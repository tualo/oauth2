<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

use Tualo\Office\OAuth2\Setup;

/**
 * Shared German scope descriptions, including validity hints based on the
 * configured token TTLs, for consent and device-verification UIs.
 */
final class ScopeCatalog
{
    /**
     * @param string[] $scopeIdentifiers
     */
    public static function describe(array $scopeIdentifiers, Setup $setup): array
    {
        $accessTtl = self::formatIntervalGerman($setup->envWithDefault('access_token_ttl', 'PT15M'));
        $refreshTtl = self::formatIntervalGerman($setup->envWithDefault('refresh_token_ttl', 'P30D'));

        $scopeDescriptions = [
            'openid' => [
                'name' => 'Anmeldung',
                'description' => 'Dich anhand einer eindeutigen Benutzerkennung erkennen.',
                'duration' => 'Gültigkeit: nur solange der erteilte Zugriff besteht – '
                    . 'ein Zugriff läuft spätestens nach ' . $accessTtl . ' automatisch ab.',
            ],
            'profile' => [
                'name' => 'Profil',
                'description' => 'Deinen Benutzernamen und Anzeigenamen lesen.',
                'duration' => 'Gültigkeit: nur solange der erteilte Zugriff besteht '
                    . '(maximal ' . $accessTtl . ' je Zugriff).',
            ],
            'offline_access' => [
                'name' => 'Dauerhafter Zugriff',
                'description' => 'Die Zugriffsberechtigung erneuern, auch wenn du nicht mehr aktiv angemeldet bist.',
                'duration' => 'Gültigkeit: dauerhaft, bis du den Zugriff widerrufst – '
                    . 'die Anwendung kann den Zugriff ohne erneute Anmeldung selbstständig '
                    . 'immer wieder erneuern (jeweils um bis zu ' . $refreshTtl . ').',
            ],
        ];

        // parameterized scopes: "<base>:<param>", e.g. "ds.read:kunden"
        $parameterizedDescriptions = [
            'ds.read' => [
                'name' => 'Datenzugriff (lesend)',
                'description' => static fn (string $param): string => 'Die Tabelle „' . $param . '“ lesen.',
            ],
            'ds.write' => [
                'name' => 'Datenzugriff (schreibend)',
                'description' => static fn (string $param): string => 'Die Tabelle „' . $param . '“ ändern.',
            ],
        ];

        $requestedAccess = [];
        foreach ($scopeIdentifiers as $identifier) {
            $description = $scopeDescriptions[$identifier] ?? null;

            if ($description === null && str_contains($identifier, ':')) {
                [$base, $param] = explode(':', $identifier, 2);
                if (isset($parameterizedDescriptions[$base]) && $param !== '') {
                    $description = [
                        'name' => $parameterizedDescriptions[$base]['name'],
                        'description' => $parameterizedDescriptions[$base]['description']($param),
                        'duration' => 'Gültigkeit: für die Dauer des erteilten Zugriffs '
                            . '(maximal ' . $accessTtl . ' je Zugriff).',
                    ];
                }
            }

            $description ??= [
                'name' => $identifier,
                'description' => 'Anwendungsspezifische Berechtigung: ' . $identifier,
                'duration' => 'Gültigkeit: für die Dauer des erteilten Zugriffs '
                    . '(maximal ' . $accessTtl . ' je Zugriff).',
            ];
            $requestedAccess[] = [
                'type' => 'string',
                'id' => $identifier,
                'name' => $description['name'],
                'description' => $description['description'],
                'duration' => $description['duration'],
            ];
        }

        return $requestedAccess;
    }

    public static function formatIntervalGerman(string $isoDuration): string
    {
        try {
            $interval = new \DateInterval($isoDuration);
        } catch (\Exception) {
            return $isoDuration;
        }

        $units = [
            [$interval->y, 'Jahr', 'Jahre'],
            [$interval->m, 'Monat', 'Monate'],
            [$interval->d, 'Tag', 'Tage'],
            [$interval->h, 'Stunde', 'Stunden'],
            [$interval->i, 'Minute', 'Minuten'],
            [$interval->s, 'Sekunde', 'Sekunden'],
        ];

        $parts = [];
        foreach ($units as [$value, $singular, $plural]) {
            if ($value > 0) {
                $parts[] = $value . ' ' . ($value === 1 ? $singular : $plural);
            }
        }

        return $parts === [] ? $isoDuration : implode(' ', $parts);
    }
}
