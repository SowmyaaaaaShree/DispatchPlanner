<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WeatherService
{
    public function getWeather(float $lat, float $lon, Carbon $date): ?array
    {
        $cacheKey = sprintf('weather:%s:%s:%s', $lat, $lon, $date->toDateString());

        return Cache::remember($cacheKey, 3600, function () use ($lat, $lon, $date) {
            try {
                $response = Http::timeout(10)
                    ->retry(3, 200)
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => $lat,
                        'longitude' => $lon,
                        'daily' => 'precipitation_sum,temperature_2m_max,temperature_2m_min',
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->toDateString(),
                        'timezone' => 'UTC',
                    ]);

                if (! $response->successful()) {
                    Log::warning('Open-Meteo request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'lat' => $lat,
                        'lon' => $lon,
                        'date' => $date->toDateString(),
                    ]);

                    return null;
                }

                $data = $response->json();
                $daily = $data['daily'] ?? [];

                $precip = $this->extractDailyValue($daily, 'precipitation_sum');
                $tempMax = $this->extractDailyValue($daily, 'temperature_2m_max');
                $tempMin = $this->extractDailyValue($daily, 'temperature_2m_min');

                if ($precip === null || $tempMax === null || $tempMin === null) {
                    Log::warning('Open-Meteo returned unexpected payload', [
                        'daily' => $daily,
                        'lat' => $lat,
                        'lon' => $lon,
                        'date' => $date->toDateString(),
                    ]);

                    return null;
                }

                return [
                    'precipitation_mm' => $precip,
                    'temperature_max_c' => $tempMax,
                    'temperature_min_c' => $tempMin,
                    'is_blocked' => $precip > 20 || $tempMax > 45 || $tempMin < -10,
                ];
            } catch (\Throwable $e) {
                Log::warning('Open-Meteo request failed', [
                    'message' => $e->getMessage(),
                    'lat' => $lat,
                    'lon' => $lon,
                    'date' => $date->toDateString(),
                ]);
            }

            return null;
        });
    }

    private function extractDailyValue(array $daily, string $key): ?float
    {
        $value = data_get($daily, $key);

        if (is_array($value) && count($value) > 0) {
            return is_numeric($value[0]) ? (float) $value[0] : null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}