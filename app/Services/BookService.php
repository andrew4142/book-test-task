<?php

namespace App\Services;

use App\Models\Book;
use Exception;
use Illuminate\Support\Facades\DB;

class BookService
{
    /**
     * Create a new book with authors and genres.
     */
    public function createBook(array $data): Book
    {
        DB::beginTransaction();

        try {
            // Create book
            $book = Book::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'edition' => $data['edition'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'year' => $data['year'] ?? null,
                'format' => $data['format'] ?? null,
                'pages' => $data['pages'] ?? null,
                'country' => $data['country'] ?? null,
                'isbn' => $data['isbn'],
            ]);

            // Attach authors by IDs
            if (! empty($data['author_ids'])) {
                $book->authors()->attach($data['author_ids']);
            }

            // Attach genres by IDs
            if (! empty($data['genre_ids'])) {
                $book->genres()->attach($data['genre_ids']);
            }

            DB::commit();

            // Load relationships
            $book->load(['authors', 'genres']);

            return $book;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing book with new data.
     */
    public function updateBook(Book $book, array $data): Book
    {
        DB::beginTransaction();

        try {
            // Update book fields
            $book->update([
                'title' => $data['title'] ?? $book->title,
                'description' => $data['description'] ?? $book->description,
                'edition' => $data['edition'] ?? $book->edition,
                'publisher' => $data['publisher'] ?? $book->publisher,
                'year' => $data['year'] ?? $book->year,
                'format' => $data['format'] ?? $book->format,
                'pages' => $data['pages'] ?? $book->pages,
                'country' => $data['country'] ?? $book->country,
                'isbn' => $data['isbn'] ?? $book->isbn,
            ]);

            // Update authors by IDs if provided
            if (isset($data['author_ids'])) {
                $book->authors()->sync($data['author_ids']);
            }

            // Update genres by IDs if provided
            if (isset($data['genre_ids'])) {
                $book->genres()->sync($data['genre_ids']);
            }

            DB::commit();

            // Reload relationships
            $book->load(['authors', 'genres']);

            return $book;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a book and its relationships.
     */
    public function deleteBook(Book $book): void
    {
        DB::beginTransaction();

        try {
            // Detach all relationships
            $book->authors()->detach();
            $book->genres()->detach();

            // Delete the book
            $book->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
