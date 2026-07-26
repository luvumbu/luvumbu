<?php
/**
 * Géocodage des villes via Nominatim (OpenStreetMap).
 *
 * Contraintes d'usage Nominatim respectées ici :
 *   - 1 requête par seconde maximum ;
 *   - User-Agent identifiable avec un contact ;
 *   - résultats mis en cache en base (on ne redemande jamais deux fois).
 *
 * Voir https://operations.osmfoundation.org/policies/nominatim/
 */

declare(strict_types=1);

final class Geocoder
{
    /** @var array<string,mixed> */
    private array $config;
    private float $lastRequestAt = 0.0;

    /** @param array<string,mixed> $config section 'geocoder' de la configuration */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Interroge le géocodeur pour une ville, en essayant plusieurs graphies.
     *
     * Les fusions de communes belges (« Beveren-Kruibeke-Zwijndrecht ») sont
     * souvent absentes d'OpenStreetMap sous leur nom composé : on retombe alors
     * sur la première commune du groupe.
     *
     * @return array{lat:float,lon:float,display_name:string,region:?string,postal_code:?string,query:string}|null
     */
    public function lookup(string $cityName, string $countryCode): ?array
    {
        foreach ($this->queryVariants($cityName) as $variant) {
            $result = $this->query($variant, $countryCode);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Géocode une adresse libre (rue, numéro, code postal, commune…).
     *
     * Contrairement à lookup(), aucune variante n'est essayée : découper une
     * adresse sur les traits d'union produirait des résultats absurdes.
     *
     * @return array{lat:float,lon:float,display_name:string,region:?string,postal_code:?string,query:string}|null
     */
    public function search(string $address, ?string $countryCode = null): ?array
    {
        return $this->query(trim($address), $countryCode);
    }

    /**
     * Graphies successives à tenter, de la plus précise à la plus large.
     *
     * @return list<string>
     */
    private function queryVariants(string $cityName): array
    {
        $name = trim($cityName);
        if ($name === '') {
            return [];
        }

        $variants = [$name];

        // « Ville (précision) » → « Ville »
        $withoutParens = trim(preg_replace('/\s*\([^)]*\)/u', '', $name) ?? $name);
        if ($withoutParens !== '' && $withoutParens !== $name) {
            $variants[] = $withoutParens;
        }

        // Fusion de communes : au moins deux segments d'au moins 3 lettres.
        $segments = preg_split('/\s*-\s*/u', $withoutParens ?: $name) ?: [];
        $segments = array_values(array_filter($segments, static fn(string $s): bool => mb_strlen(trim($s)) >= 3));
        if (count($segments) >= 2) {
            foreach ($segments as $segment) {
                $variants[] = trim($segment);
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return array{lat:float,lon:float,display_name:string,region:?string,postal_code:?string,query:string}|null
     */
    private function query(string $query, ?string $countryCode): ?array
    {
        if ($query === '') {
            return null;
        }

        $this->throttle();

        $params = [
            'q'               => $query,
            'format'          => 'jsonv2',
            'addressdetails'  => '1',
            'limit'           => '1',
            'accept-language' => (string) ($this->config['accept_language'] ?? 'fr'),
        ];

        if ($countryCode !== null && $countryCode !== '') {
            $params['countrycodes'] = strtolower($countryCode);
        }

        $url = $this->config['endpoint'] . '?' . http_build_query($params);

        $response = $this->request($url);
        if ($response === null) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || $data === []) {
            return null;
        }

        $hit = $data[0];
        if (!isset($hit['lat'], $hit['lon'])) {
            return null;
        }

        $address = $hit['address'] ?? [];

        return [
            'lat'          => (float) $hit['lat'],
            'lon'          => (float) $hit['lon'],
            'display_name' => (string) ($hit['display_name'] ?? $query),
            'region'       => $address['state'] ?? $address['province'] ?? $address['county'] ?? null,
            'postal_code'  => $address['postcode'] ?? null,
            'query'        => $query,
        ];
    }

    /**
     * Attend le délai minimal entre deux appels réseau.
     */
    private function throttle(): void
    {
        $delay = (float) ($this->config['delay_seconds'] ?? 1.1);
        $elapsed = microtime(true) - $this->lastRequestAt;
        if ($this->lastRequestAt > 0.0 && $elapsed < $delay) {
            usleep((int) (($delay - $elapsed) * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }

    private function request(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => (string) $this->config['user_agent'],
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            // XAMPP sous Windows n'a pas toujours de bundle CA à jour.
            CURLOPT_CAINFO         => self::caBundle(),
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            fwrite(STDERR, "  [réseau] {$error}\n");
            return null;
        }
        if ($status !== 200) {
            fwrite(STDERR, "  [HTTP {$status}] {$url}\n");
            return null;
        }

        return (string) $body;
    }

    /**
     * Chemin vers un bundle de certificats si XAMPP en fournit un.
     */
    private static function caBundle(): ?string
    {
        static $path = false;
        if ($path === false) {
            $path = null;
            foreach ([
                'C:/xampp/apache/bin/curl-ca-bundle.crt',
                'C:/xampp/php/extras/ssl/cacert.pem',
                ini_get('curl.cainfo') ?: '',
                ini_get('openssl.cafile') ?: '',
            ] as $candidate) {
                if ($candidate !== '' && is_file($candidate)) {
                    $path = $candidate;
                    break;
                }
            }
        }
        return $path;
    }
}
