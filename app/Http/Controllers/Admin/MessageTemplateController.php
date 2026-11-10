<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Applications\DecideApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $template = new MessageTemplate($this->validated($request));
        $template->updated_by = $request->user()->id;
        $template->save();

        $audit->record('message_template.created', $template, ['status' => $template->status->value]);

        return $this->saved();
    }

    public function update(Request $request, MessageTemplate $messageTemplate, AuditLogger $audit): RedirectResponse
    {
        $messageTemplate->fill($this->validated($request));
        $messageTemplate->updated_by = $request->user()->id;
        $messageTemplate->save();

        $audit->record('message_template.updated', $messageTemplate, ['status' => $messageTemplate->status->value]);

        return $this->saved();
    }

    /**
     * Sent messages are copies on each application, so they stay.
     */
    public function destroy(MessageTemplate $messageTemplate, AuditLogger $audit): RedirectResponse
    {
        $audit->record('message_template.deleted', $messageTemplate, ['status' => $messageTemplate->status->value]);

        $messageTemplate->delete();

        return $this->saved();
    }

    /**
     * @return array{title: string, status: string, body: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::enum(ApplicationStatus::class)->only(DecideApplication::DECISIONS)],
            'body' => ['required', 'string', 'max:1500', function (string $attribute, string $value, Closure $fail): void {
                preg_match_all('/\{([^{}]*)\}/u', $value, $matches);

                if (array_diff($matches[1], MessageTemplate::PLACEHOLDERS) !== []) {
                    $fail(__('admin.errors.unknown_placeholder'));
                }
            }],
        ]);
    }

    private function saved(): RedirectResponse
    {
        return to_route('admin.content.index');
    }
}
