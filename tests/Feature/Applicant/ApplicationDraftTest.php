<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Models\User;

beforeEach(function () {
    seedReferenceData();
});

describe('starting an application', function () {
    it('creates one draft and redirects to the form', function () {
        $user = applicant();
        $period = ExamPeriod::factory()->create();

        $response = $this->actingAs($user)->post(route('applications.store'), ['exam_period_id' => $period->id]);

        $application = Application::query()->sole();
        $response->assertRedirect(route('applications.edit', $application));
        expect($application->user_id)->toBe($user->id)
            ->and($application->status)->toBe(ApplicationStatus::Draft)
            ->and($application->reference_number)->toBeNull()
            ->and($application->events()->count())->toBe(1);
    });

    it('returns the existing draft instead of creating a duplicate', function () {
        $user = applicant();
        $period = ExamPeriod::factory()->create();

        $this->actingAs($user)->post(route('applications.store'), ['exam_period_id' => $period->id]);
        $this->actingAs($user)->post(route('applications.store'), ['exam_period_id' => $period->id]);

        expect(Application::query()->count())->toBe(1);
    });

    it('rejects periods that are not accepting applications', function () {
        $period = ExamPeriod::factory()->closed()->create();

        $this->actingAs(applicant())
            ->post(route('applications.store'), ['exam_period_id' => $period->id])
            ->assertSessionHasErrors(['exam_period' => __('applications.errors.period_closed')]);

        expect(Application::query()->count())->toBe(0);
    });

    it('forbids staff accounts from applying', function () {
        $period = ExamPeriod::factory()->create();

        $this->actingAs(staffWith(Role::Reviewer))
            ->post(route('applications.store'), ['exam_period_id' => $period->id])
            ->assertForbidden();
    });
});

