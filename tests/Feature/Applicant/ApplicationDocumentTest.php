<?php

use App\Enums\ApplicationEventType;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A real temporary file, so the MIME type is sniffed from its bytes the way
 * it is for genuine uploads (fake uploads report a type from the filename).
 */
function realUpload(string $clientName, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $contents);

    return new UploadedFile($path, $clientName, null, null, true);
}

function draftWithRequiredDocument(): array
{
    [$period, $type] = openPeriodRequiringDocument();
    $application = Application::factory()->create(['exam_period_id' => $period->id]);

    return [$application, $type];
}

describe('uploading', function () {
    it('stores the file privately under a random key and verifies it', function () {
        [$application, $type] = draftWithRequiredDocument();

        $this->actingAs($application->user)
            ->post(route('applications.documents.store', $application), [
                'document_type' => $type->code,
                'file' => uploadedPdf('../../etc/passwd.pdf'),
            ])
            ->assertSessionHasNoErrors();

        $document = ApplicationDocument::query()->sole();
        expect($document->status)->toBe(DocumentStatus::Verified)
            ->and($document->path)->toStartWith($application->uuid.'/')
            ->and($document->path)->not->toContain('passwd')
            ->and($document->original_name)->toBe('passwd.pdf')
            ->and($document->sha256)->toBe(hash('sha256', pdfBytes('BT (ok) Tj ET')));

        Storage::disk('documents')->assertExists($document->path);
    });

    it('keeps the replaced document in the history', function () {
        [$application, $type] = draftWithRequiredDocument();
        $owner = $application->user;

        $this->actingAs($owner)->post(route('applications.documents.store', $application), ['document_type' => $type->code, 'file' => uploadedPdf()]);
        $this->actingAs($owner)->post(route('applications.documents.store', $application), ['document_type' => $type->code, 'file' => uploadedPdf('second.pdf')]);

        expect(ApplicationDocument::query()->count())->toBe(2)
            ->and($application->currentDocuments()->count())->toBe(1)
            ->and($application->currentDocuments()->sole()->original_name)->toBe('second.pdf')
            ->and($application->events()->where('type', ApplicationEventType::DocumentReplaced)->count())->toBe(1);
    });

    it('rejects a disguised file by its real content type', function () {
        [$application, $type] = draftWithRequiredDocument();

        $this->actingAs($application->user)
            ->post(route('applications.documents.store', $application), [
                'document_type' => $type->code,
                'file' => realUpload('photo.pdf', "<?php system('id'); ?>"),
            ])
            ->assertSessionHasErrors('file');

        expect(ApplicationDocument::query()->count())->toBe(0);
    });

    it('rejects files over the size limit for the document type', function () {
        [$application, $type] = draftWithRequiredDocument();
        $type->update(['max_size_kb' => 1]);

        $this->actingAs($application->user)
            ->post(route('applications.documents.store', $application), [
                'document_type' => $type->code,
                'file' => uploadedPdf(body: str_repeat('A', 4096)),
            ])
            ->assertSessionHasErrors('file');
    });

    it('rejects document types the period does not ask for', function () {
        [$application] = draftWithRequiredDocument();

        $this->actingAs($application->user)
            ->post(route('applications.documents.store', $application), ['document_type' => 'unknown_type', 'file' => uploadedPdf()])
            ->assertSessionHasErrors('document_type');
    });

    it('marks PDFs with embedded scripts as rejected and deletes the bytes', function () {
        [$application, $type] = draftWithRequiredDocument();

        $this->actingAs($application->user)->post(route('applications.documents.store', $application), [
            'document_type' => $type->code,
            'file' => uploadedPdf(body: '<< /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >>'),
        ]);

        $document = ApplicationDocument::query()->sole();
        expect($document->status)->toBe(DocumentStatus::Rejected)
            ->and($document->rejection_reason)->toBe('active_content');
        Storage::disk('documents')->assertMissing($document->path);
    });

    it('answers 404 when uploading to another applicant\'s application', function () {
        [$application, $type] = draftWithRequiredDocument();

        $this->actingAs(applicant())
            ->post(route('applications.documents.store', $application), ['document_type' => $type->code, 'file' => uploadedPdf()])
            ->assertNotFound();

        Storage::disk('documents')->assertDirectoryEmpty('/');
    });
});

describe('downloading', function () {
    it('serves a verified document to its owner with safe headers', function () {
        $document = ApplicationDocument::factory()->verified()->create();

        $response = $this->actingAs($document->application->user)->get(route('documents.show', $document));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'application/pdf');
        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
            ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('answers 404 to other applicants', function () {
        $document = ApplicationDocument::factory()->verified()->create();

        $this->actingAs(applicant())->get(route('documents.show', $document))->assertNotFound();
    });

    it('withholds documents that have not passed scanning', function () {
        $document = ApplicationDocument::factory()->create();

        $this->actingAs($document->application->user)->get(route('documents.show', $document))->assertForbidden();
    });

    it('lets reviewers open documents of applications in the queue and audits the view', function () {
        $application = Application::factory()->submitted()->create();
        $document = ApplicationDocument::factory()->verified()->create(['application_id' => $application->id]);
        $reviewer = staffWith(Role::Reviewer);

        $this->actingAs($reviewer)->get(route('documents.show', $document))->assertOk();

        expect(AuditEvent::query()->where('action', 'document.viewed')->where('actor_id', $reviewer->id)->exists())->toBeTrue();
    });

    it('denies staff roles without document access', function () {
        $application = Application::factory()->submitted()->create();
        $document = ApplicationDocument::factory()->verified()->create(['application_id' => $application->id]);

        $this->actingAs(staffWith(Role::Auditor))->get(route('documents.show', $document))->assertNotFound();
    });

    it('hides draft documents from staff', function () {
        $document = ApplicationDocument::factory()->verified()->create();

        $this->actingAs(staffWith(Role::Supervisor))->get(route('documents.show', $document))->assertNotFound();
    });

    it('requires authentication', function () {
        $document = ApplicationDocument::factory()->verified()->create();

        $this->get(route('documents.show', $document))->assertRedirect(route('login'));
    });
});
