<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Http\Resources\GenreResource;
use App\Models\Genre;
use App\Services\GenreService;
use Exception;
use Illuminate\Http\JsonResponse;

class GenreController extends Controller
{
    public function __construct(
        private GenreService $genreService
    ) {}

    /**
     * Display a listing of genres.
     */
    public function index(): JsonResponse
    {
        $genres = Genre::withCount('books')->get();

        return response()->json([
            'success' => true,
            'data' => GenreResource::collection($genres),
        ], 200);
    }

    /**
     * Store a newly created genre.
     */
    public function store(StoreGenreRequest $request): JsonResponse
    {
        try {
            $genre = $this->genreService->createGenre($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Genre created successfully.',
                'data' => new GenreResource($genre),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating genre: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified genre.
     */
    public function show(int $id): JsonResponse
    {
        $genre = Genre::withCount('books')->find($id);

        if (! $genre) {
            return response()->json([
                'success' => false,
                'message' => 'Genre not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new GenreResource($genre),
        ], 200);
    }

    /**
     * Update the specified genre.
     */
    public function update(UpdateGenreRequest $request, int $id): JsonResponse
    {
        $genre = Genre::find($id);

        if (! $genre) {
            return response()->json([
                'success' => false,
                'message' => 'Genre not found.',
            ], 404);
        }

        try {
            $genre = $this->genreService->updateGenre($genre, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Genre updated successfully.',
                'data' => new GenreResource($genre),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating genre: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified genre.
     */
    public function destroy(int $id): JsonResponse
    {
        $genre = Genre::find($id);

        if (! $genre) {
            return response()->json([
                'success' => false,
                'message' => 'Genre not found.',
            ], 404);
        }

        try {
            $this->genreService->deleteGenre($genre);

            return response()->json([
                'success' => true,
                'message' => 'Genre deleted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
