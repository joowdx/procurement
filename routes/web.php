<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [\App\Http\Controllers\ListingController::class, 'index'])->name('home');
Route::get('/browse/{folder}', [\App\Http\Controllers\ListingController::class, 'show'])->name('browse');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Workspace routes (without requiring workspace context)
    Route::post('workspaces', [\App\Http\Controllers\WorkspaceController::class, 'store'])
        ->name('workspaces.store');
    Route::post('workspaces/{workspace}/select', [\App\Http\Controllers\WorkspaceController::class, 'select'])
        ->name('workspaces.select');

    // Membership invitation routes (without requiring workspace context)
    Route::post('workspace/memberships/{workspace}/accept', [\App\Http\Controllers\MembershipController::class, 'accept'])
        ->name('workspace.membership.accept');
    Route::post('workspace/memberships/{workspace}/decline', [\App\Http\Controllers\MembershipController::class, 'decline'])
        ->name('workspace.membership.decline');
    Route::post('workspace/memberships/{workspace}/leave', [\App\Http\Controllers\MembershipController::class, 'leave'])
        ->name('workspace.membership.leave');

    // Workspace routes (requiring workspace context)
    Route::middleware(['workspace.require'])->group(function () {
        Route::get('workspace/edit', [\App\Http\Controllers\WorkspaceController::class, 'edit'])
            ->name('workspace.edit');
        Route::put('workspace', [\App\Http\Controllers\WorkspaceController::class, 'update'])
            ->name('workspace.update');
        Route::delete('workspace', [\App\Http\Controllers\WorkspaceController::class, 'destroy'])
            ->name('workspace.destroy');

        // Membership routes (workspace member management)
        Route::post('workspace/memberships', [\App\Http\Controllers\MembershipController::class, 'store'])
            ->name('workspace.membership.store');
        Route::put('workspace/memberships/{membership}', [\App\Http\Controllers\MembershipController::class, 'update'])
            ->name('workspace.membership.update');
        Route::delete('workspace/memberships/{membership}', [\App\Http\Controllers\MembershipController::class, 'destroy'])
            ->name('workspace.membership.destroy');
    });

    // Folder routes
    Route::post('folders/reorder', [\App\Http\Controllers\FolderController::class, 'reorder'])
        ->name('folders.reorder');
    Route::post('folders/{id}/restore', [\App\Http\Controllers\FolderController::class, 'restore'])
        ->name('folders.restore');
    Route::resource('folders', \App\Http\Controllers\FolderController::class)
        ->withTrashed(['show', 'destroy']);

    // File routes
    Route::post('files/{id}/restore', [\App\Http\Controllers\FileController::class, 'restore'])
        ->name('files.restore');
    Route::resource('files', \App\Http\Controllers\FileController::class)
        ->withTrashed(['destroy']);
    Route::get('files/{file}/download', [\App\Http\Controllers\FileController::class, 'download'])
        ->name('files.download');
    Route::get('files/{file}/preview', [\App\Http\Controllers\FileController::class, 'preview'])
        ->name('files.preview');
});

require __DIR__.'/settings.php';
