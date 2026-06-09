<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StudentKarteController;

Route::get('/', function () {
    return redirect()->route('admin.students.karte.show', ['student' => 1]);
});

Route::get('/students/{student}/karte', [StudentKarteController::class, 'show'])
    ->name('admin.students.karte.show');

Route::get('/students/{student}/karte/edit', [StudentKarteController::class, 'edit'])
    ->name('admin.students.karte.edit');

Route::put('/students/{student}/karte', [StudentKarteController::class, 'update'])
    ->name('admin.students.karte.update');

Route::post('/students/{student}/karte/routines/apply-package', [StudentKarteController::class, 'applyRoutinePackage'])
    ->name('admin.students.karte.routines.apply-package');
Route::post('/students/{student}/karte/routines/apply-item', [StudentKarteController::class, 'applyRoutineItemPackage'])
    ->name('admin.students.karte.routines.apply-item');


Route::post('/students/{student}/karte/routines/items/{item}/daily-status', [StudentKarteController::class, 'updateRoutineDailyStatus'])
    ->name('admin.students.karte.routines.items.daily-status');

Route::put('/students/{student}/karte/routines/items/{item}', [StudentKarteController::class, 'updateRoutineItem'])
    ->name('admin.students.karte.routines.items.update');

Route::delete('/students/{student}/karte/routines/items/{item}', [StudentKarteController::class, 'deleteRoutineItem'])
    ->name('admin.students.karte.routines.items.delete');

Route::post('/students/{student}/karte/routines/items/{item}/finish', [StudentKarteController::class, 'finishRoutineItem'])
    ->name('admin.students.karte.routines.items.finish');

Route::put('/students/{student}/karte/routines/items/{item}/teacher-comment', [StudentKarteController::class, 'updateRoutineItemTeacherComment'])
    ->name('admin.students.karte.routines.items.teacher-comment');

Route::get('/students/{student}/karte/routines/history', [StudentKarteController::class, 'routineHistory'])
    ->name('admin.students.karte.routines.history');

Route::post('/students/{student}/routine/complete',[StudentKarteController::class, 'completeRoutine'])
    ->name('admin.students.routine.complete');

Route::delete('/students/{student}/routine/cancel', [StudentKarteController::class, 'cancelRoutine'])
    ->name('admin.students.routine.cancel');

Route::post('/students/{student}/routine/finish',[StudentKarteController::class, 'finishRoutine'])
    ->name('admin.students.routine.finish');

/*
|--------------------------------------------------------------------------
| 互換用：admin付きURL
|--------------------------------------------------------------------------
*/

Route::get('/admin/students/{student}/karte', [StudentKarteController::class, 'show']);
Route::post('/admin/students/{student}/karte/routines/apply-package', [StudentKarteController::class, 'applyRoutinePackage']);
Route::put('/admin/students/{student}/karte/routines/items/{item}', [StudentKarteController::class, 'updateRoutineItem']);
Route::delete('/admin/students/{student}/karte/routines/items/{item}', [StudentKarteController::class, 'deleteRoutineItem']);