<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\RewardCategory;
use App\Models\RewardItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * システム ＞ 設定 ＞ ポイント商品管理。
 *
 * 役割:
 * - 商品の検索、絞り込み、並び替え
 * - 商品の登録、編集、複製、公開切替、論理削除
 * - 在庫・予約在庫・画像の一体管理
 *
 * 更新対象:
 * reward_items / reward_item_stocks / reward_item_images
 */
class PointProductManagementController extends Controller
{
    public function index()
    {
        return view('admin.system.point-products.index', [
            'categories' => RewardCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /** 一覧データと画面上部の集計値を返します。 */
    public function list(Request $request): JsonResponse
    {
        try {
            $query = RewardItem::query()->with([
                'category', 'stock', 'mainImage', 'images',
            ]);

            $this->applyFilters($query, $request);
            $this->applySort($query, (string) $request->input('sort'));
            $items = $query->paginate(30)->withQueryString();

            return response()->json([
                'data' => $items->items(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'stats' => $this->stats(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('ポイント商品一覧の取得に失敗しました。', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
                'filters' => $request->query(),
            ]);

            return response()->json([
                'message' => 'ポイント商品一覧を取得できませんでした。Laravelログを確認してください。',
                'error' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $item = DB::transaction(function () use ($request, $data) {
            $stock = $this->stockData($data);
            unset($data['stock_quantity'], $data['reserved_quantity'], $data['alert_quantity'], $data['images']);

            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
            $data['stock_quantity'] = $stock['stock_quantity'];
            $data['stock_alert_quantity'] = $stock['alert_quantity'];
            $data['sort_order'] ??= ((int) RewardItem::max('sort_order')) + 1;

            $item = RewardItem::create($data);
            $item->stock()->create($stock + ['updated_by' => auth()->id()]);
            $this->syncImages($request, $item);

            return $item;
        });

        return response()->json(['message' => 'ポイント商品を登録しました。', 'id' => $item->id], 201);
    }

    public function update(Request $request, RewardItem $rewardItem): JsonResponse
    {
        $data = $this->validated($request, $rewardItem->id);

        DB::transaction(function () use ($request, $rewardItem, $data) {
            $stock = $this->stockData($data);
            unset($data['stock_quantity'], $data['reserved_quantity'], $data['alert_quantity'], $data['images']);

            $data['updated_by'] = auth()->id();
            $data['stock_quantity'] = $stock['stock_quantity'];
            $data['stock_alert_quantity'] = $stock['alert_quantity'];

            $rewardItem->update($data);
            $rewardItem->stock()->updateOrCreate([], $stock + ['updated_by' => auth()->id()]);
            $this->syncImages($request, $rewardItem);
        });

        return response()->json(['message' => 'ポイント商品を更新しました。']);
    }

    /** 商品本体・在庫・画像ファイルを完全分離して複製します。 */
    public function duplicate(RewardItem $rewardItem): JsonResponse
    {
        $copy = DB::transaction(function () use ($rewardItem) {
            $rewardItem->load(['stock', 'images']);

            $copy = $rewardItem->replicate(['code', 'image_url', 'created_at', 'updated_at']);
            $copy->code = $this->nextCode($rewardItem->code);
            $copy->name = $rewardItem->name . '（複製）';
            $copy->publication_status = 'draft';
            $copy->is_active = false;
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->sort_order = ((int) RewardItem::max('sort_order')) + 1;
            $copy->save();

            if ($rewardItem->stock) {
                $copy->stock()->create([
                    'stock_quantity' => $rewardItem->stock->stock_quantity,
                    'reserved_quantity' => 0,
                    'alert_quantity' => $rewardItem->stock->alert_quantity,
                    'updated_by' => auth()->id(),
                ]);
            }

            foreach ($rewardItem->images as $image) {
                if (!Storage::disk('public')->exists($image->image_path)) {
                    continue;
                }

                $extension = pathinfo($image->image_path, PATHINFO_EXTENSION);
                $newPath = "reward-items/{$copy->id}/" . uniqid('copy-', true) . ($extension ? ".{$extension}" : '');
                Storage::disk('public')->copy($image->image_path, $newPath);

                $copy->images()->create([
                    'image_path' => $newPath,
                    'alt_text' => $image->alt_text ?: $copy->name,
                    'display_order' => $image->display_order,
                    'is_main' => $image->is_main,
                ]);
            }

            $main = $copy->images()->where('is_main', true)->first() ?? $copy->images()->first();
            if ($main) {
                $main->update(['is_main' => true]);
                $copy->update(['image_url' => $main->image_path]);
            }

            return $copy;
        });

        return response()->json(['message' => 'ポイント商品を複製しました。', 'id' => $copy->id]);
    }

    /** 公開中と下書きをワンクリックで切り替えます。 */
    public function togglePublication(RewardItem $rewardItem): JsonResponse
    {
        $publishing = $rewardItem->publication_status !== 'published';

        $rewardItem->update([
            'publication_status' => $publishing ? 'published' : 'draft',
            'published_at' => $publishing ? ($rewardItem->published_at ?? now()) : $rewardItem->published_at,
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => $publishing ? '商品を公開しました。' : '商品を下書きに戻しました。',
        ]);
    }

    /** 過去の申請履歴を壊さないよう、商品は論理削除します。 */
    public function destroy(RewardItem $rewardItem): JsonResponse
    {
        $rewardItem->update([
            'is_active' => false,
            'publication_status' => 'ended',
            'updated_by' => auth()->id(),
        ]);
        $rewardItem->delete();

        return response()->json(['message' => 'ポイント商品を削除しました。']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:reward_items,id',
            'items.*.sort_order' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($data) {
            collect($data['items'])->each(function ($row) {
                RewardItem::whereKey($row['id'])->update([
                    'sort_order' => $row['sort_order'],
                    'updated_by' => auth()->id(),
                ]);
            });
        });

        return response()->json(['message' => '表示順を更新しました。']);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $keyword = trim((string) $request->input('keyword'));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where('code', 'ilike', "%{$keyword}%")
                    ->orWhere('name', 'ilike', "%{$keyword}%")
                    ->orWhere('description', 'ilike', "%{$keyword}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('reward_category_id', $request->integer('category_id'));
        }
        if ($request->filled('publication_status')) {
            $query->where('publication_status', (string) $request->input('publication_status'));
        }
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }
        if ($request->filled('recommended')) {
            $query->where('is_recommended', $request->boolean('recommended'));
        }
        if ($request->filled('limited')) {
            $query->where('is_limited', $request->boolean('limited'));
        }

        match ((string) $request->input('stock_status')) {
            'out' => $query->where('is_stock_managed', true)
                ->whereHas('stock', fn (Builder $stock) => $stock->whereRaw('(stock_quantity - reserved_quantity) <= 0')),
            'low' => $query->where('is_stock_managed', true)
                ->whereHas('stock', fn (Builder $stock) => $stock
                    ->whereRaw('(stock_quantity - reserved_quantity) > 0')
                    ->whereRaw('(stock_quantity - reserved_quantity) <= alert_quantity')),
            'available' => $query->where(function (Builder $builder) {
                $builder->where('is_stock_managed', false)
                    ->orWhereHas('stock', fn (Builder $stock) => $stock->whereRaw('(stock_quantity - reserved_quantity) > 0'));
            }),
            'unmanaged' => $query->where('is_stock_managed', false),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'updated_desc' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'points_asc' => $query->orderBy('required_points')->orderBy('id'),
            'points_desc' => $query->orderByDesc('required_points')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderBy('sort_order')->orderBy('id'),
        };
    }

    private function stats(): array
    {
        $base = RewardItem::query();

        return [
            'registered' => (clone $base)->count(),
            'published' => (clone $base)->where('publication_status', 'published')->count(),
            'out_of_stock' => (clone $base)->where('is_stock_managed', true)
                ->whereHas('stock', fn (Builder $stock) => $stock->whereRaw('(stock_quantity - reserved_quantity) <= 0'))
                ->count(),
            'low_stock' => (clone $base)->where('is_stock_managed', true)
                ->whereHas('stock', fn (Builder $stock) => $stock
                    ->whereRaw('(stock_quantity - reserved_quantity) > 0')
                    ->whereRaw('(stock_quantity - reserved_quantity) <= alert_quantity'))
                ->count(),
            'limited' => (clone $base)->where('is_limited', true)->count(),
            'ended' => (clone $base)->where('publication_status', 'ended')->count(),
        ];
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('reward_items', 'code')->ignore($id)],
            'name' => 'required|string|max:150',
            'reward_category_id' => 'required|exists:reward_categories,id',
            'description' => 'nullable|string|max:3000',
            'required_points' => 'required|integer|min:0|max:99999999',
            'cost_price' => 'nullable|integer|min:0|max:99999999',
            'publication_status' => ['required', Rule::in(['draft', 'published', 'ended'])],
            'published_at' => 'nullable|date',
            'publication_ended_at' => 'nullable|date|after_or_equal:published_at',
            'stock_quantity' => 'required|integer|min:0',
            'reserved_quantity' => 'nullable|integer|min:0',
            'alert_quantity' => 'required|integer|min:0',
            'is_stock_managed' => 'required|boolean',
            'is_recommended' => 'required|boolean',
            'is_new' => 'required|boolean',
            'is_limited' => 'required|boolean',
            'is_active' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:1',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer|exists:reward_item_images,id',
            'main_image_id' => 'nullable|integer|exists:reward_item_images,id',
        ]);
    }

    private function stockData(array $data): array
    {
        $stock = (int) $data['stock_quantity'];
        $reserved = min($stock, (int) ($data['reserved_quantity'] ?? 0));

        return [
            'stock_quantity' => $stock,
            'reserved_quantity' => $reserved,
            'alert_quantity' => (int) $data['alert_quantity'],
        ];
    }

    private function syncImages(Request $request, RewardItem $item): void
    {
        $removeIds = $request->input('remove_image_ids', []);
        $item->images()->whereIn('id', $removeIds)->get()->each(function ($image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();
        });

        $order = (int) $item->images()->max('display_order');
        foreach ($request->file('images', []) as $file) {
            $path = $file->store("reward-items/{$item->id}", 'public');
            $item->images()->create([
                'image_path' => $path,
                'alt_text' => $item->name,
                'display_order' => ++$order,
                'is_main' => !$item->images()->exists(),
            ]);
        }

        $mainId = $request->integer('main_image_id');
        if ($mainId && $item->images()->whereKey($mainId)->exists()) {
            $item->images()->update(['is_main' => false]);
            $item->images()->whereKey($mainId)->update(['is_main' => true]);
        }

        $main = $item->images()->where('is_main', true)->first() ?? $item->images()->first();
        if ($main) {
            $item->images()->whereKeyNot($main->id)->update(['is_main' => false]);
            $main->update(['is_main' => true]);
            $item->update(['image_url' => $main->image_path]);
        } else {
            $item->update(['image_url' => null]);
        }
    }

    private function nextCode(?string $base): string
    {
        $prefix = ($base ?: 'RWD') . '-COPY';
        $code = $prefix;
        $sequence = 2;

        while (RewardItem::withTrashed()->where('code', $code)->exists()) {
            $code = $prefix . $sequence++;
        }

        return $code;
    }
}
