<?php

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Keeps Cloudflare Access in front of the staff portal in step with the
 * staff accounts: the Allow policy of the staff host's Access application
 * lists exactly the e-mails of active staff, plus the always-allowed ones
 * (the owner), so a staff member an administrator adds can open the portal
 * and one who is deactivated cannot. The database is the source of truth;
 * every sync rewrites the whole list. Student e-mails never go there.
 *
 * Without a token (local, tests) it does nothing.
 */
class CloudflareAccess
{
    public const STATUS_KEY = 'staff-access-sync';

    public function isConfigured(): bool
    {
        return filled(config('services.cloudflare_access.token'))
            && filled(config('services.cloudflare_access.account_id'))
            && filled(config('services.cloudflare_access.domain'));
    }

    /**
     * @return list<string>
     */
    public function allowedEmails(): array
    {
        $staff = User::query()
            ->staff()
            ->whereNull('deactivated_at')
            ->whereNull('closed_at')
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        $emails = array_map(
            fn (mixed $email): string => Str::lower(trim((string) $email)),
            [...$staff, ...(array) config('services.cloudflare_access.always_allow', [])],
        );

        $emails = array_values(array_unique(array_filter($emails, fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
        sort($emails);

        return $emails;
    }

    /**
     * Rewrites the Allow policy and returns how many e-mails it now lists.
     */
    public function sync(): int
    {
        $emails = $this->allowedEmails();

        // An empty list would lock everyone out, the owner included.
        if ($emails === []) {
            throw new RuntimeException('Refusing to sync Cloudflare Access: no staff e-mail to allow.');
        }

        $domain = (string) config('services.cloudflare_access.domain');
        $policyName = (string) config('services.cloudflare_access.policy');

        $app = $this->find('apps', 'domain', $domain)
            ?? throw new RuntimeException("No Cloudflare Access application for {$domain}.");

        $policy = $this->find("apps/{$app['id']}/policies", 'name', $policyName)
            ?? throw new RuntimeException("No policy named \"{$policyName}\" on the Access application for {$domain}.");

        $this->api()->put("apps/{$app['id']}/policies/{$policy['id']}", [
            'name' => $policyName,
            'decision' => 'allow',
            'include' => array_map(fn (string $email): array => ['email' => ['email' => $email]], $emails),
            'precedence' => $policy['precedence'] ?? 1,
        ])->throw();

        $this->remember(true, count($emails));

        return count($emails);
    }

    /**
     * What the Users page shows: when the list last went to Cloudflare, and
     * whether it got there.
     *
     * @return array{ok: bool|null, at: string, count: int|null}|null ok is null while a sync is waiting
     */
    public function status(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $status = Cache::get(self::STATUS_KEY);

        if (! is_array($status) || ! is_string($status['at'] ?? null)) {
            return null;
        }

        return [
            'ok' => is_bool($status['ok'] ?? null) ? $status['ok'] : null,
            'at' => $status['at'],
            'count' => is_int($status['count'] ?? null) ? $status['count'] : null,
        ];
    }

    public function remember(bool $ok, ?int $count = null): void
    {
        Cache::forever(self::STATUS_KEY, ['ok' => $ok, 'at' => now()->toIso8601String(), 'count' => $count]);
    }

    /**
     * A change is waiting for the sync job.
     */
    public function markPending(): void
    {
        Cache::forever(self::STATUS_KEY, ['ok' => null, 'at' => now()->toIso8601String(), 'count' => null]);
    }

    /**
     * The first item of a Cloudflare list whose field has the value.
     *
     * @return array{id: string, precedence?: int}|null
     */
    private function find(string $path, string $field, string $value): ?array
    {
        $items = $this->api()->get($path)->throw()->json('result');

        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && ($item[$field] ?? null) === $value && is_string($item['id'] ?? null)) {
                return array_filter([
                    'id' => $item['id'],
                    'precedence' => is_int($item['precedence'] ?? null) ? $item['precedence'] : null,
                ], fn (mixed $entry): bool => $entry !== null);
            }
        }

        return null;
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4/accounts/'.config('services.cloudflare_access.account_id').'/access/')
            ->withToken((string) config('services.cloudflare_access.token'))
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }
}
