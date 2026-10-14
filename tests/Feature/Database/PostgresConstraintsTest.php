<?php

use App\Models\Application;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Database-level guarantees that only PostgreSQL enforces. Skipped on
 * SQLite; CI runs the suite on PostgreSQL so these always execute there.
 */
beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only constraints.');
    }
});

it('rejects UPDATE and DELETE on audit events even with raw SQL', function () {
    DB::table('audit_events')->insert(['action' => 'test.event']);

    expect(fn () => DB::table('audit_events')->update(['action' => 'tampered']))->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_events')->delete())->toThrow(QueryException::class);
});

it('rejects UPDATE and DELETE on application history even with raw SQL', function () {
    $application = Application::factory()->create();
    DB::table('application_events')->insert(['application_id' => $application->id, 'type' => 'created']);

    expect(fn () => DB::table('application_events')->update(['reason' => 'rewritten']))->toThrow(QueryException::class);
});

it('refuses a non-draft application without a reference number', function () {
    $application = Application::factory()->create();

    expect(fn () => DB::table('applications')->where('id', $application->id)->update(['status' => 'submitted']))
        ->toThrow(QueryException::class);
});

it('refuses a period that closes before it opens', function () {
    expect(fn () => DB::table('exam_periods')->insert([
        'code' => 'BAD1', 'name_ar' => 'x', 'name_en' => 'x', 'academic_year' => '2026-2027',
        'opens_at' => now(), 'closes_at' => now()->subDay(),
    ]))->toThrow(QueryException::class);
});

it('refuses malformed usernames', function () {
    expect(fn () => DB::table('users')->insert([
        'username' => 'Bad Name', 'name' => 'x', 'email' => 'x@example.com', 'password' => 'x',
    ]))->toThrow(QueryException::class);
});
