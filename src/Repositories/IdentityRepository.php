<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Repositories;

use OpenIDConnectServer\Repositories\IdentityProviderInterface;
use Ramsey\Uuid\Uuid;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\OAuth2\Entities\OAuthUser;

final class IdentityRepository implements IdentityProviderInterface
{
    public function __construct(private readonly Database $database)
    {
    }

    public function getOrCreateSubjectId(string $login): string
    {
        if (trim($login) === '') {
            throw new \InvalidArgumentException('The Tualo login must not be empty.');
        }

        $rows = $this->database->direct(
            'SELECT subject_id FROM oauth2_subjects WHERE login = {login}',
            ['login' => $login]
        );
        if ($rows === false) {
            throw new \RuntimeException('Unable to look up the OIDC subject identifier.');
        }
        if (isset($rows[0]['subject_id'])) {
            return (string) $rows[0]['subject_id'];
        }

        $inserted = $this->database->direct(
            'INSERT INTO oauth2_subjects (subject_id, login) VALUES ({subject_id}, {login}) '
                . 'ON DUPLICATE KEY UPDATE subject_id = subject_id',
            [
                'subject_id' => Uuid::uuid4()->toString(),
                'login' => $login,
            ]
        );
        if ($inserted === false) {
            throw new \RuntimeException('Unable to persist the OIDC subject identifier.');
        }

        $rows = $this->database->direct(
            'SELECT subject_id FROM oauth2_subjects WHERE login = {login}',
            ['login' => $login]
        );
        if ($rows === false || !isset($rows[0]['subject_id'])) {
            throw new \RuntimeException('Unable to create the OIDC subject identifier.');
        }

        return (string) $rows[0]['subject_id'];
    }

    public function getUserEntityByIdentifier($identifier): OAuthUser
    {
        if (!is_string($identifier) || $identifier === '') {
            throw new \InvalidArgumentException('The OIDC subject identifier must be a non-empty string.');
        }

        $rows = $this->database->direct(
            'SELECT macc_users.login, '
                . 'TRIM(CONCAT(COALESCE(loginnamen.vorname, \'\'), \' \', COALESCE(loginnamen.nachname, \'\'))) AS display_name '
                . 'FROM oauth2_subjects '
                . 'JOIN macc_users ON macc_users.login = oauth2_subjects.login '
                . 'LEFT JOIN loginnamen ON loginnamen.login = macc_users.login '
                . 'WHERE oauth2_subjects.subject_id = {subject_id}',
            ['subject_id' => $identifier]
        );
        if ($rows === false || !isset($rows[0]['login'])) {
            throw new \RuntimeException('The OIDC subject does not identify an active Tualo user.');
        }

        return new OAuthUser(
            $identifier,
            (string) $rows[0]['login'],
            (string) ($rows[0]['display_name'] ?? '')
        );
    }
}
