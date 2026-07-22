<?php

namespace App\Services;

/**
 * Géocodage via Nominatim (OpenStreetMap) : adresse -> coordonnées GPS.
 * Best-effort : renvoie null en cas d'échec (le signalement reste valide sans coordonnées).
 */
final class GeocodingService
{
    /**
     * @return array{lat:float,lng:float,display:string}|null
     */
    public static function geocode(string $address, ?string $city = null, ?string $postal = null, string $country = 'France'): ?array
    {
        $query = trim(implode(', ', array_filter([$address, $postal, $city, $country])));
        if ($query === '') {
            return null;
        }

        $endpoint = rtrim((string) config('geocoding.endpoint'), '/') . '/search';
        $url = $endpoint . '?' . http_build_query([
            'q'      => $query,
            'format' => 'jsonv2',
            'limit'  => 1,
            'addressdetails' => 0,
        ]);

        $response = self::httpGet($url);
        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return null;
        }

        return [
            'lat'     => (float) $data[0]['lat'],
            'lng'     => (float) $data[0]['lon'],
            'display' => (string) ($data[0]['display_name'] ?? $query),
        ];
    }

    /**
     * Recherche d'adresses (autocomplétion). Retourne jusqu'à $limit suggestions structurées.
     * @return array<int,array<string,mixed>>
     */
    public static function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }

        $endpoint = rtrim((string) config('geocoding.endpoint'), '/') . '/search';
        $url = $endpoint . '?' . http_build_query([
            'q'              => $query,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'limit'          => max(1, min(10, $limit)),
            'countrycodes'   => 'fr',
            'accept-language' => 'fr',
        ]);

        $response = self::httpGet($url);
        if ($response === null) {
            return [];
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $item) {
            $a = $item['address'] ?? [];
            $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? ($a['county'] ?? '');
            $road = trim(($a['house_number'] ?? '') . ' ' . ($a['road'] ?? ''));
            $out[] = [
                'label'       => $item['display_name'] ?? $query,
                'lat'         => isset($item['lat']) ? (float) $item['lat'] : null,
                'lng'         => isset($item['lon']) ? (float) $item['lon'] : null,
                'address'     => $road ?: ($a['road'] ?? ''),
                'city'        => $city,
                'postal_code' => $a['postcode'] ?? '',
                'department'  => $a['county'] ?? '',
                'region'      => $a['state'] ?? '',
                'country'     => $a['country'] ?? 'France',
            ];
        }
        return $out;
    }

    private static function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => (string) config('geocoding.user_agent', 'BadPlace/1.0'),
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: fr'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            logger('Géocodage échoué', 'WARN', ['url' => $url, 'code' => $code]);
            return null;
        }
        return (string) $body;
    }
}
