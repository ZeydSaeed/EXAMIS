<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransitionApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = ApplicationStatus::tryFrom((string) $this->input('target'));

        if ($target === null) {
            Gate::authorize('view', $this->application());

            return true;
        }

        Gate::authorize('transition', [$this->application(), $target]);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', Rule::enum(ApplicationStatus::class)->only([
                ApplicationStatus::UnderReview,
                ApplicationStatus::RequiresCorrection,
                ApplicationStatus::Accepted,
                ApplicationStatus::Rejected,
            ])],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function target(): ApplicationStatus
    {
        return ApplicationStatus::from($this->string('target')->toString());
    }

    public function application(): Application
    {
        /** @var Application */
        return $this->route('application');
    }
}
