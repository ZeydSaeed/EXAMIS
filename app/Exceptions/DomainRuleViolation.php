<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule rejected the operation. Rendered as a field error for
 * browser requests and as a JSON problem for API requests; never reported
 * to the error tracker because it is expected behaviour.
 */
class DomainRuleViolation extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context  safe, non-personal details for clients
     */
    public function __construct(
        string $message,
        public readonly string $field = 'application',
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidTransition(): self
    {
        return new self(__('applications.errors.invalid_transition'), 'status', 409);
    }

    public static function staleVersion(): self
    {
        return new self(__('applications.errors.stale_version'), 'lock_version', 409);
    }

    public static function periodClosed(): self
    {
        return new self(__('applications.errors.period_closed'), 'exam_period');
    }

    public static function notEditable(): self
    {
        return new self(__('applications.errors.not_editable'), 'application', 409);
    }

    public static function reasonRequired(): self
    {
        return new self(__('applications.errors.reason_required'), 'reason');
    }

    /**
     * @param  array{fields: list<string>, documents: list<string>}  $missing
     */
    public static function incomplete(array $missing): self
    {
        return new self(__('applications.errors.incomplete'), 'application', 422, ['missing' => $missing]);
    }

    /**
     * @param  list<string>  $fields
     */
    public static function requiredAfterSubmission(array $fields): self
    {
        return new self(__('applications.errors.required_after_submission'), 'application', 422, ['missing' => ['fields' => $fields, 'documents' => []]]);
    }

    public static function notEligible(string $field): self
    {
        return new self(__('applications.errors.not_eligible'), $field);
    }

    public static function documentLimitReached(): self
    {
        return new self(__('applications.errors.document_limit'), 'file');
    }

    public static function assigneeNotEligible(): self
    {
        return new self(__('applications.errors.assignee_not_eligible'), 'assignee');
    }
}
