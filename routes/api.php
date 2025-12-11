<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/status', [ImportController::class, 'status'])->name('api.status');

// Import endpoint
Route::post('/import', [ImportController::class, 'import'])->name('api.import');

// Books endpoints
Route::get('/books', [BookController::class, 'index'])->name('api.books.index');
Route::post('/books', [BookController::class, 'store'])->name('api.books.store');
Route::get('/books/{id}', [BookController::class, 'show'])->name('api.books.show');
Route::put('/books/{id}', [BookController::class, 'update'])->name('api.books.update');
Route::delete('/books/{id}', [BookController::class, 'destroy'])->name('api.books.destroy');

// Authors endpoint
Route::get('/authors', [BookController::class, 'authors'])->name('api.authors');

// Genres endpoint
Route::get('/genres', [BookController::class, 'genres'])->name('api.genres');

