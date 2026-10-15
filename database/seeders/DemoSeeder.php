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
            'instructions_ar' => <<<'TEXT'
                املأ البيانات كما وردت في استمارة واقع حال وفي المستمسكات الرسمية، واطّلع على صفحتي «شروط التقديم» و«المستمسكات المطلوبة» قبل البدء.
                تُختم استمارة واقع حال الورقية من آخر مدرسة، وتوضع المستمسكات (أصل وصورة ملونة، كل مستمسك في ورقة واحدة) مع 10 صور شخصية في فايل كارتون بلون الفرع: الصناعي أحمر، الحاسوب وتقنية المعلومات أبيض، التجاري أصفر، الفنون التطبيقية أزرق، الزراعي أخضر.
                TEXT,
            'instructions_en' => <<<'TEXT'
                Enter your details exactly as on the student status form and your official documents, and read the "Application conditions" and "Required documents" pages first.
                The paper status form is stamped by your last school; the documents (original and colour copy, each on one page) and 10 photos go in a cardboard folder in your branch's colour: industrial red, computing and IT white, commercial yellow, applied arts blue, agricultural green.
                TEXT,
        ]);

        $required = DocumentType::query()
            ->whereIn('code', ['participation_request', 'national_id', 'father_id', 'mother_id', 'residence_card', 'personal_photo'])
            ->pluck('id');

        $period->documentTypes()->sync(
            $required->mapWithKeys(fn (int $id): array => [$id => ['is_required' => true]])->all(),
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
