<?php

namespace App\Services;

use App\Models\DispatchBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CsvIngestionService
{
    public function __construct(private DispatchPlannerService $planner) {}

    public function processCsv(string $filePath): void
    {
        $content = $this->readCsvContent($filePath);

        if ($content === null) {
            Log::warning('CSV ingestion failed: file not found or unreadable', ['file' => $filePath]);
            return;
        }

        $lines = explode("\n", $content);
        $header = str_getcsv(array_shift($lines));

        if (! is_array($header)) {
            Log::warning('CSV ingestion failed: invalid header', ['file' => $filePath]);
            return;
        }

        $orders = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $data = str_getcsv($line);
            if (! is_array($data) || count($data) !== count($header)) {
                Log::warning('CSV ingestion skipped invalid row', ['file' => $filePath, 'line' => $line]);
                continue;
            }

            $orders[] = array_combine($header, $data);
        }

        if (empty($orders)) {
            Log::warning('CSV ingestion found no valid orders', ['file' => $filePath]);
            return;
        }

        DB::beginTransaction();

        try {
            $batch = DispatchBatch::create();

            foreach ($orders as $orderData) {
                $batch->orders()->create([
                    'order_id' => $orderData['order_id'],
                    'placed_at' => $orderData['placed_at'],
                    'destination_address' => $orderData['destination_address'],
                    'destination_country' => $orderData['destination_country'],
                    'total_value' => $orderData['total_value'],
                    'total_value_currency' => $orderData['total_value_currency'],
                    'weight_grams' => $orderData['weight_grams'],
                    'payment_mode' => $orderData['payment_mode'],
                ]);
            }

            $this->planner->processBatch($batch);
            $this->deleteCsvFile($filePath);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CSV ingestion failed during processing', ['message' => $e->getMessage(), 'file' => $filePath]);
        }
    }

    public function pollForCsvs(): void
    {
        $files = $this->listInputFiles();

        foreach ($files as $file) {
            if (str_ends_with($file, '.csv')) {
                $this->processCsv($file);
            }
        }
    }

    private function readCsvContent(string $filePath): ?string
    {
        try {
            return Storage::disk('s3_input')->get($filePath);
        } catch (\Throwable $e) {
            Log::warning('S3 input read failed, falling back to local storage', ['file' => $filePath, 'error' => $e->getMessage()]);

            try {
                return Storage::disk('local')->get($filePath);
            } catch (\Throwable $e2) {
                Log::warning('Local input read failed', ['file' => $filePath, 'error' => $e2->getMessage()]);
                return null;
            }
        }
    }

    private function listInputFiles(): array
    {
        try {
            return Storage::disk('s3_input')->files('input/');
        } catch (\Throwable $e) {
            Log::warning('S3 input list failed, falling back to local storage', ['error' => $e->getMessage()]);

            try {
                return Storage::disk('local')->files('input/');
            } catch (\Throwable $e2) {
                Log::error('Local input list failed', ['error' => $e2->getMessage()]);
                return [];
            }
        }
    }

    private function deleteCsvFile(string $filePath): void
    {
        try {
            Storage::disk('s3_input')->delete($filePath);
        } catch (\Throwable $e) {
            try {
                Storage::disk('local')->delete($filePath);
            } catch (\Throwable $e2) {
                Log::warning('Failed to delete CSV file from both S3 and local storage', ['file' => $filePath]);
            }
        }
    }
}
