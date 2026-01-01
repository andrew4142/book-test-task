<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/status', [ImportController::class, 'status'])->name('api.status');

// Import endpoints (async)
Route::post('/import', [ImportController::class, 'import'])->name('api.import');
Route::get('/import/{id}', [ImportController::class, 'importStatus'])->name('api.import.status');

// Books endpoints
Route::apiResource('books', BookController::class);

// Authors endpoints
Route::apiResource('authors', AuthorController::class);

// Genres endpoints
Route::apiResource('genres', GenreController::class);
