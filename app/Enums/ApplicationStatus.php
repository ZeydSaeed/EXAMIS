<?php

namespace App\Enums;

/**
 * Application lifecycle. The transition table is the single source of truth
 * for which status changes are legal (ADR-0004).
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RequiresCorrection = 'requires_correction';
    case Resubmitted = 'resubmitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /**
     * Legal transitions keyed by the source status.
     *
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Draft->value => [self::Submitted, self::Withdrawn],
            self::Submitted->value => [self::UnderReview, self::Withdrawn],
            self::UnderReview->value => [self::RequiresCorrection, self::Accepted, self::Rejected],
            self::RequiresCorrection->value => [self::Resubmitted, self::Withdrawn],
            self::Resubmitted->value => [self::UnderReview],
            self::Accepted->value => [self::UnderReview],
            self::Rejected->value => [self::UnderReview],
            self::Withdrawn->value => [],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitions()[$this->value], true);
    }

    /**
     * Whether a transition into $target from this status needs a written reason.
     */
    public function requiresReasonFor(self $target): bool
    {
        return match (true) {
            $target === self::RequiresCorrection, $target === self::Rejected => true,
            $target === self::UnderReview && $this->isDecided() => true,
            default => false,
        };
    }

    /**
     * Permission a staff member needs to move an application from this status to $target,
     * or null when the transition belongs to the applicant who owns it.
     */
    public function permissionFor(self $target): ?Permission
    {
        return match (true) {
            $target === self::Submitted, $target === self::Resubmitted, $target === self::Withdrawn => null,
            $target === self::UnderReview && $this->isDecided() => Permission::ApplicationsReopen,
            $target === self::Accepted, $target === self::Rejected => Permission::ApplicationsDecide,
            default => Permission::ApplicationsReview,
        };
    }

    /**
     * Statuses in which the applicant may edit form data and documents.
     */
    /**
     * The applicant may change the form: a draft, an application sent back
     * for correction, and a submitted one until staff start reviewing it.
     */
    public function isEditableByApplicant(): bool
    {
        return $this === self::Draft || $this === self::RequiresCorrection || $this->isAwaitingReview();
    }

    /**
     * Submitted (or resubmitted) and not yet picked up by a reviewer.
     */
    public function isAwaitingReview(): bool
    {
        return $this === self::Submitted || $this === self::Resubmitted;
    }

    public function isDecided(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }

    /**
     * Statuses that sit in the staff review queue.
     *
     * @return list<self>
     */
    public static function awaitingReview(): array
    {
        return [self::Submitted, self::Resubmitted];
    }

    public function label(): string
    {
        return __('status.'.$this->value);
    }
}
