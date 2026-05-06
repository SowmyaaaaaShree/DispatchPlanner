<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

// Test database connection
try {
    \Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "Database connection OK\n";
} catch (\Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test creating a batch
try {
    $batch = \App\Models\DispatchBatch::create();
    echo "Batch created: ID " . $batch->id . "\n";
} catch (\Exception $e) {
    echo "Batch creation failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test creating an order
try {
    $order = $batch->orders()->create([
        'order_id' => 'TEST123',
        'placed_at' => now(),
        'destination_address' => 'Test Address',
        'destination_country' => 'IN',
        'total_value' => 100.0,
        'total_value_currency' => 'INR',
        'weight_grams' => 500,
        'payment_mode' => 'prepaid',
    ]);
    echo "Order created: ID " . $order->id . "\n";
} catch (\Exception $e) {
    echo "Order creation failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\nAll tests passed!\n";
