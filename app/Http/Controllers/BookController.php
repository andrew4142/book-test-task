<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class BookController extends Controller
{
    /**
     * Get list of all books with title, authors, publisher, year only.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $books = Book::with('authors:id,name')
            ->select('id', 'title', 'publisher', 'year')
            ->get()
            ->map(function ($book) {
                return [
                    'title' => $book->title,
                    'authors' => $book->authors->pluck('name')->toArray(),
                    'publisher' => $book->publisher,
                    'year' => $book->year,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $books,
        ], 200);
    }

    /**
     * Get a single book by ID with authors and genres.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $book = Book::with(['authors', 'genres'])->find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $book,
        ], 200);
    }

    /**
     * Create a new book.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'edition' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'year' => 'nullable|date',
            'format' => 'nullable|string|max:255',
            'pages' => 'nullable|integer|min:1',
            'country' => 'nullable|string|max:255',
            'isbn' => 'required|string|unique:books,isbn',
            'authors' => 'nullable|array',
            'authors.*' => 'string',
            'genres' => 'nullable|array',
            'genres.*' => 'string',
        ], [
            'title.required' => 'Title is required.',
            'isbn.required' => 'ISBN is required.',
            'isbn.unique' => 'Book with this ISBN already exists.',
            'pages.min' => 'Pages must be at least 1.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create book
            $book = Book::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'edition' => $request->input('edition'),
                'publisher' => $request->input('publisher'),
                'year' => $request->input('year'),
                'format' => $request->input('format'),
                'pages' => $request->input('pages'),
                'country' => $request->input('country'),
                'isbn' => $request->input('isbn'),
            ]);

            // Attach authors
            if ($request->has('authors')) {
                $authorIds = $this->processAuthors($request->input('authors'));
                $book->authors()->attach($authorIds);
            }

            // Attach genres
            if ($request->has('genres')) {
                $genreIds = $this->processGenres($request->input('genres'));
                $book->genres()->attach($genreIds);
            }

            DB::commit();

            // Load relationships
            $book->load(['authors', 'genres']);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully.',
                'data' => $book,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating book: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing book.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'edition' => 'nullable|string|max:255',
            'publisher' => 'nullable|string|max:255',
            'year' => 'nullable|date',
            'format' => 'nullable|string|max:255',
            'pages' => 'nullable|integer|min:1',
            'country' => 'nullable|string|max:255',
            'isbn' => 'sometimes|required|string|unique:books,isbn,' . $id,
            'authors' => 'nullable|array',
            'authors.*' => 'string',
            'genres' => 'nullable|array',
            'genres.*' => 'string',
        ], [
            'title.required' => 'Title is required.',
            'isbn.required' => 'ISBN is required.',
            'isbn.unique' => 'Book with this ISBN already exists.',
            'pages.min' => 'Pages must be at least 1.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update book fields
            $book->update($request->only([
                'title', 'description', 'edition', 'publisher',
                'year', 'format', 'pages', 'country', 'isbn'
            ]));

            // Update authors if provided
            if ($request->has('authors')) {
                $authorIds = $this->processAuthors($request->input('authors'));
                $book->authors()->sync($authorIds);
            }

            // Update genres if provided
            if ($request->has('genres')) {
                $genreIds = $this->processGenres($request->input('genres'));
                $book->genres()->sync($genreIds);
            }

            DB::commit();

            // Reload relationships
            $book->load(['authors', 'genres']);

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully.',
                'data' => $book,
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating book: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all authors.
     *
     * @return JsonResponse
     */
    public function authors(): JsonResponse
    {
        $authors = Author::withCount('books')->get();

        return response()->json([
            'success' => true,
            'data' => $authors,
        ], 200);
    }

    /**
     * Get list of all genres.
     *
     * @return JsonResponse
     */
    public function genres(): JsonResponse
    {
        $genres = Genre::withCount('books')->get();

        return response()->json([
            'success' => true,
            'data' => $genres,
        ], 200);
    }

    /**
     * Process authors array and return author IDs.
     *
     * @param array $authors
     * @return array
     */
    private function processAuthors(array $authors): array
    {
        $authorIds = [];

        foreach ($authors as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $author = Author::firstOrCreate(['name' => $name]);
            $authorIds[] = $author->id;
        }

        return $authorIds;
    }

    /**
     * Delete a book.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Detach all relationships
            $book->authors()->detach();
            $book->genres()->detach();

            // Delete the book
            $book->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully.',
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error deleting book: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process genres array and return genre IDs.
     *
     * @param array $genres
     * @return array
     */
    private function processGenres(array $genres): array
    {
        $genreIds = [];

        foreach ($genres as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $genre = Genre::firstOrCreate(['name' => $name]);
            $genreIds[] = $genre->id;
        }

        return $genreIds;
    }
}

