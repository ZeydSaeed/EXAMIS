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
    case FeesPaid = 'fees_paid';

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
            self::Accepted->value => [self::UnderReview, self::FeesPaid],
            self::Rejected->value => [self::UnderReview],
            self::Withdrawn->value => [],
            self::FeesPaid->value => [self::UnderReview],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::transitions()[$this->value], true);
    }

    /**
     * The legal transitions that lead from this status to a decision: the
     * decision itself, or a stop at review first. Empty when there is no way,
     * as for an application waiting on the student.
     *
     * @return list<self>
     */
    public function stepsTo(self $decision): array
    {
        return match (true) {
            $this === $decision => [],
            $this->canTransitionTo($decision) => [$decision],
            $this->canTransitionTo(self::UnderReview) && self::UnderReview->canTransitionTo($decision) => [self::UnderReview, $decision],
            default => [],
        };
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
            $target === self::Accepted, $target === self::Rejected, $target === self::FeesPaid => Permission::ApplicationsDecide,
            default => Permission::ApplicationsReview,
        };
    }

    /**
     * The applicant may change the form only in a draft or when staff sent it
     * back for correction (ADR-0004). Once submitted it is locked, so
     * reviewers always judge what was sent.
     */
    public function isEditableByApplicant(): bool
    {
        return $this === self::Draft || $this === self::RequiresCorrection;
    }

    public function isDecided(): bool
    {
        return $this === self::Accepted || $this === self::Rejected || $this === self::FeesPaid;
    }

    /**
     * The status people are shown. A sent application is with the reviewers
     * from the moment it arrives, so submitted and resubmitted read as under
     * review; the stored status still drives the queue and the rules.
     */
    public function shownAs(): self
    {
        return match ($this) {
            self::Submitted, self::Resubmitted => self::UnderReview,
            default => $this,
        };
    }

    /**
     * The stored statuses shown as this one.
     *
     * @return list<self>
     */
    public function shownFrom(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->shownAs() === $this));
    }

    /**
     * The statuses staff count and filter by, in the order they follow.
     *
     * @return list<self>
     */
    public static function staffStatuses(): array
    {
        return [self::UnderReview, self::Accepted, self::Rejected, self::RequiresCorrection, self::FeesPaid];
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
