<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Subject types the AuditLogger writes (model base names).
     *
     * @var list<string>
     */
    private const SUBJECTS = ['Application', 'ApplicationDocument', 'ExamPeriod', 'ContentPage', 'User'];

    /**
     * Read-only, newest first, cursor-paginated so deep pages stay cheap on a
     * large append-only table. The search term matches the start of an
     * action or of the actor's username.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $events = AuditEvent::query()
            ->with('actor:id,username,name')
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', 'like', $this->escapeLike($action).'%'))
            ->when($filters['actor'] ?? null, fn (Builder $query, int|string $actor) => $query->where('actor_id', $actor))
            ->when($filters['subject'] ?? null, fn (Builder $query, string $subject) => $query->where('subject_type', $subject))
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $prefix = $this->escapeLike(trim($term)).'%';

                $query->where(fn (Builder $query) => $query
                    ->where('action', 'like', $prefix)
                    ->orWhereIn('actor_id', User::query()->whereLike('username', $prefix)->select('id')));
            })
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 50))
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
            'subjects' => self::SUBJECTS,
        ]);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }
}
