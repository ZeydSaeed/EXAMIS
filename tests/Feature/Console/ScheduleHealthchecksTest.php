<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Psr\Http\Message\RequestInterface;

/**
 * Loads the schedule again under the current Healthchecks.io settings and
 * returns the event for the command.
 */
function scheduledEvent(string $command): Event
{
    $schedule = new Schedule;
    ScheduleFacade::swap($schedule);
    require base_path('routes/console.php');

    return collect($schedule->events())->first(fn (Event $event): bool => str_contains((string) $event->command, $command))
        ?? throw new RuntimeException("{$command} is not scheduled");
}

/**
 * The scheduler pings through Guzzle; record what it asks for.
 *
 * @return ArrayObject<int, array{request: RequestInterface}>
 */
function recordPings(): ArrayObject
{
    $history = new ArrayObject;
    $stack = HandlerStack::create(new MockHandler(array_fill(0, 5, new Response(200))));
    $stack->push(Middleware::history($history));
    app()->instance(ClientInterface::class, new Client(['handler' => $stack]));

    return $history;
}

it('pings Healthchecks.io after the staff access sync and the integrity check', function (string $command, string $key) {
    config(["services.healthchecks.{$key}" => 'https://hc-ping.com/11111111-2222-3333-4444-555555555555']);
    $pings = recordPings();

    $event = scheduledEvent($command);
    $event->exitCode = 0;
    $event->callAfterCallbacks(app());
    $event->exitCode = 1;
    $event->callAfterCallbacks(app());

    expect(array_map(fn (array $entry): string => (string) $entry['request']->getUri(), $pings->getArrayCopy()))->toBe([
        'https://hc-ping.com/11111111-2222-3333-4444-555555555555',
        'https://hc-ping.com/11111111-2222-3333-4444-555555555555/fail',
    ]);
})->with([
    'staff access sync' => ['examis:sync-staff-access', 'staff_access_sync'],
    'integrity check' => ['examis:verify-integrity', 'verify_integrity'],
]);

it('pings nothing when no URL is set', function () {
    config(['services.healthchecks.staff_access_sync' => null]);
    $pings = recordPings();

    $event = scheduledEvent('examis:sync-staff-access');
    $event->exitCode = 0;
    $event->callAfterCallbacks(app());

    expect($pings)->toHaveCount(0);
});
