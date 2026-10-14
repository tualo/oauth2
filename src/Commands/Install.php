<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2\Commands;

use Garden\Cli\Args;
use Garden\Cli\Cli;
use Tualo\Office\Basic\ICommandline;
use Tualo\Office\Basic\MYSQL\Database;
use Tualo\Office\Basic\Session;
use Tualo\Office\Basic\TualoApplication as App;

final class Install implements ICommandline
{
    public static function getCommandName(): string
    {
        return 'install-oauth2';
    }

    public static function setup(Cli $cli)
    {
        $cli->command(self::getCommandName())
            ->description('installs OAuth 2.0 and OpenID Connect schema in the session database');
    }

    public static function run(Args $args)
    {
        $_SERVER['REQUEST_URI'] = '';
        $_SERVER['REQUEST_METHOD'] = 'none';
        App::run();

        $session = App::get('session');
        if (!$session instanceof Session || !$session->db instanceof Database) {
            throw new \RuntimeException('The Tualo session database is not available.');
        }

        $sql = file_get_contents(__DIR__ . '/../sql/session/oauth2.sql');
        if ($sql === false) {
            throw new \RuntimeException('Unable to read the OAuth 2.0 schema file.');
        }

        foreach ($session->db->explode_by_delimiter($sql) as $statement) {
            if ($session->db->execute($statement) === false) {
                throw new \RuntimeException('Failed to install the OAuth 2.0 schema.');
            }
            $session->db->moreResults();
        }
    }
}
