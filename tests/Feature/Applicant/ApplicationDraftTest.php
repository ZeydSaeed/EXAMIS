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

    it('does not require a verified email address', function () {
        $period = ExamPeriod::factory()->create();

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('applications.store'), ['exam_period_id' => $period->id])
            ->assertRedirect(route('applications.edit', Application::query()->sole()));
    });
});

describe('saving a draft', function () {
    it('stores valid fields and increments the version', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'full_name_ar' => 'زينب علي حسين كاظم',
                'surname' => 'الموسوي',
                'phone' => '٠٧٧٠١٢٣٤٥٦٧',
                'mahalla' => '٦٣٠',
                'failed_years' => '٠',
                'branch_code' => 'COM',
                'category_code' => 'COM_ACC',
                'issued_documents' => [
                    ['number' => '1452', 'date' => '2025-09-01', 'recipient' => 'مديرية تربية كربلاء'],
                    ['number' => null, 'date' => null, 'recipient' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();
        expect($application->full_name_ar)->toBe('زينب علي حسين كاظم')
            ->and($application->surname)->toBe('الموسوي')
            ->and($application->phone)->toBe('07701234567')
            ->and($application->mahalla)->toBe('630')
            ->and($application->failed_years)->toBe(0)
            ->and($application->category_code)->toBe('COM_ACC')
            ->and($application->issued_documents)->toBe([
                ['number' => '1452', 'date' => '2025-09-01', 'recipient' => 'مديرية تربية كربلاء'],
            ])
            ->and($application->lock_version)->toBe(2);
    });

    it('keeps every application in Karbala', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'governorate_code' => 'BSR'])
            ->assertSessionHasNoErrors();

        expect($application->fresh()->governorate_code)->toBe(Application::GOVERNORATE_CODE);
    });

    it('requires the specialization to belong to the chosen branch', function () {
        $application = Application::factory()->create();

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'branch_code' => 'COM',
                'category_code' => 'IND_ELC',
            ])
            ->assertSessionHasErrors(['category_code' => __('applications.errors.specialization_branch')]);
    });

    it('accepts at most four issued documents', function () {
        $application = Application::factory()->create();
        $row = ['number' => '1', 'date' => '2025-01-01', 'recipient' => 'مدرسة'];

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), [
                'lock_version' => 1,
                'issued_documents' => array_fill(0, 5, $row),
            ])
            ->assertSessionHasErrors('issued_documents');
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
                'branch_code' => 'ATLANTIS',
            ])
            ->assertSessionHasErrors([
                'full_name_ar' => 'يجب كتابة الاسم بالأحرف العربية فقط.',
                'branch_code' => 'القيمة المختارة في الفرع غير صالحة.',
            ]);
    });

    it('only offers branches eligible for the period', function () {
        $period = ExamPeriod::factory()->create(['eligible_branch_codes' => ['IND']]);
        $application = Application::factory()->create(['exam_period_id' => $period->id]);

        $this->actingAs($application->user)
            ->put(route('applications.update', $application), ['lock_version' => 1, 'branch_code' => 'COM'])
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
