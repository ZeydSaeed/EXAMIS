<?php

use App\Enums\Role;
use App\Models\User;

/*
 * Students reach the portal from shared addresses: mobile carriers in Iraq
 * put many subscribers behind one IP (CGNAT) and a school lab signs a class
 * in from one. Their per-IP limits are wide, configurable, and counted apart
 * from staff sign-in (audit finding M1).
 */

it('lets a whole class sign in from one address in the same minute', function () {
    expect(config('examis.security.students_per_ip.logins_per_minute'))->toBeGreaterThanOrEqual(60)
        ->and(config('examis.security.students_per_ip.registrations_per_day'))->toBeGreaterThanOrEqual(300);

    foreach (range(1, 40) as $student) {
        $this->post(route('student.login'), ['identifier' => (string) (500000 + $student)])
            ->assertRedirect(route('student.code', absolute: false));
    }
});

it('still stops one address that goes past the configured limit', function () {
    config(['examis.security.students_per_ip.logins_per_minute' => 3]);

    foreach (range(1, 3) as $try) {
        $this->post(route('student.login'), ['identifier' => '50000'.$try])->assertRedirect();
    }

    $this->post(route('student.login'), ['identifier' => '500004'])->assertTooManyRequests();
});

it('limits registrations per address by the configured numbers', function () {
    config(['examis.security.students_per_ip.registrations_per_minute' => 2]);

    $this->post(route('register.store'), [])->assertStatus(302);
    $this->post(route('register.store'), [])->assertStatus(302);
    $this->post(route('register.store'), [])->assertTooManyRequests();
});

it('counts students apart from staff, so a busy school cannot block staff sign-in', function () {
    config(['examis.security.students_per_ip.attempts_per_hour' => 2]);
    $staff = User::factory()->staff(Role::Reviewer)->create();

    $this->post(route('student.login'), ['identifier' => '500001'])->assertRedirect();
    $this->post(route('student.login'), ['identifier' => '500002'])->assertRedirect();
    $this->post(route('student.login'), ['identifier' => '500003'])->assertTooManyRequests();

    $this->post(route('login.store'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('admin.home'));
    $this->assertAuthenticatedAs($staff);
});