describe('saving a draft', function () {
    it('stores valid fields and increments the version', function () {
        $application = Application::factory()->create();
        $application->user->forceFill(['phone' => '07701234567'])->save();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'student_name' => 'زينب',
                'father_name' => 'علي',
                'grandfather_name' => 'حسين',
                'great_grandfather_name' => 'عبد الكريم',
                'mother_first_name' => 'مريم',
                'mother_father_name' => 'جاسم',
                'surname' => 'الموسوي',
                'phone' => '07999999999',
                'mahalla' => '٦٣٠',
                'school_name' => 'إعدادية الصناعة',
                'school_governorate_code' => 'BGD',
                'last_grade' => '  الخامس   الإعدادي ',
                'intermediate_school_name' => 'لم يعد يُجمع',
                'branch_code' => 'COM',
                'category_code' => 'COM_ACC',
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->student_name)->toBe('زينب')
            ->and($application->great_grandfather_name)->toBe('عبد الكريم')
            ->and($application->full_name_ar)->toBe('زينب علي حسين عبد الكريم')
            ->and($application->mother_name_ar)->toBe('مريم جاسم')
            ->and($application->getAttributes())->not->toHaveKey('surname')
            // The mobile number is the account's, whatever the request sends.
            ->and($application->phone)->toBe('07701234567')
            ->and($application->mahalla)->toBe('630')
            ->and($application->school_governorate_code)->toBe('BGD')
            ->and($application->last_grade)->toBe('الخامس الإعدادي')
            ->and($application->getAttributes())->not->toHaveKey('intermediate_school_name')
            // The branch and specialization are chosen on their own page only.
            ->and($application->branch_code)->toBeNull()
            ->and($application->category_code)->toBeNull()
            ->and($application->lock_version)->toBe(2);
    });

    it('keeps every application in Karbala', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'governorate_code' => 'BSR'])
            ->assertSessionHasNoErrors();

        expect($application->fresh()->governorate_code)->toBe(Application::GOVERNORATE_CODE);
    });

    it('keeps the branch and specialization chosen on their page', function () {
        $application = Application::factory()->create(['branch_code' => 'IND', 'category_code' => 'IND_ELC']);

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'branch_code' => 'COM',
                'category_code' => 'COM_ACC',
            ])
            ->assertSessionHasNoErrors();

        expect($application->fresh()->only(['branch_code', 'category_code']))
            ->toBe(['branch_code' => 'IND', 'category_code' => 'IND_ELC']);
    });

    it('stores the place of birth as a governorate or as written text for "other"', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'birth_place_code' => 'OTHER',
                'birth_place_other' => '  طهران  ',
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->birth_place_code)->toBe('OTHER')
            ->and($application->birth_place_other)->toBe('طهران')
            ->and($application->birthPlace())->toBe('طهران');

        // Choosing a governorate drops the written place.
        $this->put(route('applications.update', $application), [
            'lock_version' => $application->lock_version,
            'birth_place_code' => 'BGD',
            'birth_place_other' => 'طهران',
        ])->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->birth_place_other)->toBeNull()
            ->and($application->birthPlace())->toBe('بغداد');
    });

    it('rejects an unknown place of birth and requires the written place for "other"', function () {
        $application = Application::factory()->complete()->create(['birth_place_code' => 'OTHER', 'birth_place_other' => null]);

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'birth_place_code' => 'ATLANTIS'])
            ->assertSessionHasErrors('birth_place_code');

        expect($application->fresh()->missingFields())->toContain('birth_place_other');
    });

    it('accepts only a known governorate for the last school', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'school_governorate_code' => 'XXX'])
            ->assertSessionHasErrors('school_governorate_code');
    });

    it('rejects a save based on a stale version instead of overwriting', function () {
        $application = Application::factory()->create(['student_name' => 'الأصلي']);
        $application->forceFill(['lock_version' => 3])->save();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 2,
                'student_name' => 'قديم',
            ])
            ->assertSessionHasErrors(['lock_version' => __('applications.errors.stale_version')]);

        expect($application->fresh()->student_name)->toBe('الأصلي');
    });

    it('validates fields with Arabic messages', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'father_name' => 'Ali',
                'school_governorate_code' => 'ATLANTIS',
            ])
            ->assertSessionHasErrors([
                'father_name' => 'يجب كتابة اسم الأب بالأحرف العربية فقط.',
                'school_governorate_code' => 'القيمة المختارة في المحافظة غير صالحة.',
            ]);
    });

    it('answers 404 when another applicant tries to edit', function () {
        $application = Application::factory()->create();

        $this->actingAs(applicant())
            ->put(route('applications.update', $application), ['lock_version' => 1, 'student_name' => 'محاولة'])
            ->assertNotFound();
    });

    it('refuses edits once the review has started', function () {
        $application = Application::factory()->inStatus(ApplicationStatus::UnderReview)->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'student_name' => 'تعديل'])
            ->assertForbidden();
    });

    it('lets the applicant edit a submitted application before review and records the change', function () {
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->for($student)->create(['phone' => $student->phone]);

        $this->actingAs($student)
            ->put(route('applications.update', $application), [
                ...$application->only(Application::FORM_FIELDS),
                'birth_date' => '2006-03-15',
                'gender' => 'male',
                'lock_version' => $application->lock_version,
                'student_name' => 'معدّل',
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->student_name)->toBe('معدّل')
            ->and($application->status)->toBe(ApplicationStatus::Submitted)
            ->and($application->events()->where('type', 'data_corrected')->exists())->toBeTrue();
    });

    it('keeps a submitted application complete while it is edited', function () {
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->for($student)->create(['phone' => $student->phone]);

        $this->actingAs($student)
            ->put(route('applications.update', $application), [
                'lock_version' => $application->lock_version,
                'student_name' => null,
            ])
            ->assertSessionHasErrors(['application' => __('applications.errors.required_after_submission')]);

        expect($application->fresh()->student_name)->not->toBeNull();
    });

    it('stops edits to a submitted application once the period closes', function () {
        $student = User::factory()->student()->create();
        $application = Application::factory()->submitted()->for($student)->create(['phone' => $student->phone]);
        $application->examPeriod->update(['closes_at' => now()->subMinute()]);

        $this->actingAs($student)
            ->put(route('applications.update', $application), [
                'lock_version' => $application->lock_version,
                'student_name' => 'متأخر',
            ])
            ->assertSessionHasErrors(['exam_period' => __('applications.errors.period_closed')]);
    });

    it('ignores attempts to mass-assign protected attributes', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)->put(route('applications.update', $application), [
            'lock_version' => 1,
            'status' => 'accepted',
            'reference_number' => 'FAKE-0000010',
            'user_id' => applicant()->id,
            'school_name' => 'إعدادية المستقبل',
        ]);

        $application->refresh();
        expect($application->status)->toBe(ApplicationStatus::Draft)
            ->and($application->reference_number)->toBeNull()
            ->and($application->school_name)->toBe('إعدادية المستقبل');
    });
});
