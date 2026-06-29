<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $permissions = Permission::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group_name');

        $rolePermissionMap = RolePermission::query()
            ->get()
            ->mapWithKeys(function ($rolePermission) {
                return [
                    $rolePermission->role_id . '_' . $rolePermission->permission_id => true,
                ];
            });

        return view('admin.system.permissions.index', compact(
            'roles',
            'permissions',
            'rolePermissionMap'
        ));
    }

    public function update(Request $request)
    {
        $submittedPermissions = $request->input('permissions', []);

        DB::transaction(function () use ($submittedPermissions) {
            RolePermission::query()->delete();

            foreach ($submittedPermissions as $roleId => $permissionIds) {
                foreach ($permissionIds as $permissionId) {
                    RolePermission::query()->create([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => '権限を保存しました。',
        ]);
    }
}