<?php

declare(strict_types=1);

namespace Tualo\Office\OAuth2;

use Garden\Cli\Cli;
use Tualo\Office\Basic\TualoApplication;
use Tualo\Office\Basic\MYSQL\Database as Database;
use Ramsey\Uuid\Uuid;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;



class Setup
{
    private static Setup $instance;

    private static ?Database $_db = null;
    private static array $ENV = [];


    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }



    public function db($db = null)
    {
        if (!is_null($db) && is_null(self::$_db)) {
            self::$_db = $db;
        }
        if (is_null(self::$_db)) {
            self::$_db = TualoApplication::get('session')->db;
        }

        return self::$_db;
    }

    public  function environment(): array
    {
        if (empty(self::$ENV)) {
            if (($issuer_url = TualoApplication::configuration('oauth2', 'issuer_url', false)) !== false) {
                self::$ENV['issuer_url'] = $issuer_url;
            }
            self::$ENV['refresh_token_ttl'] = TualoApplication::configuration('oauth2', 'refresh_token_ttl', 'P30D');
            self::$ENV['access_token_ttl'] = TualoApplication::configuration('oauth2', 'access_token_ttl', 'PT15M');
            self::$ENV['auth_token_ttl'] = TualoApplication::configuration('oauth2', 'auth_token_ttl', 'PT5M');

            $db = self::db();
            if (!$db instanceof Database) {
                throw new \RuntimeException('The OAuth environment database is unavailable.');
            }

            $data = $db->direct('SELECT id, val FROM oauth2_environments', []);
            if (!is_array($data)) {
                throw new \RuntimeException('Unable to load OAuth environment settings.');
            }
            foreach ($data as $row) {
                if (!isset($row['id'], $row['val'])) {
                    throw new \UnexpectedValueException('An OAuth environment row is malformed.');
                }
                self::$ENV[(string) $row['id']] = (string) $row['val'];
            }
        }
        if (empty(self::$ENV)) {
            self::$ENV = [];
        }
        return self::$ENV;
    }


    public function env(string $key): string
    {
        $env = self::environment();
        if (isset($env[$key])) {
            return $env[$key];
        }
        throw new \Exception('Environment ' . $key . ' not found!');
    }
}
