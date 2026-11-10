<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The one gate in front of synthetic test data (quality engineering, k6).
 * Every rule is an allow-list and every rule must hold: the environment, the
 * application identity, the portal hosts, the connection and database, the
 * database server address and a marker written into the database itself
 * when an approved test database is created:
 *
 *   COMMENT ON DATABASE examis_perf IS 'external_link:synthetic';
 *
 * Nothing here can prove that a database holds no real people. The marker is
 * that promise: it is written only on an empty database created for
 * synthetic data, never on staging or production.
 */
final class SyntheticTarget
{
    /** `connection:database` pairs that may receive synthetic data. */
    public const DATABASES = ['sqlite::memory:', 'pgsql:examis_testing', 'pgsql:examis_perf'];

    public const ENVIRONMENTS = ['testing', 'local', 'perf'];

    /** Database servers on this machine only. */
    public const DATABASE_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /** Portal hosts that can never be reached from the internet. */
    public const PORTAL_HOST_SUFFIXES = ['.localhost', '.test'];

    public const APP_NAME = 'EXAMIS';

    public const MARKER = 'external_link:synthetic';

    /**
     * @throws RuntimeException when any rule fails
     */
    public static function assert(): void
    {
        $problems = self::check(self::facts());

        if ($problems !== []) {
            throw new RuntimeException('Synthetic data refused: '.implode('; ', $problems));
        }
    }

    /**
     * @return array{environment: string, production: bool, app_name: string, portal_hosts: list<string>, connection: string, database: string, host: string|null, marker: string|null}
     */
    public static function facts(): array
    {
        $connection = (string) config('database.default');
        $settings = (array) config("database.connections.{$connection}");

        return [
            'environment' => (string) App::environment(),
            'production' => App::isProduction(),
            'app_name' => (string) config('app.name'),
            'portal_hosts' => array_values(array_map('strval', (array) config('examis.portals'))),
            'connection' => $connection,
            'database' => (string) ($settings['database'] ?? ''),
            'host' => isset($settings['host']) ? (string) (is_array($settings['host']) ? implode(',', $settings['host']) : $settings['host']) : null,
            'marker' => self::marker($connection),
        ];
    }

    /**
     * Pure rules, so each refusal can be tested without touching a database.
     *
     * @param  array{environment: string, production: bool, app_name: string, portal_hosts: list<string>, connection: string, database: string, host: string|null, marker: string|null}  $facts
     * @return list<string>
     */
    public static function check(array $facts): array
    {
        $problems = [];
        $target = "{$facts['connection']}:{$facts['database']}";

        if ($facts['production'] || ! in_array($facts['environment'], self::ENVIRONMENTS, true)) {
            $problems[] = "environment [{$facts['environment']}] is not a test environment";
        }

        if ($facts['app_name'] !== self::APP_NAME) {
            $problems[] = "application [{$facts['app_name']}] is not ".self::APP_NAME;
        }

        if ($facts['portal_hosts'] === []) {
            $problems[] = 'portal hosts are not configured';
        }

        foreach ($facts['portal_hosts'] as $host) {
            if (! self::isPrivateHost($host)) {
                $problems[] = "portal host [{$host}] is reachable from outside this machine";
            }
        }

        if (! in_array($target, self::DATABASES, true)) {
            $problems[] = "database [{$target}] is not an approved test database";
        }

        if ($facts['connection'] !== 'sqlite') {
            if (! in_array($facts['host'], self::DATABASE_HOSTS, true)) {
                $problems[] = 'database server ['.($facts['host'] ?? 'none').'] is not on this machine';
            }

            if ($facts['marker'] !== self::MARKER) {
                $problems[] = "database [{$facts['database']}] is not marked '".self::MARKER."'";
            }
        }

        return $problems;
    }

    private static function isPrivateHost(string $host): bool
    {
        $host = strtolower($host);

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return $host !== '';
        }

        foreach (self::PORTAL_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private static function marker(string $connection): ?string
    {
        if ($connection !== 'pgsql') {
            return null;
        }

        try {
            $value = DB::connection($connection)->scalar(
                "select shobj_description(oid, 'pg_database') from pg_database where datname = current_database()",
            );
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
