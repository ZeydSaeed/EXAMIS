<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedReferenceData();
    config(['examis.security.require_staff_two_factor' => false]);
});

it('renders the applicant dashboard with only the applicant\'s own applications', function () {
    $mine = Application::factory()->submitted()->create();
    Application::factory()->submitted()->create();

    $this->actingAs($mine->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('applicant/dashboard')
            ->has('applications', 1)
            ->where('applications.0.uuid', $mine->uuid));
});

it('sends staff from the applicant dashboard to the admin area', function () {
    $this->actingAs(staffWith(Role::Reviewer))->get(route('dashboard'))->assertRedirect(route('admin.home'));
});

it('renders the application form with eligible options and what is missing', function () {
    $application = readyDraft();
    $application->examPeriod->update(['eligible_branch_codes' => ['SCI']]);

    $this->actingAs($application->user)->get(route('applications.edit', $application))
        ->assertInertia(fn (Assert $page) => $page
            ->component('applicant/applications/edit')
            ->where('application.lock_version', 1)
            ->has('options.branches', 1)
            ->where('options.branches.0.code', 'SCI')
            ->has('documentTypes', 1)
            ->where('missing.fields', [])
            ->where('missing.documents', []));
});

it('redirects from the form to the summary once submitted', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs($application->user)->get(route('applications.edit', $application))
        ->assertRedirect(route('applications.show', $application));
});

it('shows the applicant a generic reviewer label instead of staff names', function () {
    $reviewer = staffWith(Role::Reviewer);
    $application = Application::factory()->inStatus(ApplicationStatus::RequiresCorrection)->create();
    $application->events()->create(['actor_id' => $reviewer->id, 'type' => 'status_changed', 'to_status' => 'requires_correction', 'reason' => 'أعد رفع الصورة']);

    $this->actingAs($application->user)->get(route('applications.show', $application))
        ->assertInertia(fn (Assert $page) => $page
            ->component('applicant/applications/show')
            ->where('application.events.0.actor', __('applications.actors.staff'))
            ->where('application.events.0.reason', 'أعد رفع الصورة')
            ->where('application.events.0.metadata', null)
            ->where('can.edit', true));
});

it('answers 404 when an applicant opens another applicant\'s application', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(applicant())->get(route('applications.show', $application))->assertNotFound();
});

it('renders the review screen with only the actions the role may take and audits the view', function () {
    $reviewer = staffWith(Role::Reviewer);
    $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo($reviewer)->create();

    $this->actingAs($reviewer)->get(route('admin.applications.show', $application))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/applications/show')
            ->has('transitions', 1)
            ->where('transitions.0.target', 'requires_correction')
            ->where('transitions.0.requires_reason', true)
            ->where('canAssign', false));

    expect(AuditEvent::query()->where('action', 'application.viewed')->where('actor_id', $reviewer->id)->exists())->toBeTrue();
});

it('lists only the review queue for reviewers and filters by status', function () {
    $reviewer = staffWith(Role::Reviewer);
    Application::factory()->submitted()->create();
    Application::factory()->inStatus(ApplicationStatus::UnderReview)->assignedTo(staffWith(Role::Reviewer))->create();
    Application::factory()->complete()->create();

    $this->actingAs($reviewer)->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/applications/index')->where('applications.total', 1));

    $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.applications.index', ['status' => 'under_review']))
        ->assertInertia(fn (Assert $page) => $page->where('applications.total', 1));
});

it('finds applications by reference number', function () {
    $target = Application::factory()->submitted()->create();
    Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.applications.index', ['q' => strtolower((string) $target->reference_number)]))
        ->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->where('applications.data.0.uuid', $target->uuid));
});

it('treats search input as data, not SQL', function () {
    Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.applications.index', ['q' => "' OR 1=1 --"]))
        ->assertInertia(fn (Assert $page) => $page->where('applications.total', 0));
});

it('renders every admin section for an administrator', function (string $route) {
    $this->actingAs(staffWith(Role::Administrator))->get(route($route))->assertOk();
})->with(['admin.home', 'admin.dashboard', 'admin.periods.index', 'admin.periods.create', 'admin.content.index', 'admin.users.index', 'admin.audit.index', 'admin.operations', 'admin.reports.index']);

it('creates an exam period with its document requirements', function () {
    $type = DocumentType::factory()->create();

    $this->actingAs(staffWith(Role::Administrator))->post(route('admin.periods.store'), [
        'code' => 'EX2028',
        'name_ar' => 'دورة ٢٠٢٨',
        'name_en' => 'Round 2028',
        'academic_year' => '2027-2028',
        'status' => 'open',
        'opens_at' => '2027-09-01T08:00',
        'closes_at' => '2027-10-01T08:00',
        'eligible_branch_codes' => [],
        'documents' => [['document_type_id' => $type->id, 'is_required' => true]],
    ])->assertRedirect(route('admin.periods.index'));

    $period = ExamPeriod::query()->where('code', 'EX2028')->sole();
    expect($period->eligible_branch_codes)->toBeNull()
        ->and($period->documentTypes()->sole()->pivot->is_required)->toBeTruthy();
});

it('exports CSV with spreadsheet formulas neutralised and audits the export', function () {
    $application = Application::factory()->submitted()->create(['full_name_ar' => '=HYPERLINK("http://evil")']);

    $response = $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.reports.export', $application->examPeriod));

    $csv = $response->streamedContent();
    expect($csv)->toContain("'=HYPERLINK")
        ->and(AuditEvent::query()->where('action', 'report.exported')->exists())->toBeTrue();
});

it('does not let reviewers export personal data', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Reviewer))->get(route('admin.reports.export', $application->examPeriod))->assertForbidden();
});
