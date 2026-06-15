<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\NotificationMaster;
use Illuminate\Http\Request;

class NotificationMasterController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationMaster::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        $notifications = $query
            ->orderBy('sort_order')
            ->paginate(30)
            ->withQueryString();

        $categories = NotificationMaster::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view(
            'admin.system.notification_masters.index',
            compact(
                'notifications',
                'categories'
            )
        );
    }

    public function create()
    {
        return view(
            'admin.system.notification_masters.create'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:notification_masters,code'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
        ]);

        NotificationMaster::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'],
            'default_enabled' => $request->boolean('default_enabled'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.system.notification-masters.index')
            ->with('success', '通知マスタを登録しました。');
    }

    public function edit(NotificationMaster $notificationMaster)
    {
        return view(
            'admin.system.notification_masters.edit',
            compact('notificationMaster')
        );
    }

    public function update(
        Request $request,
        NotificationMaster $notificationMaster
    ) {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                'unique:notification_masters,code,' . $notificationMaster->id,
            ],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
        ]);

        $notificationMaster->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'],
            'default_enabled' => $request->boolean('default_enabled'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.system.notification-masters.index')
            ->with('success', '通知マスタを更新しました。');
    }

    public function destroy(NotificationMaster $notificationMaster)
    {
        $notificationMaster->delete();

        return redirect()
            ->route('admin.system.notification-masters.index')
            ->with('success', '通知マスタを削除しました。');
    }
}
