<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [\App\Http\Controllers\ListingController::class, 'index'])->name('home');
Route::get('/browse/{folder}', [\App\Http\Controllers\ListingController::class, 'show'])->name('browse');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Folder routes
    Route::post('folders/reorder', [\App\Http\Controllers\FolderController::class, 'reorder'])
        ->name('folders.reorder');
    Route::post('folders/{id}/restore', [\App\Http\Controllers\FolderController::class, 'restore'])
        ->name('folders.restore');
    Route::resource('folders', \App\Http\Controllers\FolderController::class)
        ->withTrashed(['show', 'destroy']);

    // File routes
    Route::resource('files', \App\Http\Controllers\FileController::class)
        ->withTrashed(['destroy']);
    Route::get('files/{file}/download', [\App\Http\Controllers\FileController::class, 'download'])
        ->name('files.download');
    Route::get('files/{file}/preview', [\App\Http\Controllers\FileController::class, 'preview'])
        ->name('files.preview');
});

require __DIR__.'/settings.php';
