<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CurrencyService
{
    public function convert(float $amount, string $from, string $to, Carbon $date): ?float
    {
        if (strtoupper($from) === strtoupper($to)) {
            return $amount;
        }

        $from = strtoupper($from);
        $to = strtoupper($to);
        $dateStr = $date->toDateString();
        $cacheKey = sprintf('currency:%s:%s:%s', $from, $to, $dateStr);

        return Cache::remember($cacheKey, 86400, function () use ($amount, $from, $to, $dateStr) {
            $rate = $this->getRateForDate($from, $to, $dateStr);

            if ($rate === null) {
                $rate = $this->getRateForLatest($from, $to);
            }

            if ($rate === null) {
                return null;
            }

            return $amount * $rate;
        });
    }

    private function getRateForDate(string $from, string $to, string $dateStr): ?float
    {
        try {
            $response = Http::timeout(10)
                ->retry(3, 200)
                ->get("https://api.frankfurter.app/{$dateStr}", [
                    'from' => $from,
                    'to' => $to,
                ]);

            if ($response->successful()) {
                return $this->extractRate($response->json(), $to);
            }

            Log::warning('Frankfurter date-based request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'from' => $from,
                'to' => $to,
                'date' => $dateStr,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Frankfurter date-based request failed', [
                'message' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
                'date' => $dateStr,
            ]);
        }

        return null;
    }

    private function getRateForLatest(string $from, string $to): ?float
    {
        try {
            $response = Http::timeout(10)
                ->retry(3, 200)
                ->get('https://api.frankfurter.app/latest', [
                    'from' => $from,
                    'to' => $to,
                ]);

            if ($response->successful()) {
                return $this->extractRate($response->json(), $to);
            }

            Log::warning('Frankfurter latest request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'from' => $from,
                'to' => $to,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Frankfurter latest request failed', [
                'message' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
            ]);
        }

        return null;
    }

    private function extractRate(array $data, string $to): ?float
    {
        $rate = data_get($data, "rates.{$to}");

        if (is_numeric($rate)) {
            return (float) $rate;
        }

        return null;
    }
}