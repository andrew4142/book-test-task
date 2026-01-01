<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Services\BookService;
use Exception;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    public function __construct(
        private BookService $bookService
    ) {}

    /**
     * Get list of all books with pagination, search and sorting.
     */
    public function index(IndexBookRequest $request): JsonResponse
    {
        $query = Book::with('authors:id,name')
            ->select('id', 'title', 'publisher', 'year');

        // Search by title
        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%'.$request->title.'%');
        }

        // Search by author name
        if ($request->filled('author')) {
            $query->whereHas('authors', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%'.$request->author.'%');
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $books = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => new BookCollection($books->items()),
            'pagination' => [
                'current_page' => $books->currentPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
                'last_page' => $books->lastPage(),
                'from' => $books->firstItem(),
                'to' => $books->lastItem(),
            ],
            'links' => [
                'first' => $books->url(1),
                'last' => $books->url($books->lastPage()),
                'prev' => $books->previousPageUrl(),
                'next' => $books->nextPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a single book by ID with authors and genres.
     */
    public function show(int $id): JsonResponse
    {
        $book = Book::with(['authors', 'genres'])->find($id);

        if (! $book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new BookResource($book),
        ], 200);
    }

    /**
     * Create a new book.
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        try {
            $book = $this->bookService->createBook($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully.',
                'data' => new BookResource($book),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating book: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing book.
     */
    public function update(UpdateBookRequest $request, int $id): JsonResponse
    {
        $book = Book::find($id);

        if (! $book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        try {
            $book = $this->bookService->updateBook($book, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully.',
                'data' => new BookResource($book),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating book: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a book.
     */
    public function destroy(int $id): JsonResponse
    {
        $book = Book::find($id);

        if (! $book) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found.',
            ], 404);
        }

        try {
            $this->bookService->deleteBook($book);

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting book: '.$e->getMessage(),
            ], 500);
        }
    }
}
