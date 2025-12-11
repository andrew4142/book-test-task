<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCsvRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportController extends Controller
{
    /**
     * API status check.
     *
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Book Import API is running',
            'timestamp' => now()->toDateTimeString(),
            'endpoints' => [
                'POST /api/import' => 'Import books from CSV file',
            ],
        ], 200);
    }

    /**
     * Import books from CSV file.
     *
     * @param ImportCsvRequest $request
     * @return JsonResponse
     */
    public function import(ImportCsvRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            
            // Open file for reading
            $handle = fopen($file->getRealPath(), 'r');
            
            if ($handle === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to open file.',
                ], 500);
            }

            // Read headers
            $headers = fgetcsv($handle);
            
            // Clean headers from BOM and spaces
            if ($headers) {
                $headers = array_map(function($header) {
                    $header = str_replace("\xEF\xBB\xBF", '', $header);
                    return trim($header);
                }, $headers);
            }
            
            if (!$this->validateHeaders($headers)) {
                fclose($handle);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid CSV file structure. Expected columns: Authors, Title, Genre, Description, Edition, Publisher, Year, Format, Pages, Country, ISBN',
                    'received_headers' => $headers,
                ], 422);
            }

            $importedCount = 0;
            $errors = [];
            $rowNumber = 1; // Start from 1, as 0 is headers

            // Process each row in transaction
            DB::beginTransaction();

            try {
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;
                    
                    try {
                        $this->processRow($row, $headers);
                        $importedCount++;
                    } catch (Exception $e) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'error' => $e->getMessage(),
                        ];
                        
                        // If there's an error, rollback entire transaction
                        throw new Exception("Error in row {$rowNumber}: {$e->getMessage()}");
                    }
                }

                DB::commit();
                fclose($handle);

                return response()->json([
                    'success' => true,
                    'message' => 'Import completed successfully.',
                    'imported_count' => $importedCount,
                ], 200);

            } catch (Exception $e) {
                DB::rollBack();
                fclose($handle);

                Log::error('CSV Import Error', [
                    'message' => $e->getMessage(),
                    'errors' => $errors,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Import error: ' . $e->getMessage(),
                    'errors' => $errors,
                ], 422);
            }

        } catch (Exception $e) {
            Log::error('CSV Import Fatal Error', ['message' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Critical import error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate CSV headers.
     *
     * @param array|null $headers
     * @return bool
     */
    private function validateHeaders(?array $headers): bool
    {
        if ($headers === null) {
            return false;
        }

        $expectedHeaders = [
            'Authors', 'Title', 'Genre', 'Description', 'Edition', 
            'Publisher', 'Year', 'Format', 'Pages', 'Country', 'ISBN'
        ];

        // Log for debugging
        Log::debug('CSV Headers validation', [
            'received' => $headers,
            'expected' => $expectedHeaders,
            'match' => $headers === $expectedHeaders
        ]);

        return $headers === $expectedHeaders;
    }

    /**
     * Process a single CSV row.
     *
     * @param array $row
     * @param array $headers
     * @return void
     * @throws Exception
     */
    private function processRow(array $row, array $headers): void
    {
        // Create associative array from data
        $data = array_combine($headers, $row);

        // Validate required fields
        if (empty($data['Title']) || empty($data['ISBN'])) {
            throw new Exception('Title and ISBN are required fields.');
        }

        // Check if book with this ISBN already exists
        if (Book::where('isbn', $data['ISBN'])->exists()) {
            throw new Exception("Book with ISBN {$data['ISBN']} already exists in database.");
        }

        // Create or find authors
        $authors = $this->processAuthors($data['Authors']);

        // Create or find genres
        $genres = $this->processGenres($data['Genre']);

        // Create book
        $book = Book::create([
            'title' => $data['Title'],
            'description' => $data['Description'] ?: null,
            'edition' => $data['Edition'] ?: null,
            'publisher' => $data['Publisher'] ?: null,
            'year' => !empty($data['Year']) ? $data['Year'] : null,
            'format' => $data['Format'] ?: null,
            'pages' => !empty($data['Pages']) ? (int)$data['Pages'] : null,
            'country' => $data['Country'] ?: null,
            'isbn' => $data['ISBN'],
        ]);

        // Attach authors and genres
        if (!empty($authors)) {
            $book->authors()->attach($authors);
        }

        if (!empty($genres)) {
            $book->genres()->attach($genres);
        }
    }

    /**
     * Process authors from CSV field (split by semicolon).
     *
     * @param string|null $authorsString
     * @return array
     */
    private function processAuthors(?string $authorsString): array
    {
        if (empty($authorsString)) {
            return [];
        }

        $authorNames = array_map('trim', explode(';', $authorsString));
        $authorIds = [];

        foreach ($authorNames as $name) {
            if (empty($name)) {
                continue;
            }

            $author = Author::firstOrCreate(['name' => $name]);
            $authorIds[] = $author->id;
        }

        return $authorIds;
    }

    /**
     * Process genres from CSV field (split by semicolon).
     *
     * @param string|null $genresString
     * @return array
     */
    private function processGenres(?string $genresString): array
    {
        if (empty($genresString)) {
            return [];
        }

        $genreNames = array_map('trim', explode(';', $genresString));
        $genreIds = [];

        foreach ($genreNames as $name) {
            if (empty($name)) {
                continue;
            }

            $genre = Genre::firstOrCreate(['name' => $name]);
            $genreIds[] = $genre->id;
        }

        return $genreIds;
    }
}
