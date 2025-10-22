<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Folder routes
    Route::post('folders/reorder', [\App\Http\Controllers\FolderController::class, 'reorder'])
        ->name('folders.reorder');
    Route::resource('folders', \App\Http\Controllers\FolderController::class);

    // File routes
    Route::resource('files', \App\Http\Controllers\FileController::class);
    Route::get('files/{file}/download', [\App\Http\Controllers\FileController::class, 'download'])
        ->name('files.download');
    Route::get('files/{file}/preview', [\App\Http\Controllers\FileController::class, 'preview'])
        ->name('files.preview');
});

require __DIR__.'/settings.php';
