<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\PerfSeeder;
use Database\Seeders\Support\SyntheticTarget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The k6 suite (tests/load) replays this journey over HTTP with the same
 * accounts and the same answers (tests/load/fixtures/application.json). If
 * the application changes and this test fails, the k6 scripts are out of
 * date too.
 */
beforeEach(function () {
    if (DB::getDriverName() === 'pgsql' && SyntheticTarget::facts()['marker'] !== SyntheticTarget::MARKER) {
        $this->markTestSkipped("examis_testing has no '".SyntheticTarget::MARKER."' marker yet (needs the owner's approval).");
    }

    config(['examis.load_test_users' => 3]);
    $this->seed(PerfSeeder::class);
});

/**
 * @return array{steps: array<string, array<string, string>>, photo: array{document_type: string, file: string, mime: string}}
 */
function loadFixture(): array
{
    return json_decode((string) file_get_contents(base_path('tests/load/fixtures/application.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, int>
 */
function integrityViolations(): array
{
    $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents(base_path('tests/load/sql/post-run-integrity.sql')));
    $results = [];

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $row = (array) DB::selectOne($statement);
        $results[(string) $row['check_name']] = (int) $row['violations'];
    }

    return $results;
}

function signInByQr(int $n): void
{
    test()->followingRedirects()
        ->get(route('student.qr', PerfSeeder::publicId($n)))
        ->assertInertia(fn (Assert $page) => $page->component('auth/student-code'));

    test()->followingRedirects()
        ->post(route('student.verify'), ['access_code' => PerfSeeder::accessCode($n)])
        ->assertInertia(fn (Assert $page) => $page->component('apply/specialization'));
}

test('seeds synthetic students that sign in by QR link and access code', function () {
    signInByQr(2);

    $this->assertAuthenticatedAs(User::query()->where('student_number', PerfSeeder::studentNumber(2))->sole(), 'web');
    expect(User::query()->where('public_id', 'like', PerfSeeder::PUBLIC_ID_PREFIX.'%')->count())->toBe(3);
});

test('refuses another student\'s access code', function () {
    $this->get(route('student.qr', PerfSeeder::publicId(1)));

    $this->post(route('student.verify'), ['access_code' => PerfSeeder::accessCode(2)])
        ->assertSessionHasErrors('access_code');

    $this->assertGuest('web');
});

test('the k6 journey completes and leaves the data intact', function () {
    $fixture = loadFixture();
    signInByQr(1);

    $branches = null;
    $this->get(route('apply.specialization'))
        ->assertInertia(function (Assert $page) use (&$branches) {
            $page->component('apply/specialization');
            $branches = $page->toArray()['props']['branches'];
        });
    $branch = $branches[0]['code'];
    $category = $branches[0]['specializations'][0]['code'];

    $this->followingRedirects()
        ->post(route('apply.specialization.store'), ['branch_code' => $branch, 'category_code' => $category])
        ->assertInertia(fn (Assert $page) => $page->component('applicant/applications/edit')
            ->where('application.status', 'draft')
            ->where('application.form.branch_code', $branch)
            ->where('errors', []));

    $application = Application::query()->sole();

    foreach ($fixture['steps'] as $step => $answers) {
        $this->followingRedirects()
            ->put(route('applications.update', $application), [
                ...$answers,
                'lock_version' => $application->refresh()->lock_version,
                'step' => (int) $step,
            ])
            ->assertInertia(fn (Assert $page) => $page->component('applicant/applications/edit')
                ->where('step', (int) $step + 1)
                ->where('errors', [])
                ->where('application.lock_version', $application->lock_version + 1));
    }

    $application->refresh();
    foreach ($fixture['steps'] as $answers) {
        foreach ($answers as $field => $value) {
            $stored = $application->{$field};
            $stored = match (true) {
                $stored instanceof BackedEnum => $stored->value,
                $stored instanceof DateTimeInterface => $stored->format('Y-m-d'),
                default => $stored,
            };
            expect((string) $stored)->toBe($value, "field {$field} was not stored");
        }
    }
    expect($application->phone)->toBe(PerfSeeder::phone(1));

    $photo = UploadedFile::fake()->createWithContent(
        $fixture['photo']['file'],
        (string) file_get_contents(base_path('tests/load/fixtures/'.$fixture['photo']['file'])),
    );
    $this->followingRedirects()
        ->post(route('applications.documents.store', $application), [
            'document_type' => $fixture['photo']['document_type'],
            'file' => $photo,
        ])
        ->assertInertia(fn (Assert $page) => $page->component('applicant/applications/edit')
            ->where('errors', [])
            ->where('missing.documents', [])
            ->where('application.documents.0.document_type', 'personal_photo')
            ->where('application.documents.0.status', 'verified'));

    $this->followingRedirects()
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertInertia(fn (Assert $page) => $page->component('applicant/applications/printed')
            ->where('studentNumber', PerfSeeder::studentNumber(1)));

    $reference = $application->refresh()->reference_number;
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($reference)->not->toBeNull();

    // A retry after the answer arrived lands on the printed page and changes nothing.
    $this->followingRedirects()
        ->post(route('applications.submit', $application), ['declaration' => '1'])
        ->assertInertia(fn (Assert $page) => $page->component('applicant/applications/printed'));
    expect($application->refresh()->reference_number)->toBe($reference);

    $receipt = $this->get(route('applications.receipt', $application))->assertOk();
    expect((string) $receipt->headers->get('Content-Type'))->toContain('pdf')
        ->and(str_starts_with((string) $receipt->getContent(), '%PDF'))->toBeTrue();

    expect(array_filter(integrityViolations()))->toBe([]);
});

test('synthetic staff follow the k6 staff steps', function () {
    $password = 'qe-contract-'.bin2hex(random_bytes(8));
    $_ENV['QE_STAFF_PASSWORD'] = $_SERVER['QE_STAFF_PASSWORD'] = $password;
    putenv("QE_STAFF_PASSWORD={$password}");

    try {
        $this->seed(PerfSeeder::class);
    } finally {
        unset($_ENV['QE_STAFF_PASSWORD'], $_SERVER['QE_STAFF_PASSWORD']);
        putenv('QE_STAFF_PASSWORD');
    }

    $application = Application::factory()->submitted()->create();

    $this->get(route('staff.login'))->assertInertia(fn (Assert $page) => $page->component('auth/staff-login'));
    $this->followingRedirects()
        ->post(route('login.store'), ['email' => 'perf.supervisor.1@qe.localhost', 'password' => $password])
        ->assertInertia(fn (Assert $page) => $page->component('admin/home'));

    $this->get(route('admin.applications.index', ['status' => 'submitted']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/applications/index')
            ->where('applications.data.0.uuid', $application->uuid));
    $this->get(route('admin.applications.show', $application))
        ->assertInertia(fn (Assert $page) => $page->component('admin/applications/show')
            ->where('application.uuid', $application->uuid));
    $this->followingRedirects()
        ->post(route('admin.applications.transition', $application), ['target' => 'under_review'])
        ->assertInertia(fn (Assert $page) => $page->component('admin/applications/show')
            ->where('application.status', 'under_review'));

    $this->followingRedirects()->post(route('logout'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/staff-login'));

    $this->followingRedirects()
        ->post(route('login.store'), ['email' => 'perf.reviewer.1@qe.localhost', 'password' => $password])
        ->assertInertia(fn (Assert $page) => $page->component('admin/home'));
    $this->get(route('admin.users.index'))->assertForbidden();

    expect(User::query()->where('email', 'like', '%@'.PerfSeeder::STAFF_DOMAIN)->count())
        ->toBe(PerfSeeder::REVIEWERS + PerfSeeder::SUPERVISORS);
});

test('a student session cannot open another student\'s application or the staff portal', function () {
    signInByQr(1);
    $foreign = Application::factory()->create();

    $this->get(route('applications.edit', $foreign))->assertNotFound();
    $this->followingRedirects()->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/staff-login'));
});

test('a save moves the lock version only when something changed, as the k6 loop assumes', function () {
    $answers = loadFixture()['steps']['2'];
    signInByQr(1);
    $this->post(route('apply.specialization.store'), ['branch_code' => 'IND', 'category_code' => 'IND_ELC']);
    $application = Application::query()->sole();
    $save = fn (array $changes) => $this->put(route('applications.update', $application), [
        ...$answers, ...$changes, 'lock_version' => $application->refresh()->lock_version, 'step' => 2,
    ]);

    $save([]);
    $version = $application->refresh()->lock_version;

    $save([]);
    expect($application->refresh()->lock_version)->toBe($version);

    $save(['house_number' => '123456789']);
    expect($application->refresh()->lock_version)->toBe($version + 1)
        ->and($application->house_number)->toBe('123456789');
});

test('public pages, sign-out and the signed-out redirect answer as k6 expects', function () {
    foreach (['start' => 'start', 'apply.terms' => 'apply/terms', 'home' => 'welcome', 'track.create' => 'public/track', 'staff.login' => 'auth/staff-login'] as $route => $component) {
        $this->get(route($route))
            ->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    $this->followingRedirects()->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('apply/terms'));

    signInByQr(1);
    $this->followingRedirects()->post(route('student.logout'))
        ->assertInertia(fn (Assert $page) => $page->component('start'));
    $this->assertGuest('web');
});

test('the integrity checks see a planted inconsistency', function () {
    expect(array_filter(integrityViolations()))->toBe([]);

    DB::table('exam_periods')->update(['last_sequence' => 7]);
    DB::table('users')->where('student_number', PerfSeeder::studentNumber(3))->update(['phone' => '07701234567']);

    expect(integrityViolations())
        ->toMatchArray(['period_counter_mismatch' => 1, 'non_synthetic_applicants' => 1]);
});
