<?php

use App\Enums\OverrideEffect;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Services\Database\TableEditor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function rowKey(string $table, array $key): string
{
    return app(TableEditor::class)->keyToken($table, $key);
}

/**
 * The fingerprint the edit page hands the form for this row as it is now.
 *
 * @param  array<string, mixed>  $key
 */
function rowFingerprint(string $table, array $key): string
{
    $tables = app(TableEditor::class);

    return $tables->fingerprint($table, (array) $tables->find($table, $key));
}

it('lets administrators list tables but hides infrastructure tables', function () {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.database.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/database/index')
            ->where('tables', fn ($tables) => collect($tables)->pluck('name')->contains('applications')
                && ! collect($tables)->pluck('name')->contains('sessions')
                && ! collect($tables)->pluck('name')->contains('password_reset_tokens')
                && collect($tables)->firstWhere('name', 'audit_events')['writable'] === false));
});

it('keeps the screen from staff without the database permissions', function (Role $role) {
    $this->actingAs(staffWith($role))->get(route('admin.database.index'))->assertForbidden();
    $this->actingAs(staffWith($role))->get(route('admin.backups.index'))->assertForbidden();
})->with([Role::Supervisor, Role::Reviewer, Role::Auditor]);

it('lets a view-only grant read rows but not change them', function () {
    $staff = staffWith(Role::Reviewer);
    $staff->permissionOverrides()->create(['permission' => Permission::DatabaseView, 'effect' => OverrideEffect::Grant]);
    $period = ExamPeriod::factory()->create();

    $this->actingAs($staff)->get(route('admin.database.table', 'exam_periods'))->assertOk();
    $this->actingAs($staff)
        ->put(route('admin.database.update', 'exam_periods'), ['key' => rowKey('exam_periods', ['id' => $period->id]), 'values' => ['name_ar' => 'x']])
        ->assertForbidden();
});

it('masks secret columns and never writes them', function () {
    $admin = staffWith(Role::Administrator);
    $student = User::factory()->student()->create();
    $token = rowKey('users', ['id' => $student->id]);

    $this->actingAs($admin)
        ->get(route('admin.database.edit', ['table' => 'users', 'key' => $token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('row.access_code', '••••••')
            ->where('row.phone', $student->phone));

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'users'), ['key' => $token, 'fingerprint' => rowFingerprint('users', ['id' => $student->id]), 'values' => ['access_code' => 'HACKED', 'name' => 'اسم جديد']])
        ->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->name)->toBe('اسم جديد')
        ->and($student->access_code)->toBe('ABCDE-23456')
        ->and(AuditEvent::query()->where('action', 'database.row_updated')->first()->metadata['changed'])
        ->toBe(['name']);
});

it('edits, adds and deletes rows with typed values', function () {
    $admin = staffWith(Role::Administrator);
    $type = DocumentType::factory()->create(['max_size_kb' => 100, 'is_photo' => false]);
    $token = rowKey('document_types', ['id' => $type->id]);

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'document_types'), ['key' => $token, 'values' => ['max_size_kb' => 'abc']])
        ->assertSessionHasErrors('max_size_kb');

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'document_types'), ['key' => $token, 'fingerprint' => rowFingerprint('document_types', ['id' => $type->id]), 'values' => ['max_size_kb' => '2048', 'is_photo' => 'true']])
        ->assertSessionHasNoErrors();

    expect($type->fresh())->max_size_kb->toBe(2048)->is_photo->toBeTrue();

    $period = ExamPeriod::factory()->create();
    $this->actingAs($admin)
        ->post(route('admin.database.store', 'document_type_exam_period'), ['values' => [
            'exam_period_id' => (string) $period->id, 'document_type_id' => (string) $type->id, 'is_required' => 'false',
        ]])
        ->assertRedirect(route('admin.database.table', 'document_type_exam_period'));

    expect($period->documentTypes()->count())->toBe(1);

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'document_type_exam_period'), [
            'key' => rowKey('document_type_exam_period', ['exam_period_id' => $period->id, 'document_type_id' => $type->id]),
        ])
        ->assertSessionHasNoErrors();

    expect($period->documentTypes()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'database.row_deleted')->exists())->toBeTrue();
});

