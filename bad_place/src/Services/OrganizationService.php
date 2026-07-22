<?php

namespace App\Services;

use App\Core\Database;

/**
 * Gestion des entités signalées (lieux, entreprises, marques, services).
 * Rattache les signalements à une entité réutilisable (dédup) et calcule le niveau d'activité.
 */
final class OrganizationService
{
    /**
     * Retrouve une entité existante (dédup) ou la crée. Retourne son id.
     *
     * @param array $d name, type, brand_name, category_id, address, city, postal_code,
     *                 department, region, country, country_code, latitude, longitude, website
     */
    public static function findOrCreate(array $d): int
    {
        $name = trim((string) ($d['name'] ?? ''));
        $slug = str_slug($name);
        $city = trim((string) ($d['city'] ?? ''));
        $postal = trim((string) ($d['postal_code'] ?? ''));
        $type = $d['type'] ?? 'place';

        // Dédup : même slug + (même code postal OU même ville) OU marque identique.
        $existing = Database::selectOne(
            "SELECT id FROM organizations
             WHERE slug = ?
               AND ( (postal_code <> '' AND postal_code = ?)
                     OR (city <> '' AND city = ?)
                     OR (type = 'brand' AND ? = 'brand') )
             LIMIT 1",
            [$slug, $postal, $city, $type]
        );
        if ($existing) {
            self::enrich((int) $existing['id'], $d);
            return (int) $existing['id'];
        }

        return Database::insert(
            'INSERT INTO organizations
                (uuid, name, slug, type, brand_name, category_id, address, city, postal_code,
                 department, region, country, country_code, latitude, longitude, website)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                str_uuid(), $name, $slug, $type,
                self::nz($d['brand_name'] ?? null),
                $d['category_id'] ?? null,
                self::nz($d['address'] ?? null), self::nz($city), self::nz($postal),
                self::nz($d['department'] ?? null), self::nz($d['region'] ?? null),
                $d['country'] ?? 'France', strtoupper((string) ($d['country_code'] ?? 'FR')),
                $d['latitude'] ?? null, $d['longitude'] ?? null,
                self::nz($d['website'] ?? null),
            ]
        );
    }

    /** Complète une entité existante avec des coordonnées si elle n'en avait pas. */
    private static function enrich(int $id, array $d): void
    {
        if (!empty($d['latitude']) && !empty($d['longitude'])) {
            Database::execute(
                'UPDATE organizations SET latitude = COALESCE(latitude, ?), longitude = COALESCE(longitude, ?) WHERE id = ?',
                [$d['latitude'], $d['longitude'], $id]
            );
        }
    }

    /**
     * Recalcule le nombre de signalements publiés et le niveau d'activité (🟢🟠🔴).
     */
    public static function recomputeActivity(int $organizationId): void
    {
        $count = (int) (Database::selectOne(
            "SELECT COUNT(*) c FROM reports WHERE organization_id = ? AND status = 'published'",
            [$organizationId]
        )['c'] ?? 0);

        $medium = (int) config('reports.activity_medium_at', 3);
        $high   = (int) config('reports.activity_high_at', 10);
        $level  = $count >= $high ? 'high' : ($count >= $medium ? 'medium' : 'low');

        Database::execute(
            'UPDATE organizations SET reports_count = ?, activity_level = ? WHERE id = ?',
            [$count, $level, $organizationId]
        );
    }

    private static function nz(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        return ($v === null || $v === '') ? null : $v;
    }
}
