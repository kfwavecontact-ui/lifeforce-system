<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ショップ商品マスタ管理。
 *
 * 関連画面: わくわく ＞ ショップ ＞ 商品一覧
 * 関連Service: なし（既存構成との互換性を優先）
 * 利用DB:
 * - shop_products: 商品基本情報・価格・現在庫・公開情報（参照／登録／更新）
 * - shop_product_stocks: 在庫警告値・補助在庫値（参照／登録／更新）
 * - shop_categories / schools: 選択肢・一覧名称（参照）
 *
 * 設計上の注意:
 * - 既存ショップ売上が shop_products.stock_quantity を参照しているため、現在庫の正は同項目とする。
 * - shop_product_stocks.stock_quantity は互換用の同期値とし、reserved_quantity は更新時に上書きしない。
 * - 過去の注文明細は shop_order_items のスナップショットを利用するため、商品編集では変更しない。
 */
class ShopProductController extends Controller
{
    /** 商品一覧を検索・並び替え・ページングして表示する。 */
    public function index(Request $request): View
    {
        $filters = $request->only(['keyword', 'category_id', 'school_id', 'active_status', 'online_status', 'stock_status']);
        $sort = $request->string('sort')->toString() ?: 'display_order';
        $direction = $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
        $sortable = ['id', 'product_code', 'name', 'price', 'purchase_price', 'stock_quantity', 'display_order', 'updated_at'];
        if (! in_array($sort, $sortable, true)) {
            $sort = 'display_order';
        }

        $query = ShopProduct::query()
            ->with(['category:id,name', 'school:id,name'])
            ->leftJoin('shop_product_stocks as sps', 'sps.shop_product_id', '=', 'shop_products.id')
            ->select('shop_products.*', DB::raw('COALESCE(sps.alert_quantity, 5) as alert_quantity'), DB::raw('COALESCE(sps.reserved_quantity, 0) as reserved_quantity'));

        $query->when($filters['keyword'] ?? null, function ($query, string $keyword): void {
            $query->where(function ($subQuery) use ($keyword): void {
                $subQuery->where('shop_products.product_code', 'like', "%{$keyword}%")
                    ->orWhere('shop_products.name', 'like', "%{$keyword}%")
                    ->orWhere('shop_products.description', 'like', "%{$keyword}%")
                    ->orWhere('shop_products.barcode', 'like', "%{$keyword}%");
            });
        });
        $query->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('shop_products.category_id', $value));
        $query->when($filters['school_id'] ?? null, fn ($query, $value) => $query->where('shop_products.school_id', $value));
        $query->when(($filters['active_status'] ?? '') !== '', fn ($query) => $query->where('shop_products.is_active', $filters['active_status'] === '1'));
        $query->when(($filters['online_status'] ?? '') !== '', fn ($query) => $query->where('shop_products.is_online', $filters['online_status'] === '1'));
        $query->when($filters['stock_status'] ?? null, function ($query, string $status): void {
            if ($status === 'out') {
                $query->where('shop_products.is_stock_managed', true)->where('shop_products.stock_quantity', '<=', 0);
            } elseif ($status === 'low') {
                $query->where('shop_products.is_stock_managed', true)
                    ->where('shop_products.stock_quantity', '>', 0)
                    ->whereRaw('shop_products.stock_quantity <= COALESCE(sps.alert_quantity, 5)');
            } elseif ($status === 'available') {
                $query->where(function ($subQuery): void {
                    $subQuery->where('shop_products.is_stock_managed', false)
                        ->orWhereRaw('shop_products.stock_quantity > COALESCE(sps.alert_quantity, 5)');
                });
            }
        });

        $products = $query
            ->orderBy('shop_products.'.$sort, $direction)
            ->orderBy('shop_products.id')
            ->paginate(20)
            ->withQueryString();

        $categories = ShopCategory::query()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $schools = School::query()->orderBy('id')->get(['id', 'name']);

        $summary = [
            'total' => ShopProduct::count(),
            'active' => ShopProduct::where('is_active', true)->count(),
            'inactive' => ShopProduct::where('is_active', false)->count(),
            'stock_alert' => ShopProduct::query()
                ->leftJoin('shop_product_stocks as sps', 'sps.shop_product_id', '=', 'shop_products.id')
                ->where('shop_products.is_stock_managed', true)
                ->whereRaw('shop_products.stock_quantity <= COALESCE(sps.alert_quantity, 5)')
                ->count(),
        ];

