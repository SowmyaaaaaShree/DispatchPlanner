<?php

namespace Tests\Feature;

use App\Models\DispatchBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DispatchBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_batch_successfully()
    {
        Storage::fake('s3');

        // Fake HTTP calls
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Mumbai, India', 'lat' => 19.0760, 'lon' => 72.8777]], 200),
            'date.nager.at/*' => Http::response([], 200),
            'api.open-meteo.com/*' => Http::response(['daily' => ['precipitation_sum' => [5], 'temperature_2m_max' => [30], 'temperature_2m_min' => [20]]], 200),
            'api.frankfurter.app/*' => Http::response(['rates' => ['INR' => 1.0]], 200),
        ]);

        $data = [
            'orders' => [
                [
                    'order_id' => '123',
                    'placed_at' => '2023-05-01T10:00:00Z',
                    'destination_address' => 'Mumbai, India',
                    'destination_country' => 'IN',
                    'total_value' => 100.0,
                    'total_value_currency' => 'INR',
                    'weight_grams' => 500,
                    'payment_mode' => 'prepaid',
                ]
            ]
        ];

        $response = $this->postJson('/api/dispatch-batches', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dispatch_batches', ['id' => 1]);
    }

    public function test_writes_output_csv_to_s3_output_bucket()
    {
        Storage::fake('s3_output');
        Storage::fake('s3');

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Mumbai, India', 'lat' => 19.0760, 'lon' => 72.8777]], 200),
            'date.nager.at/*' => Http::response([], 200),
            'api.open-meteo.com/*' => Http::response(['daily' => ['precipitation_sum' => [5], 'temperature_2m_max' => [30], 'temperature_2m_min' => [20]]], 200),
            'api.frankfurter.app/*' => Http::response(['rates' => ['INR' => 1.0]], 200),
        ]);

        $data = [
            'orders' => [
                [
                    'order_id' => '123',
                    'placed_at' => '2023-05-01T10:00:00Z',
                    'destination_address' => 'Mumbai, India',
                    'destination_country' => 'IN',
                    'total_value' => 100.0,
                    'total_value_currency' => 'INR',
                    'weight_grams' => 500,
                    'payment_mode' => 'prepaid',
                ]
            ]
        ];

        $response = $this->postJson('/api/dispatch-batches', $data);

        $response->assertStatus(201);
        Storage::disk('s3_output')->assertExists('output/batch_1.csv');
    }

    public function test_handles_holiday_api_failure_safely()
    {
        Storage::fake('s3');

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Mumbai, India', 'lat' => 19.0760, 'lon' => 72.8777]], 200),
            'date.nager.at/*' => Http::response([], 500),
            'api.open-meteo.com/*' => Http::response(['daily' => ['precipitation_sum' => [5], 'temperature_2m_max' => [30], 'temperature_2m_min' => [20]]], 200),
            'api.frankfurter.app/*' => Http::response(['rates' => ['INR' => 1.0]], 200),
        ]);

        $data = [
            'orders' => [
                [
                    'order_id' => '123',
                    'placed_at' => '2023-05-01T10:00:00Z',
                    'destination_address' => 'Mumbai, India',
                    'destination_country' => 'IN',
                    'total_value' => 100.0,
                    'total_value_currency' => 'INR',
                    'weight_grams' => 500,
                    'payment_mode' => 'prepaid',
                ]
            ]
        ];

        $response = $this->postJson('/api/dispatch-batches', $data);

        $response->assertStatus(201);
        $batch = DispatchBatch::first();
        $this->assertEquals('processed', $batch->orders->first()->status);
    }

    public function test_handles_currency_api_fallback_to_latest_rates()
    {
        Storage::fake('s3');

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['display_name' => 'Mumbai, India', 'lat' => 19.0760, 'lon' => 72.8777]], 200),
            'date.nager.at/*' => Http::response([], 200),
            'api.open-meteo.com/*' => Http::response(['daily' => ['precipitation_sum' => [5], 'temperature_2m_max' => [30], 'temperature_2m_min' => [20]]], 200),
            'api.frankfurter.app/2023-05-02*' => Http::response([], 500),
            'api.frankfurter.app/latest*' => Http::response(['rates' => ['INR' => 1.0]], 200),
        ]);

        $data = [
            'orders' => [
                [
                    'order_id' => '123',
                    'placed_at' => '2023-05-01T10:00:00Z',
                    'destination_address' => 'Mumbai, India',
                    'destination_country' => 'IN',
                    'total_value' => 100.0,
                    'total_value_currency' => 'USD',
                    'weight_grams' => 500,
                    'payment_mode' => 'cod',
                ]
            ]
        ];

        $response = $this->postJson('/api/dispatch-batches', $data);

        $response->assertStatus(201);
        $order = DispatchBatch::first()->orders->first();

        $this->assertEquals('processed', $order->status);
        $this->assertEquals(100.0, $order->invoiced_value_local);
        $this->assertEquals('INR', $order->invoiced_value_currency);
    }

    public function test_handles_geocoding_failure()
    {
        Storage::fake('s3');

        // Fake geocoding failure
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 404),
        ]);

        $data = [
            'orders' => [
                [
                    'order_id' => '123',
                    'placed_at' => '2023-05-01T10:00:00Z',
                    'destination_address' => 'Invalid Address',
                    'destination_country' => 'IN',
                    'total_value' => 100.0,
                    'total_value_currency' => 'INR',
                    'weight_grams' => 500,
                    'payment_mode' => 'prepaid',
                ]
            ]
        ];

        $response = $this->postJson('/api/dispatch-batches', $data);

        $response->assertStatus(201);
        $batch = DispatchBatch::first();
        $this->assertEquals('failed', $batch->orders->first()->status);
    }

    public function test_health_check()
    {
        $response = $this->get('/api/healthz');

        $response->assertStatus(200)->assertJson(['status' => 'ok']);
    }
}
