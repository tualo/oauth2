<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Services;

/**
 * Checks granted access-token scopes on the resource side.
 *
 * Hierarchy: a parent scope covers all of its sub-scopes, i.e. a token
 * granted "ds.read" may read every table, while "ds.read:kunden" may only
 * read the table "kunden".
 */
final class ScopeGuard
{
    /**
     * @param string[] $grantedScopes
     */
    public static function covers(array $grantedScopes, string $requiredScope): bool
    {
        foreach ($grantedScopes as $granted) {
            if (!is_string($granted)) {
                continue;
            }
            if ($granted === $requiredScope) {
                return true;
            }
            // parent scope covers sub-scopes: "ds.read" covers "ds.read:kunden"
            if (str_starts_with($requiredScope, $granted . ':')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convenience check against the scopes stored in the tualo session by the
     * OAuth2 session middleware. Sessions without OAuth2 token (classic
     * logins) are not restricted by token scopes.
     */
    public static function sessionCovers(string $requiredScope): bool
    {
        if (!isset($_SESSION['tualoapplication']['oauth2'])) {
            return true;
        }
        $granted = $_SESSION['tualoapplication']['oauth2']['scopes'] ?? [];

        return is_array($granted) && self::covers($granted, $requiredScope);
    }
}
