<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Services\AuthorService;
use Exception;
use Illuminate\Http\JsonResponse;

class AuthorController extends Controller
{
    public function __construct(
        private AuthorService $authorService
    ) {}

    /**
     * Display a listing of authors.
     */
    public function index(): JsonResponse
    {
        $authors = Author::withCount('books')->get();

        return response()->json([
            'success' => true,
            'data' => AuthorResource::collection($authors),
        ], 200);
    }

    /**
     * Store a newly created author.
     */
    public function store(StoreAuthorRequest $request): JsonResponse
    {
        try {
            $author = $this->authorService->createAuthor($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Author created successfully.',
                'data' => new AuthorResource($author),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating author: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified author.
     */
    public function show(int $id): JsonResponse
    {
        $author = Author::withCount('books')->find($id);

        if (! $author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new AuthorResource($author),
        ], 200);
    }

    /**
     * Update the specified author.
     */
    public function update(UpdateAuthorRequest $request, int $id): JsonResponse
    {
        $author = Author::find($id);

        if (! $author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        try {
            $author = $this->authorService->updateAuthor($author, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Author updated successfully.',
                'data' => new AuthorResource($author),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating author: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified author.
     */
    public function destroy(int $id): JsonResponse
    {
        $author = Author::find($id);

        if (! $author) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found.',
            ], 404);
        }

        try {
            $this->authorService->deleteAuthor($author);

            return response()->json([
                'success' => true,
                'message' => 'Author deleted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