it('shows a refused write as a message instead of a server error', function () {
    $admin = staffWith(Role::Administrator);
    $application = Application::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'exam_periods'), ['key' => rowKey('exam_periods', ['id' => $application->exam_period_id])])
        ->assertSessionHasErrors('database');

    expect(ExamPeriod::query()->whereKey($application->exam_period_id)->exists())->toBeTrue()
        ->and(session('errors')->first('database'))->not->toContain('SQLSTATE');
});

it('deletes an application with its documents and history, and keeps the student', function () {
    Storage::fake('documents');
    Storage::fake('local');
    $admin = staffWith(Role::Administrator);
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->create();
    $document = ApplicationDocument::factory()->for($application)->create(['uploaded_by' => $student->id]);
    $application->events()->create(['type' => 'created']);

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'applications'), ['key' => rowKey('applications', ['id' => $application->id])])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.database.table', 'applications'));

    expect(Application::query()->whereKey($application->id)->exists())->toBeFalse()
        ->and(ApplicationDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($student->id)->exists())->toBeTrue();
    Storage::disk('documents')->assertMissing($document->path);

    $snapshot = AuditEvent::query()->where('action', 'application.deleted')->firstOrFail()->metadata['snapshot'];
    Storage::disk('local')->assertExists($snapshot.'/files/'.$document->path);
});

it('keeps history and access tables read-only', function () {
    $admin = staffWith(Role::Administrator);
    $assignment = $admin->roleAssignments()->first();

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'role_assignments'), ['key' => rowKey('role_assignments', ['id' => $assignment->id]), 'values' => ['role' => 'administrator']])
        ->assertForbidden();

    $this->actingAs($admin)->get(route('admin.database.create', 'users'))->assertForbidden();
});

it('deletes a student with everything they own after the typed confirmation', function () {
    Storage::fake('documents');
    Storage::fake('local');
    $admin = staffWith(Role::Administrator);
    $student = User::factory()->student()->create();
    $application = Application::factory()->for($student)->create();
    $document = ApplicationDocument::factory()->for($application)->create(['uploaded_by' => $student->id]);
    $token = rowKey('users', ['id' => $student->id]);

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'users'), ['key' => $token, 'confirm' => '1'])
        ->assertSessionHasErrors('confirm');
    expect(User::query()->whereKey($student->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'users'), ['key' => $token, 'confirm' => (string) $student->student_number])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.database.table', 'users'));

    expect(User::query()->whereKey($student->id)->exists())->toBeFalse()
        ->and(Application::query()->whereKey($application->id)->exists())->toBeFalse()
        ->and(ApplicationDocument::query()->whereKey($document->id)->exists())->toBeFalse();
    Storage::disk('documents')->assertMissing($document->path);

    $audit = AuditEvent::query()->where('action', 'student.deleted')->firstOrFail();
    $snapshot = $audit->metadata['snapshot'];
    Storage::disk('local')->assertExists($snapshot.'/rows.json');
    Storage::disk('local')->assertExists($snapshot.'/files/'.$document->path);
    expect(Storage::disk('local')->get($snapshot.'/rows.json'))->not->toContain('ABCDE-23456');
});

it('refuses to delete a staff member who has acted, or yourself', function () {
    $admin = staffWith(Role::Administrator);
    $other = staffWith(Role::Reviewer);
    AuditEvent::create(['actor_id' => $other->id, 'action' => 'auth.login']);

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'users'), ['key' => rowKey('users', ['id' => $other->id])])
        ->assertSessionHasErrors('database');

    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'users'), ['key' => rowKey('users', ['id' => $admin->id])])
        ->assertSessionHasErrors('database');

    expect(User::query()->whereKey([$admin->id, $other->id])->count())->toBe(2);
});

it('asks for a manual backup with a request file and shows the server status', function () {
    $directory = storage_path('framework/testing/backup-status');
    config(['examis.backups.status_dir' => $directory]);
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);
    File::put($directory.'/status.json', json_encode([
        'updated_at' => '2026-11-08T12:00:00Z',
        'kinds' => ['6h' => ['last' => '20261108T120000Z', 'size' => 2048, 'count' => 1, 'sets' => [['name' => '20261108T120000Z', 'size' => 2048]]]],
    ]));
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->get(route('admin.backups.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status.kinds.6h.count', 1)
            ->where('requestedAt', null));

    $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHasNoErrors();
    expect(File::exists($directory.'/request'))->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'backup.requested')->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHasErrors('backup');

    File::deleteDirectory($directory);
});

