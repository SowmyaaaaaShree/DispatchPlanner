<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HolidayService
{
    public function isHoliday(string $country, Carbon $date): bool
    {
        $year = $date->year;
        $cacheKey = sprintf('holidays:%s:%s', $country, $year);

        return Cache::remember($cacheKey, 86400, function () use ($country, $date, $year) {
            try {
                $response = Http::timeout(10)
                    ->retry(3, 200)
                    ->get("https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}");

                if (! $response->successful()) {
                    Log::warning('Nager.Date request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'country' => $country,
                        'year' => $year,
                    ]);

                    return false;
                }

                $holidays = $this->resolveHolidaysArray($response->json());

                return collect($holidays)->contains(function ($holiday) use ($date) {
                    return data_get($holiday, 'date') === $date->toDateString();
                });
            } catch (\Throwable $e) {
                Log::warning('Nager.Date request failed', [
                    'message' => $e->getMessage(),
                    'country' => $country,
                    'year' => $year,
                ]);
            }

            return false;
        });
    }

    private function resolveHolidaysArray($holidays): array
    {
        if (is_array($holidays) && isset($holidays['publicHolidays'])) {
            return $holidays['publicHolidays'];
        }

        if (is_array($holidays) && isset($holidays['data'])) {
            return $holidays['data'];
        }

        return is_array($holidays) ? $holidays : [];
    }
}