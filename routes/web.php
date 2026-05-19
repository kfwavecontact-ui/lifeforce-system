<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';


use App\Http\Controllers\MaterialDownloadController;

Route::get('/materials/test-download', [MaterialDownloadController::class, 'test'])
    ->middleware('auth')
    ->name('materials.test-download');


use Illuminate\Support\Facades\Storage;

Route::middleware(['auth'])->group(function () {

    // PDFをブラウザで表示
    Route::get('/materials/view/{file}', function ($file) {

        $path = 'materials/' . $file;

        if (!Storage::disk('s3')->exists($path)) {
            abort(404);
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $path,
            now()->addMinutes(10),
            [
                'ResponseContentDisposition' => 'inline; filename="' . $file . '"',
            ]
        );

        return redirect($url);

    });

    // PDFを強制ダウンロード
    Route::get('/materials/download/{file}', function ($file) {

        $path = 'materials/' . $file;

        if (!Storage::disk('s3')->exists($path)) {
            abort(404);
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $path,
            now()->addMinutes(10),
            [
                'ResponseContentDisposition' => 'attachment; filename="' . $file . '"',
            ]
        );

        return redirect($url);

    });

});
