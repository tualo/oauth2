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

    private static Database $_db;
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
            self::$_db = TualoApplication::get('session')->getDB();
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
            try {
                if (!is_null($db)) {
                    $data = $db->direct('select id,val from oauth2_environments', []);
                    foreach ($data as $d) {
                        self::$ENV[$d['id']] = $d['val'];
                    }
                } else {
                    echo 'Error ';
                }
            } catch (\Exception $e) {
                echo 'Error fetching oauth2 environments: ' . $e->getMessage() . PHP_EOL;
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
