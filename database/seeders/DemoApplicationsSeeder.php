<?php

namespace Database\Seeders;

use App\Actions\Applications\AssignApplication;
use App\Actions\Applications\SubmitApplication;
use App\Actions\Applications\TransitionApplication;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ExamPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * 100 synthetic applicants covering every status, the reopen and withdraw
 * paths, every assignee option (reviewer, supervisor, unassigned) and every
 * branch, specialization and gender. Created through the real Actions
 * so history, numbering and audit rows look like production, with submissions
 * spread over the days since the demo period opened. For demos and restore
 * drills only; never runs in production.
 */
class DemoApplicationsSeeder extends Seeder
{
    public const TOTAL = 100;

    /**
     * The original 13 demo applicants, kept first so existing local databases
     * stay consistent when the seeder is re-run.
     */
    private const LEGACY_SCENARIOS = [
        'draft', 'draft',
        'submitted', 'submitted', 'submitted', 'submitted',
        'under_review', 'under_review', 'requires_correction',
        'resubmitted', 'accepted', 'accepted', 'rejected',
    ];

    /**
     * How many of the remaining applicants follow each scenario.
     */
    private const SCENARIO_COUNTS = [
        'submitted' => 16,
        'under_review' => 12,
        'requires_correction' => 9,
        'resubmitted' => 9,
        'accepted' => 13,
        'rejected' => 9,
        'withdrawn' => 6,
        'withdrawn_after_correction' => 4,
        'reopened_after_acceptance' => 5,
        'reopened_after_rejection' => 4,
    ];

    /** @var list<array{string, string}> */
    private const MALE_NAMES = [
        ['محمد', 'Mohammed'], ['أحمد', 'Ahmed'], ['عمر', 'Omar'], ['يوسف', 'Yousif'], ['حسين', 'Hussein'],
        ['علي', 'Ali'], ['مصطفى', 'Mustafa'], ['عبد الرحمن', 'Abdulrahman'], ['كرار', 'Karrar'], ['زيد', 'Zaid'],
        ['حيدر', 'Haider'], ['سجاد', 'Sajjad'],
    ];

    /** @var list<array{string, string}> */
    private const FEMALE_NAMES = [
        ['فاطمة', 'Fatima'], ['زينب', 'Zainab'], ['مريم', 'Maryam'], ['سارة', 'Sara'], ['نور الهدى', 'Noor Alhuda'],
        ['رقية', 'Ruqaya'], ['آية', 'Aya'], ['هبة', 'Hiba'], ['دعاء', 'Duaa'], ['بتول', 'Batool'],
        ['شهد', 'Shahad'], ['رسل', 'Rusul'],
    ];

    /** @var list<array{string, string}> */
    private const FATHER_NAMES = [
        ['علي', 'Ali'], ['جاسم', 'Jasim'], ['حسن', 'Hasan'], ['سعد', 'Saad'], ['عبد الله', 'Abdullah'],
        ['خالد', 'Khalid'], ['صادق', 'Sadiq'], ['رائد', 'Raed'], ['مهدي', 'Mahdi'], ['علاء', 'Alaa'], ['فلاح', 'Falah'],
    ];

    /** @var list<array{string, string}> */
    private const GRANDFATHER_NAMES = [
        ['حسين', 'Hussein'], ['كاظم', 'Kadhim'], ['عباس', 'Abbas'], ['محمود', 'Mahmoud'], ['كريم', 'Kareem'],
        ['إبراهيم', 'Ibrahim'], ['جعفر', 'Jaafar'], ['ناصر', 'Nasir'], ['جواد', 'Jawad'], ['هادي', 'Hadi'],
    ];

    /** @var list<array{string, string}> */
    private const FAMILY_NAMES = [
        ['الربيعي', 'Al-Rubaie'], ['العبيدي', 'Al-Obaidi'], ['الموسوي', 'Al-Musawi'], ['الجبوري', 'Al-Jubouri'],
        ['التميمي', 'Al-Tamimi'], ['الدليمي', 'Al-Dulaimi'], ['الحسيني', 'Al-Husseini'], ['الشمري', 'Al-Shammari'],
        ['الكعبي', 'Al-Kaabi'], ['الساعدي', 'Al-Saadi'], ['البياتي', 'Al-Bayati'], ['العزاوي', 'Al-Azzawi'], ['الزبيدي', 'Al-Zubaidi'],
    ];

