<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase wipes whatever it connects to, so tests refuse to start
     * on anything but the in-memory database or `examis_testing`.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! in_array("{$connection}:{$database}", ['sqlite::memory:', 'pgsql:examis_testing'], true)) {
            throw new RuntimeException("Refusing to run tests against [{$connection}:{$database}].");
        }

        return $app;
    }

    /**
     * Applicant documents never touch the real disk during tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('examis.documents.disk'));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
