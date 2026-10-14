<?php

namespace Database\Seeders;

use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\TransitionApplication;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ExamPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Synthetic applications in every status, created through the real Actions
 * so history, numbering and audit rows look like production. For demos and
 * restore drills only; never runs in production.
 */
class DemoApplicationsSeeder extends Seeder
{
    private const NAMES = [
        'محمد علي حسين الربيعي', 'فاطمة جاسم كاظم العبيدي', 'زينب حسن عباس الموسوي', 'أحمد سعد محمود الجبوري',
        'مريم عبد الله كريم التميمي', 'عمر خالد إبراهيم الدليمي', 'نور الهدى صادق جعفر الحسيني', 'يوسف رائد ناصر الشمري',
        'سارة مهدي عبد الأمير الكعبي', 'حسين علاء جواد الساعدي', 'رقية فلاح هادي البياتي', 'عبد الرحمن محمد عبد الكريم العزاوي',
    ];

    public function run(SubmitApplication $submit, TransitionApplication $transition): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('DemoApplicationsSeeder must never run in production.');
        }

        Notification::fake();

        $period = ExamPeriod::query()->where('code', 'EX2027')->firstOrFail();
        $documentTypes = $period->documentTypes;
        $reviewer = User::query()->where('username', 'reviewer')->firstOrFail();
        $supervisor = User::query()->where('username', 'supervisor')->firstOrFail();

        $plan = [
            ApplicationStatus::Draft, ApplicationStatus::Draft,
            ApplicationStatus::Submitted, ApplicationStatus::Submitted, ApplicationStatus::Submitted, ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview, ApplicationStatus::UnderReview, ApplicationStatus::RequiresCorrection,
            ApplicationStatus::Resubmitted, ApplicationStatus::Accepted, ApplicationStatus::Accepted, ApplicationStatus::Rejected,
        ];

        foreach ($plan as $index => $target) {
            $username = sprintf('demo.student%02d', $index + 1);

            if (User::query()->where('username', $username)->exists()) {
                continue;
            }

            $applicant = User::factory()->create([
                'username' => $username,
                'name' => self::NAMES[$index % count(self::NAMES)],
                'email' => 'demo-family@examis.test',
            ]);

            $application = Application::factory()->complete()->create([
                'user_id' => $applicant->id,
                'exam_period_id' => $period->id,
                'full_name_ar' => self::NAMES[$index % count(self::NAMES)],
                'governorate_code' => ['BGD', 'BSR', 'NIN', 'NJF', 'KRB', 'BBL'][$index % 6],
                'branch_code' => ['SCI', 'LIT', 'APP'][$index % 3],
            ]);

            foreach ($documentTypes as $type) {
                ApplicationDocument::factory()->verified()->create([
                    'application_id' => $application->id,
                    'document_type_id' => $type->id,
                    'uploaded_by' => $applicant->id,
                ]);
            }

            if ($target === ApplicationStatus::Draft) {
                continue;
            }

            $application = $submit->handle($application, $applicant);

            match ($target) {
                ApplicationStatus::UnderReview => $transition->handle($application, ApplicationStatus::UnderReview, $reviewer),
                ApplicationStatus::RequiresCorrection => $this->requestCorrection($application, $reviewer, $transition),
                ApplicationStatus::Resubmitted => $submit->handle($this->requestCorrection($application, $reviewer, $transition), $applicant),
                ApplicationStatus::Accepted, ApplicationStatus::Rejected => $transition->handle(
                    $transition->handle($application, ApplicationStatus::UnderReview, $supervisor),
                    $target,
                    $supervisor,
                    $target === ApplicationStatus::Rejected ? 'المستمسكات المرفوعة لا تطابق البيانات المدخلة.' : null,
                ),
                default => $application,
            };
        }
    }

    private function requestCorrection(Application $application, User $reviewer, TransitionApplication $transition): Application
    {
        $underReview = $transition->handle($application, ApplicationStatus::UnderReview, $reviewer);

        return $transition->handle($underReview, ApplicationStatus::RequiresCorrection, $reviewer, 'صورة البطاقة الوطنية غير واضحة، يرجى إعادة رفعها.');
    }
}
