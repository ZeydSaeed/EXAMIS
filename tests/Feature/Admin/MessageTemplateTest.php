<?php

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\AuditEvent;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\Students\StudentIdentity;
use Database\Seeders\MessageTemplateSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function templateData(array $overrides = []): array
{
    return ['title' => 'نقص الصور', 'status' => 'requires_correction', 'body' => "الطالب {اسم_الطالب} رقم {رقم_الطالب}\nالناقص: {فراغ}", ...$overrides];
}

it('adds, edits and deletes messages, and audits each', function () {
    $this->actingAs(staffWith(Role::Administrator));

    $this->post(route('admin.message-templates.store'), templateData())->assertSessionHasNoErrors();

    $template = MessageTemplate::query()->sole();
    expect($template->status)->toBe(ApplicationStatus::RequiresCorrection);

    $this->put(route('admin.message-templates.update', $template), templateData(['title' => 'عنوان جديد', 'status' => 'rejected']))
        ->assertSessionHasNoErrors();

    expect($template->fresh()->title)->toBe('عنوان جديد')
        ->and($template->fresh()->status)->toBe(ApplicationStatus::Rejected);

    $this->delete(route('admin.message-templates.destroy', $template))->assertSessionHasNoErrors();

    expect(MessageTemplate::query()->count())->toBe(0);

    foreach (['created', 'updated', 'deleted'] as $action) {
        expect(AuditEvent::query()->where('action', 'message_template.'.$action)->exists())->toBeTrue();
    }
});

it('refuses a mistyped placeholder and any other status', function () {
    $this->actingAs(staffWith(Role::Administrator));

    $this->post(route('admin.message-templates.store'), templateData(['body' => 'مرحبا {الاسم}']))->assertSessionHasErrors('body');
    $this->post(route('admin.message-templates.store'), templateData(['status' => 'withdrawn']))->assertSessionHasErrors('status');

    expect(MessageTemplate::query()->count())->toBe(0);
});

it('leaves messages to those who manage the settings page', function () {
    $template = MessageTemplate::query()->create(templateData());

    $this->actingAs(staffWith(Role::Supervisor));

    $this->post(route('admin.message-templates.store'), templateData())->assertForbidden();
    $this->delete(route('admin.message-templates.destroy', $template))->assertForbidden();
});

it('shows the messages on the settings page', function () {
    MessageTemplate::query()->create(templateData());

    $this->actingAs(staffWith(Role::Administrator))
        ->get(route('admin.content.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('templates.0.status_label', 'وجود نقص بالمعاملة')
            ->has('placeholders', 5));
});

it('starts with one message for each decision, and never adds them twice', function () {
    $this->seed(MessageTemplateSeeder::class);
    MessageTemplate::query()->first()->delete();
    $this->seed(MessageTemplateSeeder::class);

    expect(MessageTemplate::query()->count())->toBe(2);

    MessageTemplate::query()->delete();
    $this->seed(MessageTemplateSeeder::class);

    expect(MessageTemplate::query()->pluck('status')->map->value->sort()->values()->all())
        ->toBe(['accepted', 'rejected', 'requires_correction']);
});

it('gives the list what the dialog needs to fill a message', function () {
    MessageTemplate::query()->create(templateData());

    $application = Application::factory()
        ->for(User::factory()->student()->create(['student_number' => 647179]))
        ->submitted()
        ->create(['phone' => '07701234567']);

    $this->actingAs(staffWith(Role::Supervisor))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->where('applications.data.0.contact.name', $application->full_name_ar)
            ->where('applications.data.0.contact.number', 647179)
            ->where('applications.data.0.contact.phone', '9647701234567'));
});

it('hides the phone from a list that cannot decide', function () {
    Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Auditor))
        ->get(route('admin.applications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('applications.data.0.contact', null)
            ->has('templates', 0));
});

it('keeps the finished message on the decision only', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.decision', $application), ['target' => 'requires_correction', 'reason' => 'الناقص: صورة شخصية'])
        ->assertSessionHasNoErrors();

    $events = ApplicationEvent::query()->where('application_id', $application->id)->orderBy('id')->get();

    expect($events->pluck('reason')->all())->toBe([null, 'الناقص: صورة شخصية']);
});

it('refuses a message with a gap left empty', function () {
    $application = Application::factory()->submitted()->create();

    $this->actingAs(staffWith(Role::Supervisor))
        ->post(route('admin.applications.decision', $application), ['target' => 'requires_correction', 'reason' => 'الناقص: {فراغ}'])
        ->assertSessionHasErrors('reason');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('offers the sent message again on the application page', function () {
    $application = Application::factory()->submitted()->create(['phone' => '07701234567']);
    $supervisor = staffWith(Role::Supervisor);

    $this->actingAs($supervisor)->post(route('admin.applications.decision', $application), ['target' => 'rejected', 'reason' => 'السبب: الوثائق ناقصة']);

    $this->get(route('admin.applications.show', $application))
        ->assertInertia(fn (Assert $page) => $page
            ->where('decisionMessage.text', 'السبب: الوثائق ناقصة')
            ->where('decisionMessage.phone', '9647701234567'));
});

it('turns local numbers into international ones', function (?string $phone, ?string $expected) {
    expect(StudentIdentity::internationalPhone($phone))->toBe($expected);
})->with([
    ['07701234567', '9647701234567'],
    ['+9647701234567', '9647701234567'],
    ['009647701234567', '9647701234567'],
    [null, null],
    ['', null],
]);
