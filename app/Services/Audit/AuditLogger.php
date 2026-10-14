<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Writes security-relevant actions to the append-only audit trail.
 * Never pass secrets, passwords, OTPs or document contents as metadata.
 */
class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditEvent
    {
        $actor ??= $this->request->user();

        return AuditEvent::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
            'request_id' => Context::get('request_id'),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
