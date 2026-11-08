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
        ->put(route('admin.database.update', 'users'), ['key' => $token, 'values' => ['access_code' => 'HACKED', 'name' => 'اسم جديد']])
        ->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->name)->toBe('اسم جديد')
        ->and($student->access_code)->toBe('ABCDE-23456')
        ->and(AuditEvent::query()->where('action', 'database.row_updated')->first()->metadata['changes'])
        ->toHaveKey('name')->not->toHaveKey('access_code');
});

it('edits, adds and deletes rows with typed values', function () {
    $admin = staffWith(Role::Administrator);
    $type = DocumentType::factory()->create(['max_size_kb' => 100, 'is_photo' => false]);
    $token = rowKey('document_types', ['id' => $type->id]);

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'document_types'), ['key' => $token, 'values' => ['max_size_kb' => 'abc']])
        ->assertSessionHasErrors('max_size_kb');

    $this->actingAs($admin)
        ->put(route('admin.database.update', 'document_types'), ['key' => $token, 'values' => ['max_size_kb' => '2048', 'is_photo' => 'true']])
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