    private const DISTRICTS = ['حي الحسين', 'حي العباس', 'حي الأسرة', 'حي الملحق', 'حي رمضان', 'حي النقيب', 'حي البلدية', 'حي المعلمين'];

    /**
     * Specialization codes with their branch (ReferenceDataSeeder).
     *
     * @var list<array{string, string}>
     */
    private const SPECIALIZATIONS = [
        ['COM_ADM', 'COM'], ['COM_ACC', 'COM'], ['APA_HOM', 'APA'], ['APA_DEC', 'APA'], ['AGR_GEN', 'AGR'], ['CIT_HW', 'CIT'],
        ['IND_ELC', 'IND'], ['IND_ECT', 'IND'], ['IND_MEC', 'IND'], ['IND_CON', 'IND'], ['IND_PET', 'IND'], ['IND_HVAC', 'IND'],
    ];

    private const SCHOOLS = [
        'إعدادية النجاح', 'إعدادية الرافدين', 'ثانوية الفراهيدي', 'إعدادية المتنبي', 'ثانوية بلاد الرافدين', 'إعدادية الخوارزمي',
    ];

    private const INTERMEDIATE_SCHOOLS = ['متوسطة الفرات', 'متوسطة الطف', 'متوسطة الكوثر', 'متوسطة الإمام الحسين'];

    private const GRADES = ['الثالث المتوسط', 'الرابع الإعدادي', 'الخامس الإعدادي', 'السادس الإعدادي'];

    private CarbonInterface $clock;

    public function __construct(
        private SubmitApplication $submit,
        private TransitionApplication $transition,
        private AssignApplication $assign,
    ) {}

