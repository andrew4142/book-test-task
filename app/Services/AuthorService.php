<?php

namespace App\Services;

use App\Models\Author;
use Exception;
use Illuminate\Support\Facades\DB;

class AuthorService
{
    /**
     * Create a new author.
     */
    public function createAuthor(array $data): Author
    {
        return Author::create([
            'name' => $data['name'],
        ]);
    }

    /**
     * Update an existing author.
     */
    public function updateAuthor(Author $author, array $data): Author
    {
        $author->update([
            'name' => $data['name'] ?? $author->name,
        ]);

        return $author->fresh();
    }

    /**
     * Delete an author.
     */
    public function deleteAuthor(Author $author): void
    {
        DB::beginTransaction();

        try {
            // Check if author has books
            if ($author->books()->exists()) {
                throw new Exception('Cannot delete author that has books. Please remove books first.');
            }

            $author->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
