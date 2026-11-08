<?php

namespace App\Services\Database;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * The raw table editor behind the staff "Students database" screen: lists the
 * application's tables and reads, inserts, updates and deletes their rows
 * through the query builder, so the database's own constraints still apply.
 *
 * Three limits keep it from becoming a way around the rest of the system:
 * infrastructure tables (sessions, cache, queue, reset tokens) are hidden;
 * history and access-control tables are read-only, because history is
 * append-only and roles and permissions change on the Users page; and secret
 * columns (password hashes, tokens, codes) are never shown or written.
 */
class TableEditor
{
    /** @var list<string> */
    private const HIDDEN = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'staff_sessions', 'password_reset_tokens',
    ];

    /** @var list<string> */
    private const READ_ONLY = [
        'audit_events', 'application_events',
        'role_assignments', 'staff_permission_overrides', 'staff_invitations', 'passkeys', 'trusted_devices',
    ];

    /**
     * Rows here are added elsewhere: staff on the Users page, students by registering.
     *
     * @var list<string>
     */
    private const NO_INSERT = ['users'];

    private const SECRET_PATTERN = '/(password|secret|token|recovery_codes|access_code|credential|public_key)/';

    /** @var array<string, list<array{name: string, type: string, kind: string, nullable: bool, has_default: bool, auto: bool, secret: bool, key: bool}>> */
    private array $columns = [];

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $names = [];

        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];

            if (in_array($table['schema'] ?? null, [null, 'public', 'main'], true)
                && ! in_array($name, self::HIDDEN, true) && ! str_starts_with($name, 'sqlite_')) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    public function exists(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }

    public function isWritable(string $table): bool
    {
        return ! in_array($table, self::READ_ONLY, true) && $this->primaryKey($table) !== [];
    }

    public function canInsert(string $table): bool
    {
        return $this->isWritable($table) && ! in_array($table, self::NO_INSERT, true);
    }

    public function isSecret(string $column): bool
    {
        return preg_match(self::SECRET_PATTERN, $column) === 1;
    }

    /**
     * @return list<array{name: string, type: string, kind: string, nullable: bool, has_default: bool, auto: bool, secret: bool, key: bool}>
     */
    public function columns(string $table): array
    {
        $key = $this->primaryKey($table);

        return $this->columns[$table] ??= array_map(function (array $column) use ($key): array {
            $name = (string) $column['name'];

            return [
                'name' => $name,
                'type' => (string) $column['type'],
                'kind' => $this->kind((string) $column['type_name'], (string) $column['type']),
                'nullable' => (bool) $column['nullable'],
                'has_default' => $column['default'] !== null,
                'auto' => (bool) $column['auto_increment'] || ($column['generation'] ?? null) !== null,
                'secret' => $this->isSecret($name),
                'key' => in_array($name, $key, true),
            ];
        }, Schema::getColumns($table));
    }

    /**
     * @return list<string>
     */
    public function primaryKey(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                return $index['columns'];
            }
        }

        return [];
    }

    public function count(string $table): int
    {
        return DB::table($table)->count();
    }

    /**
     * Rows whose text columns contain the term, newest first.
     */
    public function query(string $table, ?string $term): Builder
    {
        $query = DB::table($table);
        $key = $this->primaryKey($table);

        if ($term !== null && $term !== '') {
            $searchable = collect($this->columns($table))
                ->reject(fn (array $column): bool => $column['secret'] || in_array($column['kind'], ['boolean', 'json', 'binary'], true));

            $query->where(function (Builder $query) use ($searchable, $term): void {
                // whereLike compares as text and ignores case on PostgreSQL too.
                foreach ($searchable as $column) {
                    $query->orWhereLike($column['name'], '%'.$term.'%');
                }
            });
        }

        foreach ($key as $column) {
            $query->orderByDesc($column);
        }

        return $query;
    }

    /**
     * One row by its primary key, or null.
     *
     * @param  array<string, mixed>  $key
     * @return array<string, mixed>|null
     */
    public function find(string $table, array $key): ?array
    {
        $row = $this->whereKey($table, $key)->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * A row as the screens show it: secrets masked, values as text.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    public function present(string $table, array $row, ?int $limit = null): array
    {
        $values = [];

        foreach ($this->columns($table) as $column) {
            $value = $row[$column['name']] ?? null;

            if ($column['secret']) {
                $values[$column['name']] = $value === null ? null : '••••••';

                continue;
            }

            $text = match (true) {
                $value === null => null,
                is_bool($value) => $value ? 'true' : 'false',
                is_resource($value) => '[binary]',
                $column['kind'] === 'boolean' => (bool) $value ? 'true' : 'false',
                default => (string) $value,
            };

            $values[$column['name']] = $limit !== null && $text !== null && mb_strlen($text) > $limit
                ? mb_substr($text, 0, $limit).'…'
                : $text;
        }

        return $values;
    }

    /**
     * The row's primary key as a URL-safe token, and back.
     *
     * @param  array<string, mixed>  $row
     */
    public function keyToken(string $table, array $row): string
    {
        $key = [];

        foreach ($this->primaryKey($table) as $column) {
            $key[$column] = $row[$column] ?? null;
        }

        return rtrim(strtr(base64_encode((string) json_encode($key)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeKey(string $table, string $token): ?array
    {
        $key = json_decode((string) base64_decode(strtr($token, '-_', '+/'), true), true);
        $columns = $this->primaryKey($table);

        if (! is_array($key) || $columns === [] || array_keys($key) !== $columns) {
            return null;
        }

        foreach ($key as $value) {
            if (! is_scalar($value)) {
                return null;
            }
        }

        return $key;
    }

    /**
     * Converts the submitted text of each editable column to its stored
     * value. Empty text is null where the column allows it; on insert an empty
     * column with a default is left to the database.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function values(string $table, array $input, bool $inserting): array
    {
        $values = [];
        $errors = [];

        foreach ($this->columns($table) as $column) {
            $name = $column['name'];

            if ($column['secret'] || ($column['key'] && ! $inserting) || ! array_key_exists($name, $input)) {
                continue;
            }

            $raw = $input[$name];
            $text = is_string($raw) ? $raw : (is_scalar($raw) ? (string) $raw : null);

            if ($text === null || $text === '') {
                if ($inserting && ($column['auto'] || $column['has_default'])) {
                    continue;
                }

                if ($column['nullable']) {
                    $values[$name] = null;
                } elseif ($column['kind'] !== 'text') {
                    $errors[$name] = __('database.errors.required');
                } else {
                    $values[$name] = '';
                }

                continue;
            }

            try {
                $values[$name] = $this->convert($column['kind'], $text);
            } catch (Throwable) {
                $errors[$name] = __('database.errors.'.$column['kind']);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $message): array => [$message], $errors));
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    public function whereKey(string $table, array $key): Builder
    {
        $query = DB::table($table);

        foreach ($key as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function withoutSecrets(array $row): array
    {
        return array_filter($row, fn (string $column): bool => ! $this->isSecret($column), ARRAY_FILTER_USE_KEY);
    }

    private function convert(string $kind, string $text): mixed
    {
        return match ($kind) {
            'integer' => filter_var(trim($text), FILTER_VALIDATE_INT) !== false ? (int) trim($text) : throw new InvalidArgumentException,
            'decimal' => is_numeric(trim($text)) ? trim($text) : throw new InvalidArgumentException,
            'boolean' => match (strtolower(trim($text))) {
                '1', 'true', 't', 'yes' => true,
                '0', 'false', 'f', 'no' => false,
                default => throw new InvalidArgumentException,
            },
            'json' => json_validate($text) ? $text : throw new InvalidArgumentException,
            'datetime' => CarbonImmutable::parse(trim($text), 'UTC')->format('Y-m-d H:i:s'),
            'date' => CarbonImmutable::parse(trim($text), 'UTC')->format('Y-m-d'),
            default => $text,
        };
    }

    private function kind(string $typeName, string $fullType): string
    {
        $type = strtolower($typeName);

        return match (true) {
            // SQLite stores booleans as tinyint(1).
            in_array($type, ['bool', 'boolean'], true) || strtolower($fullType) === 'tinyint(1)' => 'boolean',
            in_array($type, ['int', 'int2', 'int4', 'int8', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true) => 'integer',
            in_array($type, ['numeric', 'decimal', 'float', 'float4', 'float8', 'double', 'real'], true) => 'decimal',
            in_array($type, ['json', 'jsonb'], true) => 'json',
            str_starts_with($type, 'timestamp') || $type === 'datetime' => 'datetime',
            $type === 'date' => 'date',
            in_array($type, ['bytea', 'blob', 'binary'], true) => 'binary',
            default => 'text',
        };
    }
}
