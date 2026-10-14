<?php

namespace Database\Seeders;

use App\Enums\ExamPeriodStatus;
use App\Enums\Role;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * Synthetic demo accounts and an open period for local development and
 * end-to-end tests. Refuses to run in production. All data is fictitious.
 * Every account's password is "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $period = ExamPeriod::query()->updateOrCreate(['code' => 'EX2027'], [
            'name_ar' => 'الدورة الأولى للامتحانات الخارجية ٢٠٢٧',
            'name_en' => 'External examinations, first round 2027',
            'academic_year' => '2026-2027',
            'status' => ExamPeriodStatus::Open,
            'opens_at' => now()->subWeek(),
            'closes_at' => now()->addMonths(2),
            'instructions_ar' => 'يُرجى إدخال البيانات كما في المستمسكات الرسمية، ورفع صورة شخصية حديثة والبطاقة الوطنية ووثيقة المدرسة.',
            'instructions_en' => 'Enter your data exactly as in your official documents and upload a recent photo, your national ID and your school document.',
        ]);

        $required = DocumentType::query()->whereIn('code', ['personal_photo', 'national_id', 'school_certificate'])->pluck('id');
        $optional = DocumentType::query()->where('code', 'residence_card')->pluck('id');

        $period->documentTypes()->sync(
            $required->mapWithKeys(fn (int $id): array => [$id => ['is_required' => true]])->all()
            + $optional->mapWithKeys(fn (int $id): array => [$id => ['is_required' => false]])->all(),
        );

        $staff = [
            ['admin', 'مدير النظام التجريبي', [Role::Administrator]],
            ['supervisor', 'مشرف تجريبي', [Role::Supervisor]],
            ['reviewer', 'مدقق تجريبي', [Role::Reviewer]],
            ['auditor', 'مراقب تجريبي', [Role::Auditor]],
        ];

        foreach ($staff as [$username, $name, $roles]) {
            if (User::query()->where('username', $username)->exists()) {
                continue;
            }

            User::factory()->staff(...$roles)->create([
                'username' => $username,
                'name' => $name,
                'email' => $username.'@examis.test',
            ]);
        }

        if (! User::query()->where('username', 'student')->exists()) {
            User::factory()->create([
                'username' => 'student',
                'name' => 'طالب تجريبي',
                'email' => 'family@examis.test',
            ]);
        }
    }
}
