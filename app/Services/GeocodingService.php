<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
            ]);

            if ($response->successful() && !empty($response->json())) {
                $data = $response->json()[0];
                $city = $this->parseCity($data['display_name']);
                return [
                    'city' => $city,
                    'lat' => $data['lat'],
                    'lon' => $data['lon'],
                ];
            }
        } catch (\Exception $e) {
            // Log error
        }

        return null;
    }

    private function parseCity(string $displayName): ?string
    {
        $parts = explode(',', $displayName);
        return trim($parts[0]);
    }
}