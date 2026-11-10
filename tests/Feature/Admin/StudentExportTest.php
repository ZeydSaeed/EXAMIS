<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @return list<list<string>>
 */
function workbookRows(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($path, $content);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(strval(...), $row->toArray());
        }
    }

    $reader->close();
    unlink($path);

    return $rows;
}

function submittedOn(string $utc, array $attributes = []): Application
{
    return Application::factory()->submitted()->create([...$attributes, 'submitted_at' => CarbonImmutable::parse($utc, 'UTC')]);
}

it('lists the requested columns in order and leaves drafts out', function () {
    $application = submittedOn('2026-10-10 09:00', [
        'student_name' => 'أحمد', 'father_name' => 'محمد', 'grandfather_name' => 'علي', 'great_grandfather_name' => 'حسن',
        'mother_first_name' => 'زينب', 'mother_father_name' => 'مهدي', 'mother_grandfather_name' => 'كريم',
        'phone' => '07701234567', 'district' => 'حي الموظفين', 'mahalla' => '12', 'alley' => '3', 'house_number' => '7',
        'school_name' => 'ثانوية كربلاء',
    ]);
    Application::factory()->create(['status' => ApplicationStatus::Draft]);

    $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.student-export.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/student-export/index')
            ->where('columns.0', 'اسم الطالب')
            ->where('columns.8', 'اسم جد الأم')
            ->where('columns.15', 'رقم الموبايل')
            ->where('columns.17', 'المحافظة')
            ->has('columns', 18)
            ->where('students.total', 1)
            ->where('students.data.0.uuid', $application->uuid)
            ->where('students.data.0.cells.3', 'حسن')
            ->where('students.data.0.cells.8', 'كريم')
            ->where('students.data.0.cells.15', '07701234567')
            ->has('students.data.0.cells', 18));
});

it('covers whole days of the chosen range as read in Iraq', function () {
    submittedOn('2026-10-09 20:59');
    $firstDay = submittedOn('2026-10-09 21:00');
    $lastDay = submittedOn('2026-10-11 20:59');
    submittedOn('2026-10-11 21:00');

    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.student-export.index', ['from' => '2026-10-10', 'to' => '2026-10-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.total', 2)
            ->where('students.data.0.uuid', $firstDay->uuid)
            ->where('students.data.1.uuid', $lastDay->uuid));
});

it('accepts one end of the range alone', function () {
    submittedOn('2026-10-01 09:00');
    submittedOn('2026-10-20 09:00');
    $staff = staffWith(Role::Supervisor);

    $this->actingAs($staff)->get(route('admin.student-export.index', ['from' => '2026-10-10']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 1));

    $this->actingAs($staff)->get(route('admin.student-export.index', ['to' => '2026-10-10']))
        ->assertInertia(fn (Assert $page) => $page->where('students.total', 1));
});

it('rejects a range that ends before it starts', function () {
    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.student-export.index', ['from' => '2026-10-10', 'to' => '2026-10-09']))
        ->assertSessionHasErrors('to');
});

it('exports all the data to a workbook and audits it', function () {
    submittedOn('2026-10-01 09:00', ['phone' => '07701234567', 'mother_grandfather_name' => 'كريم']);
    submittedOn('2026-10-20 09:00');

    $response = $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.student-export.download'))->assertOk();

    expect($response->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toContain('spreadsheetml');

    $rows = workbookRows($response->streamedContent());

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toHaveCount(19)
        ->and($rows[0][0])->toBe('ت')
        ->and($rows[0][1])->toBe('اسم الطالب')
        ->and($rows[1][0])->toBe('1')
        ->and($rows[1][9])->toBe('كريم')
        ->and($rows[1][16])->toBe('07701234567')
        ->and($rows[2][0])->toBe('2')
        ->and(AuditEvent::query()->where('action', 'students.exported')->sole()->metadata['filters'])->toBe([]);
});

it('exports only the chosen range and audits it', function () {
    submittedOn('2026-10-01 09:00');
    submittedOn('2026-10-20 09:00');

    $content = $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.student-export.download', ['from' => '2026-10-10', 'to' => '2026-10-31']))
        ->assertOk()
        ->streamedContent();

    expect(workbookRows($content))->toHaveCount(2)
        ->and(AuditEvent::query()->where('action', 'students.exported')->sole()->metadata['filters'])
        ->toBe(['from' => '2026-10-10', 'to' => '2026-10-31']);
});

it('keeps a value that looks like a formula as text', function () {
    submittedOn('2026-10-01 09:00', ['district' => '=HYPERLINK("http://evil")']);

    $response = $this->actingAs(staffWith(Role::Supervisor))->get(route('admin.student-export.download'));
    $path = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    expect($sheet)->not->toContain('<f>')
        ->and($sheet)->toContain('=HYPERLINK');
});

it('shows personal data only to those who see every application', function (Role $role, int $status) {
    $staff = staffWith($role);

    $this->actingAs($staff)->get(route('admin.student-export.index'))->assertStatus($status);
    $this->actingAs($staff)->get(route('admin.student-export.download'))->assertStatus($status);
})->with([
    'administrator' => [Role::Administrator, 200],
    'supervisor' => [Role::Supervisor, 200],
    'auditor' => [Role::Auditor, 200],
    'reviewer' => [Role::Reviewer, 403],
]);
