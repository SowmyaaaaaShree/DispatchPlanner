<?php

namespace App\Console\Commands;

use App\Services\CsvIngestionService;
use Illuminate\Console\Command;

class ProcessDailyBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dispatch:process-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily batches from S3 CSVs';

    public function __construct(private CsvIngestionService $csvService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->csvService->pollForCsvs();
        $this->info('Processed daily batches');
    }
}
