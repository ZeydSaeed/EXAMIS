<?php

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\ExamPeriod;
use Inertia\Testing\AssertableInertia as Assert;

function reportApplications(): ExamPeriod
{
    $period = ExamPeriod::factory()->create();

    foreach ([
        [Gender::Male, 'ELECTRICITY', 'IND'],
        [Gender::Male, 'ELECTRICITY', 'IND'],
        [Gender::Female, 'ELECTRICITY', 'IND'],
        [Gender::Female, 'ACCOUNTING', 'COM'],
    ] as [$gender, $category, $branch]) {
        Application::factory()->submitted()->create([
            'exam_period_id' => $period->id,
            'gender' => $gender,
            'category_code' => $category,
            'branch_code' => $branch,
        ]);
    }

    return $period;
}

beforeEach(function () {
    $this->actingAs(staffWith(Role::Supervisor));
});

it('splits every specialization into male, female and all', function () {
    $period = reportApplications();

    $this->get(route('admin.reports.index', ['period' => $period->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('bySpecialization.0.code', 'ELECTRICITY')
            ->where('bySpecialization.0.males', 2)
            ->where('bySpecialization.0.females', 1)
            ->where('bySpecialization.0.count', 3)
            ->where('bySpecialization.1.code', 'ACCOUNTING')
            ->where('bySpecialization.1.males', 0)
            ->where('bySpecialization.1.females', 1)
            ->where('bySpecialization.1.count', 1)
            ->has('specializations', 2));
});

it('narrows every table by gender', function () {
    $period = reportApplications();

    $this->get(route('admin.reports.index', ['period' => $period->id, 'gender' => 'female']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.gender', 'female')
            ->where('bySpecialization.0.count', 1)
            ->where('bySpecialization.0.males', 0)
            ->where('byStatus.0.count', 2)
            ->has('byBranch', 2));
});

it('narrows every table by specialization and keeps the full list to choose from', function () {
    $period = reportApplications();

    $this->get(route('admin.reports.index', ['period' => $period->id, 'category' => 'ACCOUNTING']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('bySpecialization', 1)
            ->where('byStatus.0.count', 1)
            ->has('byBranch', 1)
            ->has('specializations', 2));
});

it('combines both filters', function () {
    $period = reportApplications();

    $this->get(route('admin.reports.index', ['period' => $period->id, 'gender' => 'male', 'category' => 'ACCOUNTING']))
        ->assertInertia(fn (Assert $page) => $page->has('bySpecialization', 0)->has('byStatus', 0));
});

it('leaves drafts out and rejects an unknown gender', function () {
    $period = reportApplications();
    Application::factory()->create(['exam_period_id' => $period->id, 'status' => ApplicationStatus::Draft, 'gender' => Gender::Male, 'category_code' => 'ELECTRICITY']);

    $this->get(route('admin.reports.index', ['period' => $period->id]))
        ->assertInertia(fn (Assert $page) => $page->where('bySpecialization.0.males', 2));

    $this->get(route('admin.reports.index', ['period' => $period->id, 'gender' => 'other']))
        ->assertSessionHasErrors('gender');
});

it('exports only what the filters select and audits them', function () {
    $period = reportApplications();

    $csv = $this->get(route('admin.reports.export', ['period' => $period, 'gender' => 'female', 'category' => 'ELECTRICITY']))
        ->assertOk()
        ->streamedContent();

    expect(substr_count(trim($csv), "\n"))->toBe(1)
        ->and(AuditEvent::query()->where('action', 'report.exported')->sole()->metadata['filters'])
        ->toBe(['gender' => 'female', 'category' => 'ELECTRICITY']);
});
