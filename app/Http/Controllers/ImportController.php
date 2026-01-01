<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCsvRequest;
use App\Jobs\ImportBooksJob;
use App\Models\ImportLog;
use Exception;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    /**
     * API status check.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Book Import API is running',
            'timestamp' => now()->toDateTimeString(),
            'endpoints' => [
                'POST /api/import' => 'Import books from CSV file (async)',
                'GET /api/import/{id}' => 'Check import status',
            ],
        ], 200);
    }

    /**
     * Import books from CSV file (async via queue).
     */
    public function import(ImportCsvRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();

            // Store file temporarily
            $path = $file->store('imports', 'local');

            // Create import log
            $importLog = ImportLog::create([
                'filename' => $filename,
                'status' => 'pending',
            ]);

            // Dispatch job to queue
            ImportBooksJob::dispatch($importLog->id, $path);

            return response()->json([
                'success' => true,
                'message' => 'Import started. Use the import_id to check status.',
                'import_id' => $importLog->id,
                'check_status_url' => url("/api/import/{$importLog->id}"),
            ], 202); // 202 Accepted
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start import: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check import status.
     */
    public function importStatus(int $id): JsonResponse
    {
        $importLog = ImportLog::find($id);

        if (! $importLog) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found.',
            ], 404);
        }

        $response = [
            'success' => true,
            'import_id' => $importLog->id,
            'filename' => $importLog->filename,
            'status' => $importLog->status,
            'started_at' => $importLog->started_at?->toDateTimeString(),
            'completed_at' => $importLog->completed_at?->toDateTimeString(),
        ];

        if (in_array($importLog->status, ['completed', 'failed'])) {
            $response['imported_count'] = $importLog->imported_count;
            $response['failed_count'] = $importLog->failed_count;
            $response['errors'] = $importLog->errors;
        }

        return response()->json($response, 200);
    }
}
