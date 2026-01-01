<?php

namespace App\Services;

use App\Models\Genre;
use Exception;
use Illuminate\Support\Facades\DB;

class GenreService
{
    /**
     * Create a new genre.
     */
    public function createGenre(array $data): Genre
    {
        return Genre::create([
            'name' => $data['name'],
        ]);
    }

    /**
     * Update an existing genre.
     */
    public function updateGenre(Genre $genre, array $data): Genre
    {
        $genre->update([
            'name' => $data['name'] ?? $genre->name,
        ]);

        return $genre->fresh();
    }

    /**
     * Delete a genre.
     */
    public function deleteGenre(Genre $genre): void
    {
        DB::beginTransaction();

        try {
            // Check if genre has books
            if ($genre->books()->exists()) {
                throw new Exception('Cannot delete genre that has books. Please remove books first.');
            }

            $genre->delete();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
