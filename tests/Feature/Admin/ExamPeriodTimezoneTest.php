<?php

use App\Enums\Role;
use App\Models\ExamPeriod;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('reads the entered window as Iraq time and stores it in UTC', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.periods.store'), [
            'code' => 'TZ1',
            'name_ar' => 'دورة التوقيت',
            'name_en' => 'Timezone round',
            'academic_year' => '2026-2027',
            'status' => 'open',
            'opens_at' => '2026-11-08T18:36',
            'closes_at' => '2026-11-10T21:36',
            'documents' => [],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.periods.index'));

    $period = ExamPeriod::query()->where('code', 'TZ1')->firstOrFail();

    expect($period->opens_at->toIso8601String())->toBe('2026-11-08T15:36:00+00:00')
        ->and($period->closes_at->toIso8601String())->toBe('2026-11-10T18:36:00+00:00');
});

it('shows the stored window in Iraq time on the edit form', function () {
    $period = ExamPeriod::factory()->create([
        'opens_at' => '2026-11-08 15:36:00',
        'closes_at' => '2026-11-10 18:36:00',
    ]);

    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.periods.edit', $period))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period.opens_at', '2026-11-08T18:36')
            ->where('period.closes_at', '2026-11-10T21:36'));
});

it('opens the period to students at the entered Iraq time', function () {
    $period = ExamPeriod::factory()->create([
        'opens_at' => '2026-11-08 15:36:00',
        'closes_at' => '2026-11-10 18:36:00',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-08 18:35', ExamPeriod::LOCAL_TIMEZONE));
    expect($period->fresh()->isAcceptingApplications())->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2026-11-08 18:37', ExamPeriod::LOCAL_TIMEZONE));
    expect($period->fresh()->isAcceptingApplications())->toBeTrue();
});
