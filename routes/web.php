<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StudentKarteController;
use App\Http\Controllers\Admin\System\MasterController;
use App\Http\Controllers\Admin\System\NotificationMasterController;
use App\Http\Controllers\Admin\System\CourseManagementController;
use App\Http\Controllers\Admin\System\ChallengeManagementController;
use App\Http\Controllers\Admin\System\BadgeManagementController;



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

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/system/master', [MasterController::class, 'index'])
            ->name('system.master');

        Route::get('/system/master/list', [MasterController::class, 'list'])
            ->name('system.master.list');

        Route::post('/system/master', [MasterController::class, 'store'])
            ->name('system.master.store');

        Route::put('/system/master/{id}', [MasterController::class, 'update'])
            ->name('system.master.update');

        Route::post('/system/master/reorder', [MasterController::class, 'reorder'])
            ->name('system.master.reorder');

        Route::get('/system/notification-masters', [MasterController::class, 'notificationMasters'])
            ->name('system.notification-masters');

        Route::get('/system/role-notification-settings', [MasterController::class, 'roleNotificationSettings'])
            ->name('system.role-notification-settings');
        
        Route::get('/system/notification-histories', [MasterController::class, 'notificationHistories'])
            ->name('system.notification-histories');

        Route::put('/system/role-notification-settings', [MasterController::class, 'updateRoleNotificationSettings'])
            ->name('system.role-notification-settings.update');

        Route::get('/system/courses', [CourseManagementController::class, 'index'])
            ->name('system.courses');

        Route::get('/system/courses/list', [CourseManagementController::class, 'list'])
            ->name('system.courses.list');

        Route::post('/system/courses', [CourseManagementController::class, 'store'])
            ->name('system.courses.store');

        Route::put('/system/courses/{coursePrice}', [CourseManagementController::class, 'update'])
            ->name('system.courses.update');

        Route::post('/system/courses/reorder', [CourseManagementController::class, 'reorder'])
            ->name('system.courses.reorder');

        Route::get('/system/badges/list', [BadgeManagementController::class, 'list'])
            ->name('system.badges.list');

        Route::post('/system/badges', [BadgeManagementController::class, 'store'])
            ->name('system.badges.store');

        Route::post('/system/badges/reorder', [BadgeManagementController::class, 'reorder'])
            ->name('system.badges.reorder');

        Route::post('/system/badges/{badge}/duplicate', [BadgeManagementController::class, 'duplicate'])
            ->name('system.badges.duplicate');

        Route::post('/system/badges/{badge}/deactivate', [BadgeManagementController::class, 'deactivate'])
            ->name('system.badges.deactivate');

        Route::delete('/system/badges/{badge}', [BadgeManagementController::class, 'destroy'])
            ->name('system.badges.destroy');

        Route::post('/system/badges/bulk-deactivate', [BadgeManagementController::class, 'bulkDeactivate'])
            ->name('system.badges.bulk-deactivate');

        Route::post('/system/badges/bulk-destroy', [BadgeManagementController::class, 'bulkDestroy'])
            ->name('system.badges.bulk-destroy');

        Route::put('/system/badges/{badge}', [BadgeManagementController::class, 'update'])
            ->name('system.badges.update');

        Route::get('/system/badges', [BadgeManagementController::class, 'index'])
            ->name('system.badges');
        
        Route::get('/system/challenges/list', [ChallengeManagementController::class, 'list'])
            ->name('system.challenges.list');
        
        Route::post('/system/challenges', [ChallengeManagementController::class, 'store'])
            ->name('system.challenges.store');

        Route::post('/system/challenges/reorder', [ChallengeManagementController::class, 'reorder'])
            ->name('system.challenges.reorder');

        Route::post('/system/challenges/{challenge}/duplicate',[ChallengeManagementController::class, 'duplicate'])
            ->name('system.challenges.duplicate');

        Route::post('/system/challenges/{challenge}/deactivate',[ChallengeManagementController::class, 'deactivate'])
            ->name('system.challenges.deactivate');
        
        Route::delete('/system/challenges/{challenge}',[ChallengeManagementController::class, 'destroy'])
            ->name('system.challenges.destroy');

        Route::post('/system/challenges/bulk-deactivate',[ChallengeManagementController::class, 'bulkDeactivate'])
            ->name('system.challenges.bulk-deactivate');

        Route::post('/system/challenges/bulk-destroy',[ChallengeManagementController::class, 'bulkDestroy'])
            ->name('system.challenges.bulk-destroy');

        Route::put('/system/challenges/{challenge}', [ChallengeManagementController::class, 'update'])
            ->name('system.challenges.update');            

        Route::get('/system/challenges', [ChallengeManagementController::class, 'index'])
            ->name('system.challenges');


    });

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

