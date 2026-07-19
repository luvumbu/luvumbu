<?php
// Gestion des clés de synchronisation a usage unique (stockees dans config/sync_keys.json).
// Le fichier n'est jamais commité ni déployé (gitignore + exclusion FTP).

function _sync_keys_path() {
    return __DIR__ . '/../config/sync_keys.json';
}

function _sync_keys_read() {
    $f = _sync_keys_path();
    if (!file_exists($f)) return [];
    $raw = @file_get_contents($f);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function _sync_keys_write(array $keys) {
    $f = _sync_keys_path();
    @file_put_contents($f, json_encode($keys, JSON_PRETTY_PRINT));
    @chmod($f, 0600);
}

function _sync_key_is_valid(array $k, int $now): bool {
    // Une cle est valide si non consommee ET (sans expiration OU pas encore expiree).
    if (!empty($k['used_at'])) return false;
    $exp = (int)($k['expires_at'] ?? 0);
    if ($exp === 0) return true;        // 0 = sans expiration
    return $exp > $now;
}

function sync_key_generate(int $ttlSeconds = 3600): array {
    // $ttlSeconds = 0 -> cle permanente (sans expiration).
    $keys = _sync_keys_read();
    $now  = time();
    $keys = array_values(array_filter($keys, fn($k) => _sync_key_is_valid($k, $now)));
    $token = bin2hex(random_bytes(32));
    $entry = [
        'token'      => $token,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => $ttlSeconds <= 0 ? 0 : ($now + $ttlSeconds),
        'used_at'    => null,
        'permanent'  => $ttlSeconds <= 0,
    ];
    $keys[] = $entry;
    _sync_keys_write($keys);
    return $entry;
}

function sync_key_consume(string $token): bool {
    if ($token === '') return false;
    $keys = _sync_keys_read();
    $now  = time();
    foreach ($keys as $i => $k) {
        if (!_sync_key_is_valid($k, $now)) continue;
        if (hash_equals((string)$k['token'], $token)) {
            // Cle permanente : on ne la consomme jamais, elle reste utilisable.
            if (!empty($k['permanent']) || (int)($k['expires_at'] ?? 0) === 0) {
                return true;
            }
            $keys[$i]['used_at'] = date('Y-m-d H:i:s');
            _sync_keys_write($keys);
            return true;
        }
    }
    return false;
}

function sync_key_check(string $token): bool {
    // Verifie qu'une cle est valide SANS la consommer. Pour les endpoints read-only.
    if ($token === '') return false;
    $keys = _sync_keys_read();
    $now  = time();
    foreach ($keys as $k) {
        if (!_sync_key_is_valid($k, $now)) continue;
        if (hash_equals((string)$k['token'], $token)) return true;
    }
    return false;
}

function sync_key_find_by_prefix(string $prefix): ?array {
    // Le prefixe est deja affiche publiquement dans l'admin : il n'identifie pas, il ne protege pas.
    // C'est la verification du mot de passe cote appelant qui autorise la revelation.
    $prefix = trim($prefix);
    if (strlen($prefix) < 8) return null;
    foreach (_sync_keys_read() as $k) {
        $token = (string)($k['token'] ?? '');
        if (strncmp($token, $prefix, strlen($prefix)) === 0) return $k;
    }
    return null;
}

function sync_keys_active(): array {
    $keys = _sync_keys_read();
    $now  = time();
    return array_values(array_filter($keys, fn($k) => _sync_key_is_valid($k, $now)));
}

function sync_keys_history(int $limit = 20): array {
    $keys = _sync_keys_read();
    usort($keys, function ($a, $b) {
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });
    return array_slice($keys, 0, $limit);
}