it('offers delete on the applications list only to staff who manage the database', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.data.0.manage.student_key', rowKey('users', ['id' => $application->user_id])));

    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('applications.data.0.manage', null));
});

it('returns to the applications list after deleting a student from it', function () {
    Storage::fake('documents');
    Storage::fake('local');
    $application = Application::factory()->submitted()->for(User::factory()->student())->create();
    $student = $application->user;

    $this->actingAs(staffWith(Role::Administrator))
        ->from(route('admin.applications.index'))
        ->delete(route('admin.database.destroy', 'users'), [
            'key' => rowKey('users', ['id' => $student->id]),
            'confirm' => (string) $student->student_number,
            'stay' => true,
        ])
        ->assertRedirect(route('admin.applications.index'));

    expect(User::query()->whereKey($student->id)->exists())->toBeFalse();
});

it('asks the server to delete a backup set but never the last full one', function () {
    $directory = storage_path('framework/testing/backup-status-delete');
    config(['examis.backups.status_dir' => $directory]);
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);
    File::put($directory.'/status.json', json_encode([
        'updated_at' => '2026-11-08T12:00:00Z',
        'kinds' => [
            '6h' => ['last' => '20261108T120000Z', 'size' => 10, 'count' => 1, 'sets' => [['name' => '20261108T120000Z', 'size' => 10]]],
            'daily' => ['last' => '20261108T031500Z', 'size' => 20, 'count' => 1, 'sets' => [['name' => '20261108T031500Z', 'size' => 20]]],
        ],
    ]));
    $admin = staffWith(Role::Administrator);

    $this->actingAs($admin)
        ->get(route('admin.backups.index'))
        ->assertInertia(fn (Assert $page) => $page->where('kept', 'daily/20261108T031500Z')->where('deleting', []));

    $this->actingAs($admin)
        ->delete(route('admin.backups.destroy', ['kind' => 'daily', 'set' => '20261108T031500Z']))
        ->assertSessionHasErrors('backup');

    $this->actingAs($admin)
        ->delete(route('admin.backups.destroy', ['kind' => '6h', 'set' => '20250101T000000Z']))
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('admin.backups.destroy', ['kind' => '6h', 'set' => '20261108T120000Z']))
        ->assertSessionHasNoErrors();

    expect(File::exists($directory.'/delete-6h-20261108T120000Z'))->toBeTrue()
        ->and(File::exists($directory.'/delete-daily-20261108T031500Z'))->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'backup.delete_requested')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.backups.index'))
        ->assertInertia(fn (Assert $page) => $page->where('deleting', ['6h/20261108T120000Z']));

    $this->actingAs(staffWith(Role::Supervisor))
        ->delete(route('admin.backups.destroy', ['kind' => '6h', 'set' => '20261108T120000Z']))
        ->assertForbidden();

    File::deleteDirectory($directory);
});

it('never writes the columns only the application itself may change', function () {
    $admin = staffWith(Role::Administrator);
    $application = Application::factory()->complete()->submitted()->create(['national_id_number' => '199012345678']);
    $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);
    $before = [$application->status, $application->reference_number, $application->lock_version];

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'applications'), [
            'key' => rowKey('applications', ['id' => $application->id]),
            'fingerprint' => rowFingerprint('applications', ['id' => $application->id]),
            'values' => ['status' => 'accepted', 'reference_number' => 'X-1', 'lock_version' => '99', 'school_name' => 'مدرسة أخرى'],
        ])
        ->assertSessionHasNoErrors();

    $application->refresh();
    expect([$application->status, $application->reference_number, $application->lock_version])->toBe($before)
        ->and($application->school_name)->toBe('مدرسة أخرى');

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'application_documents'), [
            'key' => rowKey('application_documents', ['id' => $document->id]),
            'fingerprint' => rowFingerprint('application_documents', ['id' => $document->id]),
            'values' => ['status' => 'verified', 'path' => 'someone-else/file'],
        ])
        ->assertSessionHasNoErrors();

    expect($document->fresh())->status->toBe($document->status)->path->toBe($document->path);

    $this->actingAs($admin)
        ->get(route('admin.database.edit', ['table' => 'applications', 'key' => rowKey('applications', ['id' => $application->id])]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns', fn ($columns) => collect($columns)->firstWhere('name', 'status')['protected'] === true
                && collect($columns)->firstWhere('name', 'school_name')['protected'] === false));
});

