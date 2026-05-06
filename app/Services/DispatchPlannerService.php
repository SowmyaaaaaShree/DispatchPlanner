<?php

namespace App\Services;

use App\Models\DispatchBatch;
use App\Models\Order;
use App\Models\DispatchRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class DispatchPlannerService
{
    public function __construct(
        private GeocodingService $geocoding,
        private HolidayService $holiday,
        private WeatherService $weather,
        private CurrencyService $currency,
    ) {}

    public function processBatch(DispatchBatch $batch): void
    {
        $batch->update(['status' => 'processing']);

        try {
            $orders = $batch->orders;

            foreach ($orders as $order) {
                $this->processOrder($order);
            }

            $this->createRuns($batch);

            $this->writeCsv($batch);

            $batch->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (\Exception $e) {
            $batch->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function processOrder(Order $order): void
    {
        // Geocode
        $geo = $this->geocoding->geocode($order->destination_address);
        if (!$geo) {
            $order->update(['status' => 'failed', 'error_message' => 'Geocoding failed']);
            return;
        }

        $order->update([
            'geocoded_city' => $geo['city'],
            'geocoded_lat' => $geo['lat'],
            'geocoded_lon' => $geo['lon'],
        ]);

        // Calculate dispatch date
        $placedAt = Carbon::parse($order->placed_at);
        $dispatchDate = $this->getNextWorkingDay($placedAt, $order->destination_country);

        // Check weather
        $weather = $this->weather->getWeather($geo['lat'], $geo['lon'], $dispatchDate);
        if ($weather && $weather['is_blocked']) {
            $dispatchDate = $this->getNextWorkingDay($dispatchDate->addDay(), $order->destination_country);
            $order->update(['is_deferred' => true, 'deferred_reason' => 'Weather blocked']);
        }

        $order->update(['planned_dispatch_date' => $dispatchDate]);

        // Currency conversion for COD
        if ($order->payment_mode === 'cod') {
            $localCurrency = $order->destination_country === 'IN' ? 'INR' : 'EUR';
            $converted = $this->currency->convert($order->total_value, $order->total_value_currency, $localCurrency, $dispatchDate);
            if ($converted !== null) {
                $order->update([
                    'invoiced_value_local' => $converted,
                    'invoiced_value_currency' => $localCurrency,
                ]);
            }
        }

        $order->update(['status' => 'processed']);
    }

    private function getNextWorkingDay(Carbon $date, string $country): Carbon
    {
        $date = $date->copy()->addDay(); // Next day after placed

        while ($date->isWeekend() || $this->holiday->isHoliday($country, $date)) {
            $date->addDay();
        }

        return $date;
    }

    private function createRuns(DispatchBatch $batch): void
    {
        $orders = $batch->orders()->where('status', 'processed')->get();

        // Group by city and date
        $grouped = [];
        foreach ($orders as $order) {
            $key = $order->geocoded_city . '|' . $order->planned_dispatch_date;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $order;
        }

        foreach ($grouped as $key => $orderList) {
            [$city, $date] = explode('|', $key);
            $country = $orderList[0]->destination_country;

            $run = DispatchRun::create([
                'dispatch_batch_id' => $batch->id,
                'city' => $city,
                'country' => $country,
                'dispatch_date' => $date,
                'total_invoiced_value_currency' => $country === 'IN' ? 'INR' : 'EUR',
            ]);

            $total = 0;
            foreach ($orderList as $order) {
                $run->orders()->attach($order->id);
                if ($order->payment_mode === 'cod') {
                    $total += $order->invoiced_value_local ?? 0;
                }
            }

            $run->update(['total_invoiced_value_local' => $total]);

            // Weather summary
            $lat = $orderList[0]->geocoded_lat;
            $lon = $orderList[0]->geocoded_lon;
            $weather = $this->weather->getWeather($lat, $lon, Carbon::parse($date));
            $run->update(['weather_summary' => $weather]);
        }
    }

    private function writeCsv(DispatchBatch $batch): void
    {
        $runs = $batch->dispatchRuns;

        $csv = "run_id,city,country,dispatch_date,order_id,placed_at,destination_address,total_value,total_value_currency,weight_grams,payment_mode,invoiced_value_local,invoiced_value_currency\n";

        foreach ($runs as $run) {
            foreach ($run->orders as $order) {
                $csv .= "{$run->id},{$run->city},{$run->country},{$run->dispatch_date},{$order->order_id},{$order->placed_at},\"{$order->destination_address}\",{$order->total_value},{$order->total_value_currency},{$order->weight_grams},{$order->payment_mode},{$order->invoiced_value_local},{$order->invoiced_value_currency}\n";
            }
        }

        // Use local storage (works with or without S3)
        try {
            Storage::disk('s3_output')->put("output/batch_{$batch->id}.csv", $csv);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to write CSV to S3 output', ['message' => $e->getMessage(), 'batch_id' => $batch->id]);

            try {
                Storage::disk('local')->put("output/batch_{$batch->id}.csv", $csv);
            } catch (\Throwable $e2) {
                \Illuminate\Support\Facades\Log::warning('Failed to write CSV to local storage', ['message' => $e2->getMessage(), 'batch_id' => $batch->id]);
            }
        }
    }
}