    public function run(): void
    {
        if (App::isProduction()) {
            throw new RuntimeException('DemoApplicationsSeeder must never run in production.');
        }

        Notification::fake();

        $period = ExamPeriod::query()->where('code', 'EX2027')->firstOrFail();
        $reviewer = User::query()->where('username', 'reviewer')->firstOrFail();
        $supervisor = User::query()->where('username', 'supervisor')->firstOrFail();

        // Spread submissions between the period opening and now; the period
        // only accepts applications once it has opened.
        $start = $period->opens_at->copy()->max(now()->subDays(6))->addHour();
        $spacing = (int) floor($start->diffInMinutes(now()->subHour()) / self::TOTAL);

        try {
            foreach ($this->scenarios() as $index => $scenario) {
                $username = sprintf('demo.student%02d', $index + 1);

                if (User::query()->where('username', $username)->exists()) {
                    continue;
                }

                $this->clock = $start->copy()->addMinutes($index * $spacing);
                Carbon::setTestNow($this->clock);

                // Unassigned applications are picked up by the supervisor, since
                // reviewers only see what is assigned to them.
                $assignee = [$reviewer, $supervisor, null][$index % 3];

                $this->seedApplicant($index, $username, $scenario, $period, $assignee ?? $supervisor, $supervisor, $assignee);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * The scenario for every applicant: the legacy ones first, then the rest
     * in a fixed shuffle so statuses are mixed across pages.
     *
     * @return list<string>
     */
    private function scenarios(): array
    {
        $remaining = collect(self::SCENARIO_COUNTS)
            ->flatMap(fn (int $count, string $scenario): array => array_fill(0, $count, $scenario))
            ->all();

        mt_srand(2027);
        shuffle($remaining);
        mt_srand();

        return [...self::LEGACY_SCENARIOS, ...$remaining];
    }

    private function seedApplicant(
        int $index,
        string $username,
        string $scenario,
        ExamPeriod $period,
        User $reviewer,
        User $supervisor,
        ?User $assignee,
    ): void {
        $gender = $index % 2 === 0 ? Gender::Male : Gender::Female;
        $first = ($gender === Gender::Male ? self::MALE_NAMES : self::FEMALE_NAMES)[intdiv($index, 2) % 12];
        $father = self::FATHER_NAMES[$index % count(self::FATHER_NAMES)];
        $grandfather = self::GRANDFATHER_NAMES[($index * 3) % count(self::GRANDFATHER_NAMES)];
        $family = self::FAMILY_NAMES[($index * 7) % count(self::FAMILY_NAMES)];
        $nameAr = "{$first[0]} {$father[0]} {$grandfather[0]} {$family[0]}";

        $applicant = User::factory()->create([
            'username' => $username,
            'name' => $nameAr,
            'email' => 'demo-family@examis.test',
        ]);

        $application = Application::factory()->complete()->create([
            'user_id' => $applicant->id,
            'exam_period_id' => $period->id,
            'student_name' => $first[0],
            'father_name' => $father[0],
            'grandfather_name' => $grandfather[0],
            'great_grandfather_name' => $family[0],
            'mother_first_name' => self::FEMALE_NAMES[($index * 5) % 12][0],
            'mother_father_name' => self::GRANDFATHER_NAMES[($index + 4) % 10][0],
            'gender' => $gender,
            'birth_date' => Carbon::create(2000, 1, 1)->addDays(($index * 97) % 2500)->toDateString(),
            'district' => self::DISTRICTS[$index % count(self::DISTRICTS)],
            'school_name' => self::SCHOOLS[$index % count(self::SCHOOLS)].($gender === Gender::Male ? ' للبنين' : ' للبنات'),
            'intermediate_school_name' => self::INTERMEDIATE_SCHOOLS[$index % count(self::INTERMEDIATE_SCHOOLS)].($gender === Gender::Male ? ' للبنين' : ' للبنات'),
            'last_grade' => self::GRADES[$index % count(self::GRADES)],
            'failed_years' => $index % 3,
            'left_years' => intdiv($index, 3) % 4,
            'branch_code' => self::SPECIALIZATIONS[$index % count(self::SPECIALIZATIONS)][1],
            'category_code' => self::SPECIALIZATIONS[$index % count(self::SPECIALIZATIONS)][0],
        ]);

        foreach ($period->documentTypes as $type) {
            ApplicationDocument::factory()->verified()->create([
                'application_id' => $application->id,
                'document_type_id' => $type->id,
                'uploaded_by' => $applicant->id,
            ]);
        }

        if ($scenario === 'draft') {
            return;
        }

        $application = $this->submit->handle($application, $applicant);

        if ($assignee !== null) {
            $this->tick();
            $application = $this->assign->handle($application, $assignee, $supervisor);
        }

        $correctionReason = 'صورة البطاقة الوطنية غير واضحة، يرجى إعادة رفعها.';
        $rejectionReason = 'المستمسكات المرفوعة لا تطابق البيانات المدخلة.';

        $path = match ($scenario) {
            'submitted' => [],
            'under_review' => [[ApplicationStatus::UnderReview, $reviewer, null]],
            'requires_correction', 'resubmitted', 'withdrawn_after_correction' => [
                [ApplicationStatus::UnderReview, $reviewer, null],
                [ApplicationStatus::RequiresCorrection, $reviewer, $correctionReason],
            ],
            'accepted', 'reopened_after_acceptance' => [
                [ApplicationStatus::UnderReview, $reviewer, null],
                [ApplicationStatus::Accepted, $supervisor, null],
            ],
            'rejected', 'reopened_after_rejection' => [
                [ApplicationStatus::UnderReview, $reviewer, null],
                [ApplicationStatus::Rejected, $supervisor, $rejectionReason],
            ],
            'withdrawn' => [[ApplicationStatus::Withdrawn, $applicant, null]],
            default => throw new RuntimeException("Unknown demo scenario [{$scenario}]."),
        };

        $path = match ($scenario) {
            'withdrawn_after_correction' => [...$path, [ApplicationStatus::Withdrawn, $applicant, null]],
            'reopened_after_acceptance', 'reopened_after_rejection' => [
                ...$path,
                [ApplicationStatus::UnderReview, $supervisor, 'إعادة فتح الطلب لمراجعة إضافية بعد اعتراض الطالب.'],
            ],
            default => $path,
        };

        foreach ($path as [$target, $actor, $reason]) {
            $this->tick();
            $application = $this->transition->handle($application, $target, $actor, $reason);
        }

        if ($scenario === 'resubmitted') {
            $this->tick();
            $this->submit->handle($application, $applicant);
        }
    }

    /**
     * Moves the frozen clock forward so each step gets its own timestamp.
     */
    private function tick(): void
    {
        $this->clock = $this->clock->copy()->addMinutes(9);
        Carbon::setTestNow($this->clock);
    }
}
