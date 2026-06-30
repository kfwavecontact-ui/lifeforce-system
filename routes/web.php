<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StudentKarteController;
use App\Http\Controllers\Admin\System\MasterController;
use App\Http\Controllers\Admin\System\NotificationMasterController;
use App\Http\Controllers\Admin\System\CourseManagementController;
use App\Http\Controllers\Admin\System\ChallengeManagementController;
use App\Http\Controllers\Admin\System\BadgeManagementController;
use App\Http\Controllers\Admin\System\TitleManagementController;
use App\Http\Controllers\Admin\System\PermissionController;
use App\Http\Controllers\Admin\Account\AccountTransactionController;
use App\Http\Controllers\Admin\Account\TuitionEnrollmentSaleController;
use App\Http\Controllers\Admin\Account\ShopSaleController;
use App\Http\Controllers\Admin\Account\EventSaleController;
use App\Http\Controllers\Admin\Account\SpotSaleController;
use App\Http\Controllers\Admin\Account\RefundController;
use App\Http\Controllers\Admin\Account\PointProductCostController;
use App\Http\Controllers\Admin\Account\ExpenseController;


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

        Route::delete('/system/master/{id}', [MasterController::class, 'destroy'])
            ->name('system.master.destroy');

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

        Route::get('/system/titles/list', [TitleManagementController::class, 'list'])
            ->name('system.titles.list');

        Route::get('/system/titles/events/search', [TitleManagementController::class, 'searchEvents'])
            ->name('system.titles.events.search');

        Route::post('/system/titles', [TitleManagementController::class, 'store'])
            ->name('system.titles.store');

        Route::post('/system/titles/reorder', [TitleManagementController::class, 'reorder'])
            ->name('system.titles.reorder');

        Route::post('/system/titles/{title}/duplicate', [TitleManagementController::class, 'duplicate'])
            ->name('system.titles.duplicate');

        Route::post('/system/titles/{title}/deactivate', [TitleManagementController::class, 'deactivate'])
            ->name('system.titles.deactivate');

        Route::delete('/system/titles/{title}', [TitleManagementController::class, 'destroy'])
            ->name('system.titles.destroy');

        Route::post('/system/titles/bulk-deactivate', [TitleManagementController::class, 'bulkDeactivate'])
            ->name('system.titles.bulk-deactivate');

        Route::post('/system/titles/bulk-destroy', [TitleManagementController::class, 'bulkDestroy'])
            ->name('system.titles.bulk-destroy');

        Route::put('/system/titles/{title}', [TitleManagementController::class, 'update'])
            ->name('system.titles.update');

        Route::get('/system/titles', [TitleManagementController::class, 'index'])
            ->name('system.titles');

        Route::get('/system/permissions', [PermissionController::class, 'index'])
            ->name('system.permissions');

        Route::post('/system/permissions', [PermissionController::class, 'update'])
            ->name('system.permissions.update');

        Route::prefix('operations/classroom-accounting')
            ->name('admin.operations.classroom-accounting.')
            ->group(function () {
            });
        
        Route::prefix('operations/classroom-accounting')
        ->name('operations.classroom-accounting.')
        ->group(function () {
            Route::get('/transactions', [AccountTransactionController::class, 'index'])
                ->name('transactions.index');

            Route::get('/transactions/export', [AccountTransactionController::class, 'export'])
                ->name('transactions.export');

            Route::get('/tuition-enrollment-sales', [TuitionEnrollmentSaleController::class, 'index'])
                ->name('tuition-enrollment-sales.index');

            Route::get('/tuition-enrollment-sales/students/search', [TuitionEnrollmentSaleController::class, 'searchStudents'])
                ->name('tuition-enrollment-sales.students.search');

            Route::get('/tuition-enrollment-sales/export', [TuitionEnrollmentSaleController::class, 'export'])
                ->name('tuition-enrollment-sales.export');
            
            Route::post('/tuition-enrollment-sales', [TuitionEnrollmentSaleController::class, 'store'])
                ->name('tuition-enrollment-sales.store');
            
            Route::put('/tuition-enrollment-sales/{invoiceItemId}', [TuitionEnrollmentSaleController::class, 'update'])
                ->name('tuition-enrollment-sales.update');
            
            // ショップ売上
            Route::get('/shop-sales', [ShopSaleController::class, 'index'])
                ->name('shop-sales.index');

            Route::get('/shop-sales/students/search', [ShopSaleController::class, 'searchStudents'])
                ->name('shop-sales.students.search');

            Route::get('/shop-sales/export', [ShopSaleController::class, 'export'])
                ->name('shop-sales.export');

            Route::post('/shop-sales', [ShopSaleController::class, 'store'])
                ->name('shop-sales.store');

            Route::put('/shop-sales/{invoiceItemId}', [ShopSaleController::class, 'update'])
                ->name('shop-sales.update');
            
            Route::get('/shop-sales/product-search', [ShopSaleController::class, 'searchProducts'])
                ->name('shop-sales.product-search');


            // イベント売上
            Route::get('/event-sales', [EventSaleController::class, 'index'])
                ->name('event-sales.index');

            Route::get('/event-sales/students/search', [EventSaleController::class, 'searchStudents'])
                ->name('event-sales.students.search');

            Route::get('/event-sales/export', [EventSaleController::class, 'export'])
                ->name('event-sales.export');

            Route::post('/event-sales', [EventSaleController::class, 'store'])
                ->name('event-sales.store');

            Route::put('/event-sales/{eventApplicationId}', [EventSaleController::class, 'update'])
                ->name('event-sales.update');

            Route::get('/event-sales/events/search', [EventSaleController::class, 'searchEvents'])
                ->name('event-sales.events.search');


            // スポット売上
            Route::get('/spot-sales', [SpotSaleController::class, 'index'])
                ->name('spot-sales.index');

            Route::get('/spot-sales/students/search', [SpotSaleController::class, 'searchStudents'])
                ->name('spot-sales.students.search');

            Route::get('/spot-sales/export', [SpotSaleController::class, 'export'])
                ->name('spot-sales.export');

            Route::post('/spot-sales', [SpotSaleController::class, 'store'])
                ->name('spot-sales.store');

            Route::put('/spot-sales/{spotSaleId}', [SpotSaleController::class, 'update'])
                ->name('spot-sales.update');


            // 返金
            Route::get('/refunds', [RefundController::class, 'index'])
                ->name('refunds.index');

            Route::get('/refunds/students/search', [RefundController::class, 'searchStudents'])
                ->name('refunds.students.search');

            Route::get('/refunds/export', [RefundController::class, 'export'])
                ->name('refunds.export');

            Route::post('/refunds', [RefundController::class, 'store'])
                ->name('refunds.store');

            Route::put('/refunds/{invoiceItemId}', [RefundController::class, 'update'])
                ->name('refunds.update');


            // ポイント商品費用
            Route::get('/point-product-costs', [PointProductCostController::class, 'index'])
                ->name('point-product-costs.index');

            Route::get('/point-product-costs/students/search', [PointProductCostController::class, 'searchStudents'])
                ->name('point-product-costs.students.search');

            Route::get('/point-product-costs/export', [PointProductCostController::class, 'export'])
                ->name('point-product-costs.export');

            Route::post('/point-product-costs', [PointProductCostController::class, 'store'])
                ->name('point-product-costs.store');

            Route::put('/point-product-costs/{invoiceItemId}', [PointProductCostController::class, 'update'])
                ->name('point-product-costs.update');


            // 経費
            Route::get('/expenses', [ExpenseController::class, 'index'])
                ->name('expenses.index');

            Route::get('/expenses/students/search', [ExpenseController::class, 'searchStudents'])
                ->name('expenses.students.search');

            Route::get('/expenses/export', [ExpenseController::class, 'export'])
                ->name('expenses.export');

            Route::post('/expenses', [ExpenseController::class, 'store'])
                ->name('expenses.store');

            Route::put('/expenses/{invoiceItemId}', [ExpenseController::class, 'update'])
                ->name('expenses.update');
        });

    });

Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

