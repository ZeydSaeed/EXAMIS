<?php

use App\Enums\ApplicationStatus as S;
use App\Enums\Permission;

/**
 * Pins the approved transition table (ADR-0004). Changing it must be a
 * deliberate decision that updates this test and the ADR together.
 */
test('the transition table matches ADR-0004', function () {
    $table = array_map(
        fn (array $targets): array => array_map(fn (S $status): string => $status->value, $targets),
        S::transitions(),
    );

    expect($table)->toBe([
        'draft' => ['submitted', 'withdrawn'],
        'submitted' => ['under_review', 'withdrawn'],
        'under_review' => ['requires_correction', 'accepted', 'rejected'],
        'requires_correction' => ['resubmitted', 'withdrawn'],
        'resubmitted' => ['under_review'],
        'accepted' => ['under_review'],
        'rejected' => ['under_review'],
        'withdrawn' => [],
    ]);
});

test('every status has an entry in the transition table', function () {
    expect(array_keys(S::transitions()))->toEqualCanonicalizing(array_map(fn (S $status) => $status->value, S::cases()));
});

test('reasons are required for correction, rejection and reopening only', function (S $from, S $to, bool $required) {
    expect($from->requiresReasonFor($to))->toBe($required);
})->with([
    'request correction' => [S::UnderReview, S::RequiresCorrection, true],
    'reject' => [S::UnderReview, S::Rejected, true],
    'reopen accepted' => [S::Accepted, S::UnderReview, true],
    'reopen rejected' => [S::Rejected, S::UnderReview, true],
    'accept' => [S::UnderReview, S::Accepted, false],
    'start review' => [S::Submitted, S::UnderReview, false],
    'withdraw' => [S::Submitted, S::Withdrawn, false],
]);

test('transitions map to the permission that authorizes them', function (S $from, S $to, ?Permission $permission) {
    expect($from->permissionFor($to))->toBe($permission);
})->with([
    'submit is the owner\'s' => [S::Draft, S::Submitted, null],
    'withdraw is the owner\'s' => [S::RequiresCorrection, S::Withdrawn, null],
    'start review' => [S::Submitted, S::UnderReview, Permission::ApplicationsReview],
    'request correction' => [S::UnderReview, S::RequiresCorrection, Permission::ApplicationsReview],
    'accept' => [S::UnderReview, S::Accepted, Permission::ApplicationsDecide],
    'reject' => [S::UnderReview, S::Rejected, Permission::ApplicationsDecide],
    'reopen' => [S::Accepted, S::UnderReview, Permission::ApplicationsReopen],
]);

test('only drafts and correction requests are editable by the applicant', function () {
    $editable = array_values(array_filter(S::cases(), fn (S $status) => $status->isEditableByApplicant()));

    expect($editable)->toBe([S::Draft, S::RequiresCorrection]);
});
