<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    /**
     * ユーザー一覧
     */
    public function index()
    {
        $users = User::with(
            'courseContracts.coursePrice.course'
        )->get();

        return view('admin.users.index', compact('users'));
    }
}