        return view('admin.account.shop-products.index', compact(
            'products', 'categories', 'schools', 'summary', 'filters', 'sort', 'direction'
        ));
    }

    /** 商品を登録し、在庫補助テーブルを同一トランザクションで作成する。 */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);
        $actorId = $this->resolveActorId();

        $product = DB::transaction(function () use ($request, $validated, $actorId): ShopProduct {
            $validated['image_path'] = $this->storeImage($request);
            $alertQuantity = (int) $validated['alert_quantity'];
            unset($validated['alert_quantity'], $validated['remove_image']);
            $validated['created_by'] = $actorId;
            $validated['updated_by'] = $actorId;

            $product = ShopProduct::create($validated);
            DB::table('shop_product_stocks')->insert([
                'shop_product_id' => $product->id,
                'stock_quantity' => $product->stock_quantity,
                'reserved_quantity' => 0,
                'alert_quantity' => $alertQuantity,
                'updated_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $product;
        });

        return back()->with('success', 'ショップ商品を登録しました。')->with('highlight_product_id', $product->id);
    }

    /** 商品を更新し、在庫補助テーブルの予約数を保持したまま同期する。 */
    public function update(Request $request, ShopProduct $shopProduct): RedirectResponse
    {
        $validated = $this->validateProduct($request, $shopProduct);
        $actorId = $this->resolveActorId($shopProduct);

        DB::transaction(function () use ($request, $validated, $shopProduct, $actorId): void {
            if ($request->boolean('remove_image')) {
                $this->deleteImage($shopProduct->image_path);
                $validated['image_path'] = null;
            }
            if ($request->hasFile('image')) {
                $this->deleteImage($shopProduct->image_path);
                $validated['image_path'] = $this->storeImage($request);
            }

            $alertQuantity = (int) $validated['alert_quantity'];
            unset($validated['alert_quantity'], $validated['remove_image']);
            $validated['updated_by'] = $actorId;
            $shopProduct->update($validated);

            $stockExists = DB::table('shop_product_stocks')->where('shop_product_id', $shopProduct->id)->exists();
            if ($stockExists) {
                DB::table('shop_product_stocks')->where('shop_product_id', $shopProduct->id)->update([
                    'stock_quantity' => $shopProduct->stock_quantity,
                    'alert_quantity' => $alertQuantity,
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('shop_product_stocks')->insert([
                    'shop_product_id' => $shopProduct->id,
                    'stock_quantity' => $shopProduct->stock_quantity,
                    'reserved_quantity' => 0,
                    'alert_quantity' => $alertQuantity,
                    'updated_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'ショップ商品を更新しました。')->with('highlight_product_id', $shopProduct->id);
    }

    /** 登録・更新共通の入力検証とチェックボックス正規化。 */
    private function validateProduct(Request $request, ?ShopProduct $product = null): array
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'category_id' => ['required', 'integer', 'exists:shop_categories,id'],
            'product_code' => ['required', 'string', 'max:50', Rule::unique('shop_products', 'product_code')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'purchase_price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'tax_rate' => ['required', Rule::in([0, 8, 10, '0', '8', '10'])],
            'stock_quantity' => ['required_if:is_stock_managed,1', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'alert_quantity' => ['required_if:is_stock_managed,1', 'nullable', 'integer', 'min:0', 'max:9999999'],
            'point_reward' => ['required', 'integer', 'min:0', 'max:999999999'],
            'point_price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'display_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        foreach (['is_stock_managed', 'is_online', 'is_active'] as $booleanField) {
            $validated[$booleanField] = $request->boolean($booleanField);
        }
        if (! $validated['is_stock_managed']) {
            $validated['stock_quantity'] = 0;
            $validated['alert_quantity'] = 0;
        }

        return $validated;
    }

    /**
     * 操作者IDを取得する。
     *
     * 現行の教室会計系画面では認証ガード未設定時に auth()->id() が null になるため、
     * 更新時は既存商品の更新者・作成者を優先し、それも無い場合だけ開発用管理者ID=1へフォールバックする。
     * shop_product_stocks.updated_by は NOT NULL のため、必ず整数を返す。
     */
    private function resolveActorId(?ShopProduct $product = null): int
    {
        return (int) (Auth::id() ?? $product?->updated_by ?? $product?->created_by ?? 1);
    }

    /** 代表画像を公開ディスクへ保存する。 */
    private function storeImage(Request $request): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store('shop-products', 'public')
            : null;
    }

    /** 既存代表画像が存在する場合だけ削除する。 */
    private function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
