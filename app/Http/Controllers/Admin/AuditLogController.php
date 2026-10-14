<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Read-only, newest first, cursor-paginated so deep pages stay cheap on a
     * large append-only table.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:80'],
        ]);

        $events = AuditEvent::query()
            ->with('actor:id,username,name')
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $action).'%'))
            ->when($filters['actor'] ?? null, fn (Builder $query, int|string $actor) => $query->where('actor_id', $actor))
            ->when($filters['subject'] ?? null, fn (Builder $query, string $subject) => $query->where('subject_type', $subject))
            ->orderByDesc('id')
            ->cursorPaginate(50)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'actor' => $event->actor === null ? null : ['id' => $event->actor->id, 'username' => $event->actor->username],
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'ip_address' => $event->ip_address,
                'request_id' => $event->request_id,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit/index', [
            'events' => $events,
            'filters' => $filters,
        ]);
    }
}