it('adds no applications, documents or accounts behind the student form', function (string $table) {
    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.database.create', $table))
        ->assertForbidden();

    $this->actingAs(staffWith(Role::Administrator))
        ->post(route('admin.database.store', $table), ['values' => ['status' => 'verified']])
        ->assertForbidden();
})->with(['applications', 'application_documents', 'users']);

it('refuses a save based on a copy someone else has changed since', function () {
    $admin = staffWith(Role::Administrator);
    $period = ExamPeriod::factory()->create(['name_ar' => 'الأصل']);
    $key = ['id' => $period->id];
    $stale = rowFingerprint('exam_periods', $key);

    $period->forceFill(['name_ar' => 'عدّله غيرك'])->save();

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'exam_periods'), ['key' => rowKey('exam_periods', $key), 'fingerprint' => $stale, 'values' => ['name_ar' => 'تعديلي']])
        ->assertSessionHasErrors('database');

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'exam_periods'), ['key' => rowKey('exam_periods', $key), 'values' => ['name_ar' => 'بلا بصمة']])
        ->assertSessionHasErrors('database');

    expect($period->fresh()->name_ar)->toBe('عدّله غيرك')
        ->and(AuditEvent::query()->where('action', 'database.row_updated')->exists())->toBeFalse();
});

it('keeps personal data out of the audit trail of edits and deletes', function () {
    $admin = staffWith(Role::Administrator);
    $application = Application::factory()->complete()->create(['national_id_number' => '199012345678', 'phone' => '07701234567']);
    $key = ['id' => $application->id];

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'applications'), [
            'key' => rowKey('applications', $key),
            'fingerprint' => rowFingerprint('applications', $key),
            'values' => ['national_id_number' => '200098765432', 'phone' => '07809876543'],
        ])
        ->assertSessionHasNoErrors();

    $type = DocumentType::factory()->create(['name_ar' => 'وثيقة سرية']);
    $this->actingAs($admin)
        ->delete(route('admin.database.destroy', 'document_types'), ['key' => rowKey('document_types', ['id' => $type->id])])
        ->assertSessionHasNoErrors();

    $trail = AuditEvent::query()->whereIn('action', ['database.row_updated', 'database.row_deleted'])->get();
    $json = $trail->toJson(JSON_UNESCAPED_UNICODE);

    expect($trail)->toHaveCount(2)
        ->and($trail->firstWhere('action', 'database.row_updated')->metadata['changed'])->toEqualCanonicalizing(['national_id_number', 'phone'])
        ->and($json)->not->toContain('199012345678')->not->toContain('200098765432')
        ->not->toContain('0770123')->not->toContain('0780987')->not->toContain('وثيقة سرية');
});

it('keeps raw database writes with the administrator role, whatever the exceptions say', function () {
    $supervisor = staffWith(Role::Supervisor);
    $supervisor->permissionOverrides()->create(['permission' => Permission::DatabaseView, 'effect' => OverrideEffect::Grant]);
    $supervisor->permissionOverrides()->create(['permission' => Permission::DatabaseManage, 'effect' => OverrideEffect::Grant]);
    $period = ExamPeriod::factory()->create();

    expect($supervisor->fresh()->hasPermission(Permission::DatabaseManage))->toBeFalse()
        ->and(Permission::DatabaseManage->isOverridable())->toBeFalse();

    $this->actingAs($supervisor)
        ->put(route('admin.database.update', 'exam_periods'), ['key' => rowKey('exam_periods', ['id' => $period->id]), 'values' => ['name_ar' => 'x']])
        ->assertForbidden();
});
