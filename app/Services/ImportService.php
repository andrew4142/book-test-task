<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportService
{
    public function __construct(
        private BookService $bookService
    ) {}

    /**
     * Import books from CSV file with chunk processing.
     */
    public function importFromCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new Exception('Failed to open file.');
        }

        try {
            // Read headers
            $headers = fgetcsv($handle);

            // Clean headers from BOM and spaces
            if ($headers) {
                $headers = array_map(function ($header) {
                    $header = str_replace("\xEF\xBB\xBF", '', $header);

                    return trim($header);
                }, $headers);
            }

            if (! $this->validateHeaders($headers)) {
                throw new Exception('Invalid CSV file structure. Expected columns: Authors, Title, Genre, Description, Edition, Publisher, Year, Format, Pages, Country, ISBN');
            }

            $importedCount = 0;
            $failedCount = 0;
            $errors = [];
            $rowNumber = 1; // Header is row 1
            $chunkSize = 100;
            $chunk = [];

            // Process file in chunks
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $chunk[] = ['row' => $row, 'number' => $rowNumber];

                // Process chunk when it reaches the size limit
                if (count($chunk) >= $chunkSize) {
                    $result = $this->processChunk($chunk, $headers);
                    $importedCount += $result['imported'];
                    $failedCount += $result['failed'];
                    $errors = array_merge($errors, $result['errors']);
                    $chunk = [];
                }
            }

            // Process remaining rows
            if (! empty($chunk)) {
                $result = $this->processChunk($chunk, $headers);
                $importedCount += $result['imported'];
                $failedCount += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
            }

            // Log errors if any
            if (! empty($errors)) {
                Log::warning('CSV Import completed with errors', [
                    'imported' => $importedCount,
                    'failed' => $failedCount,
                    'errors' => $errors,
                ]);
            }

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'failed_count' => $failedCount,
                'errors' => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Process a chunk of rows in a single transaction.
     */
    private function processChunk(array $chunk, array $headers): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($chunk as $item) {
                try {
                    $this->processRow($item['row'], $headers);
                    $imported++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $item['number'],
                        'error' => $e->getMessage(),
                    ];
                    // Continue processing other rows in chunk
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('CSV Import Chunk Error', [
                'message' => $e->getMessage(),
                'chunk_size' => count($chunk),
            ]);

            // Mark all rows in chunk as failed
            $failed = count($chunk);
            foreach ($chunk as $item) {
                $errors[] = [
                    'row' => $item['number'],
                    'error' => 'Chunk processing failed: '.$e->getMessage(),
                ];
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Validate CSV headers.
     */
    private function validateHeaders(?array $headers): bool
    {
        if ($headers === null) {
            return false;
        }

        $expectedHeaders = [
            'Authors', 'Title', 'Genre', 'Description', 'Edition',
            'Publisher', 'Year', 'Format', 'Pages', 'Country', 'ISBN',
        ];

        Log::debug('CSV Headers validation', [
            'received' => $headers,
            'expected' => $expectedHeaders,
            'match' => $headers === $expectedHeaders,
        ]);

        return $headers === $expectedHeaders;
    }

    /**
     * Process a single CSV row.
     */
    private function processRow(array $row, array $headers): void
    {
        $data = array_combine($headers, $row);

        // Validate required fields
        if (empty($data['Title']) || empty($data['ISBN'])) {
            throw new Exception('Title and ISBN are required fields.');
        }

        // Check if book with this ISBN already exists
        if (Book::query()->where('isbn', $data['ISBN'])->exists()) {
            throw new Exception("Book with ISBN {$data['ISBN']} already exists in database.");
        }

        // Parse and create authors/genres
        $authorIds = $this->parseAuthorsFromCsv($data['Authors'] ?? null);
        $genreIds = $this->parseGenresFromCsv($data['Genre'] ?? null);

        // Use BookService to create the book
        $this->bookService->createBook([
            'title' => $data['Title'],
            'description' => $data['Description'] ?: null,
            'edition' => $data['Edition'] ?: null,
            'publisher' => $data['Publisher'] ?: null,
            'year' => ! empty($data['Year']) ? $data['Year'] : null,
            'format' => $data['Format'] ?: null,
            'pages' => ! empty($data['Pages']) ? (int) $data['Pages'] : null,
            'country' => $data['Country'] ?: null,
            'isbn' => $data['ISBN'],
            'author_ids' => $authorIds,
            'genre_ids' => $genreIds,
        ]);
    }

    /**
     * Parse authors from CSV field (split by semicolon) and return IDs.
     */
    private function parseAuthorsFromCsv(?string $authorsString): array
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
     * Parse genres from CSV field (split by semicolon) and return IDs.
     */
    private function parseGenresFromCsv(?string $genresString): array
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
