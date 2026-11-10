<?php

namespace App\Enums;

use Illuminate\Http\Request;

/**
 * The two faces of the system (ADR-0011). Each has its own host, guard,
 * session cookie and routes, so a session from one means nothing on the other.
 */
enum Portal: string
{
    case Students = 'students';
    case Staff = 'staff';

    public static function forRequest(Request $request): ?self
    {
        foreach (self::cases() as $portal) {
            if ($request->getHost() === $portal->host()) {
                return $portal;
            }
        }

        return null;
    }

    public function host(): string
    {
        return (string) config("examis.portals.{$this->value}");
    }

    /**
     * The host for route groups. Wayfinder writes a route's domain into the
     * generated TypeScript, so it runs without one and the frontend stays
     * free of hosts and ports.
     */
    public function routeDomain(): ?string
    {
        return app()->runningConsoleCommand('wayfinder:generate') ? null : $this->host();
    }

    public function guard(): string
    {
        return match ($this) {
            self::Students => 'web',
            self::Staff => 'staff',
        };
    }

    /**
     * Session settings for this portal. Cookies are always host-only, and
     * take the `__Host-` prefix over HTTPS, which browsers refuse to scope
     * wider.
     *
     * @return array<string, mixed>
     */
    public function sessionConfig(bool $secure): array
    {
        $name = 'examis_'.$this->value.'_session';

        $shared = [
            'session.cookie' => $secure ? '__Host-'.$name : $name,
            'session.domain' => null,
            'session.path' => '/',
            'session.secure' => $secure,
        ];

        return match ($this) {
            self::Students => $shared,
            self::Staff => [
                ...$shared,
                'session.table' => 'staff_sessions',
                'session.lifetime' => (int) config('examis.staff.idle_minutes'),
                'session.expire_on_close' => true,
                'session.encrypt' => true,
                'session.same_site' => 'strict',
            ],
        };
    }
}
