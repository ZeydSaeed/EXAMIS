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

    it('requires a verified email address', function () {
        $period = ExamPeriod::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('applications.store'), ['exam_period_id' => $period->id])
            ->assertRedirect(route('verification.notice'));
    });
});

describe('saving a draft', function () {
    it('stores valid fields and increments the version', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'full_name_ar' => 'زينب علي حسين كاظم',
                'phone' => '٠٧٧٠١٢٣٤٥٦٧',
                'governorate_code' => 'BSR',
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->full_name_ar)->toBe('زينب علي حسين كاظم')
            ->and($application->phone)->toBe('07701234567')
            ->and($application->governorate_code)->toBe('BSR')
            ->and($application->lock_version)->toBe(2);
    });

    it('rejects a save based on a stale version instead of overwriting', function () {
        $application = Application::factory()->create(['full_name_ar' => 'الاسم الأصلي للطالب']);
        $application->forceFill(['lock_version' => 3])->save();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 2,
                'full_name_ar' => 'اسم من جلسة قديمة',
            ])
            ->assertSessionHasErrors(['lock_version' => __('applications.errors.stale_version')]);

        expect($application->fresh()->full_name_ar)->toBe('الاسم الأصلي للطالب');
    });

    it('validates fields with Arabic messages', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'full_name_ar' => 'Ahmed Ali',
                'governorate_code' => 'ATLANTIS',
            ])
            ->assertSessionHasErrors([
                'full_name_ar' => 'يجب كتابة الاسم بالأحرف العربية فقط.',
                'governorate_code' => 'القيمة المختارة في المحافظة غير صالحة.',
            ]);
    });

    it('only offers branches eligible for the period', function () {
        $period = ExamPeriod::factory()->create(['eligible_branch_codes' => ['SCI']]);
        $application = Application::factory()->create(['exam_period_id' => $period->id]);

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'branch_code' => 'LIT'])
            ->assertSessionHasErrors('branch_code');
    });

    it('answers 404 when another applicant tries to edit', function () {
        $application = Application::factory()->create();

        $this->actingAs(applicant())
            ->put(route('applications.update', $application), ['lock_version' => 1, 'full_name_ar' => 'محاولة تعديل غير مشروعة'])
            ->assertNotFound();
    });

    it('refuses edits after submission', function () {
        $application = Application::factory()->submitted()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'full_name_ar' => 'تعديل بعد الإرسال'])
            ->assertForbidden();
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
