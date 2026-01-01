<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Services\ImportService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportBooksJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importLogId,
        public string $filePath
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImportService $importService): void
    {
        $importLog = ImportLog::find($this->importLogId);

        if (! $importLog) {
            Log::error('ImportLog not found', ['import_log_id' => $this->importLogId]);

            return;
        }

        try {
            // Update status to processing
            $importLog->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Get file from storage
            $fileContent = Storage::disk('local')->get($this->filePath);
            $tempPath = sys_get_temp_dir().'/'.basename($this->filePath);
            file_put_contents($tempPath, $fileContent);

            // Create UploadedFile instance
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                basename($this->filePath),
                'text/csv',
                null,
                true
            );

            // Process import
            $result = $importService->importFromCsv($uploadedFile);

            // Update import log with results
            $importLog->update([
                'status' => 'completed',
                'imported_count' => $result['imported_count'],
                'failed_count' => $result['failed_count'],
                'errors' => $result['errors'],
                'completed_at' => now(),
            ]);

            // Clean up temp file
            @unlink($tempPath);

            // Clean up storage file
            Storage::disk('local')->delete($this->filePath);

            Log::info('Import completed successfully', [
                'import_log_id' => $this->importLogId,
                'imported' => $result['imported_count'],
                'failed' => $result['failed_count'],
            ]);
        } catch (Exception $e) {
            $importLog->update([
                'status' => 'failed',
                'errors' => [['error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);

            Log::error('Import failed', [
                'import_log_id' => $this->importLogId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
