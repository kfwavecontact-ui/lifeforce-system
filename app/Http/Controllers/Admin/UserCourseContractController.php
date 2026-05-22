<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CoursePrice;
use App\Models\UserCourseContract;
use Illuminate\Http\Request;

class UserCourseContractController extends Controller
{
    public function create()
    {
        $users = User::orderBy('id')->get();
        $coursePrices = CoursePrice::with('course')->orderBy('course_id')->get();

        return view('admin.user_course_contracts.create', compact('users', 'coursePrices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'course_price_id' => ['required', 'exists:course_prices,id'],
            'start_date' => ['required', 'date'],
        ]);

        UserCourseContract::create([
            'user_id' => $request->user_id,
            'course_price_id' => $request->course_price_id,
            'start_date' => $request->start_date,
            'status' => 'active',
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', '契約を追加しました。');
    }
}
