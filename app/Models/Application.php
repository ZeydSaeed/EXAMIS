<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $exam_period_id
 * @property string|null $reference_number
 * @property string $verification_token
 * @property ApplicationStatus $status
 * @property int $lock_version
 * @property string|null $full_name_ar
 * @property string|null $student_name
 * @property string|null $father_name
 * @property string|null $grandfather_name
 * @property string|null $great_grandfather_name
 * @property string|null $full_name_en
 * @property string|null $mother_name_ar
 * @property string|null $mother_first_name
 * @property string|null $mother_father_name
 * @property CarbonImmutable|null $birth_date
 * @property Gender|null $gender
 * @property string|null $national_id_number
 * @property string|null $phone
 * @property string|null $contact_email
 * @property string|null $address
 * @property string|null $district
 * @property string|null $mahalla
 * @property string|null $alley
 * @property string|null $house_number
 * @property string|null $governorate_code
 * @property string|null $directorate_code
 * @property string|null $school_name
 * @property string|null $intermediate_school_name
 * @property string|null $last_grade
 * @property int|null $failed_years
 * @property int|null $left_years
 * @property string|null $branch_code
 * @property string|null $category_code
 * @property int|null $previous_exam_year
 * @property string|null $other_info
 * @property list<array{number: string|null, date: string|null, recipient: string|null}>|null $issued_documents
 * @property CarbonImmutable|null $declaration_accepted_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $last_submitted_at
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $assigned_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read ExamPeriod $examPeriod
 * @property-read User|null $assignee
 * @property-read Collection<int, ApplicationDocument> $currentDocuments
 * @property-read Collection<int, ApplicationEvent> $events
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * Applicants apply to the vocational education department in Karbala and
     * must hold a Karbala residence card, so the governorate is fixed.
     */
    public const GOVERNORATE_CODE = 'KRB';

    /**
     * Form fields an applicant may write: the official student status form
     * (استمارة واقع حال) plus the national ID number. `school_name` is the
     * last school attended and `category_code` the specialization within
     * the branch. The student's and mother's names are entered part by part;
     * `full_name_ar` and `mother_name_ar` are composed from them. `phone` is
     * always the account's mobile number (see SaveApplicationRequest).
     * Status, numbering, ownership, assignment and timestamps are never mass
     * assignable.
     */
    public const FORM_FIELDS = [
        'student_name', 'father_name', 'grandfather_name', 'great_grandfather_name',
        'mother_first_name', 'mother_father_name', 'birth_date', 'gender', 'national_id_number',
        'phone', 'district', 'mahalla', 'alley', 'house_number',
        'school_name', 'intermediate_school_name', 'last_grade', 'failed_years', 'left_years',
        'branch_code', 'category_code', 'other_info', 'issued_documents',
    ];

    /**
     * Fields that must be filled before the application can be submitted.
     */
    public const REQUIRED_FOR_SUBMISSION = [
        'student_name', 'father_name', 'grandfather_name', 'great_grandfather_name',
        'mother_first_name', 'mother_father_name', 'birth_date', 'gender', 'national_id_number',
        'phone', 'district', 'mahalla', 'alley', 'house_number',
        'school_name', 'intermediate_school_name', 'last_grade', 'failed_years', 'left_years',
        'branch_code', 'category_code',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = self::FORM_FIELDS;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'lock_version' => 1,
        'governorate_code' => self::GOVERNORATE_CODE,
    ];

    /**
     * The name parts in form order, joined into the stored full names.
     */
    public const NAME_PARTS = ['student_name', 'father_name', 'grandfather_name', 'great_grandfather_name'];

    public const MOTHER_NAME_PARTS = ['mother_first_name', 'mother_father_name'];

    protected static function booted(): void
    {
        static::creating(function (Application $application): void {
            $application->uuid ??= (string) Str::uuid7();
            $application->verification_token ??= (string) Str::uuid();
        });

        // Staff search, lists and reports read the composed names. The form
        // only sends parts; a name set directly (imports, tests) is kept.
        static::saving(function (Application $application): void {
            if ($application->isDirty(self::NAME_PARTS) && ! $application->isDirty('full_name_ar')) {
                $application->full_name_ar = self::joinParts($application, self::NAME_PARTS);
            }

            if ($application->isDirty(self::MOTHER_NAME_PARTS) && ! $application->isDirty('mother_name_ar')) {
                $application->mother_name_ar = self::joinParts($application, self::MOTHER_NAME_PARTS);
            }
        });
    }

    /**
     * @param  list<string>  $fields
     */
    private static function joinParts(Application $application, array $fields): ?string
    {
        $parts = array_filter(array_map(
            fn (string $field): string => trim((string) $application->getAttribute($field)),
            $fields,
        ), fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'gender' => Gender::class,
            'birth_date' => 'date',
            'lock_version' => 'integer',
            'previous_exam_year' => 'integer',
            'failed_years' => 'integer',
            'left_years' => 'integer',
            'issued_documents' => 'array',
            'declaration_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExamPeriod, $this>
     */
    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * Documents that have not been replaced by a newer upload.
     *
     * @return HasMany<ApplicationDocument, $this>
     */
    public function currentDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->whereNull('replaced_at');
    }

    /**
     * @return HasMany<ApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->chaperone();
    }

    /**
     * Restrict a query to the applications a staff member may see:
     * everything with `applications.view_all`, otherwise their own
     * assignments plus the unassigned review queue.
     *
     * @param  Builder<Application>  $query
     */
    public function scopeVisibleToStaff(Builder $query, User $staff): void
    {
        if ($staff->hasPermission(Permission::ApplicationsViewAll)) {
            $query->where('status', '!=', ApplicationStatus::Draft);

            return;
        }

        if (! $staff->hasPermission(Permission::ApplicationsView)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', '!=', ApplicationStatus::Draft)
            ->where(function (Builder $query) use ($staff): void {
                $query->where('assigned_to', $staff->id)
                    ->orWhere(function (Builder $query): void {
                        $query->whereNull('assigned_to')
                            ->whereIn('status', ApplicationStatus::awaitingReview());
                    });
            });
    }

    /**
     * Single-record counterpart of scopeVisibleToStaff(); keep them in sync.
     */
    public function isVisibleToStaff(User $staff): bool
    {
        if ($this->status === ApplicationStatus::Draft || ! $staff->hasPermission(Permission::ApplicationsView)) {
            return false;
        }

        if ($staff->hasPermission(Permission::ApplicationsViewAll) || $this->assigned_to === $staff->id) {
            return true;
        }

        return $this->assigned_to === null && in_array($this->status, ApplicationStatus::awaitingReview(), true);
    }

    /**
     * Missing form fields and required documents that block submission.
     *
     * @return array{fields: list<string>, documents: list<string>}
     */
    public function missingRequirements(): array
    {
        $missingFields = array_values(array_filter(
            self::REQUIRED_FOR_SUBMISSION,
            fn (string $field): bool => blank($this->getAttribute($field)),
        ));

        $verifiedTypeIds = $this->currentDocuments
            ->reject(fn (ApplicationDocument $document): bool => $document->isRejected())
            ->pluck('document_type_id')
            ->all();

        $missingDocuments = $this->examPeriod->documentTypes
            ->filter(fn (DocumentType $type): bool => (bool) $type->pivot->is_required)
            ->reject(fn (DocumentType $type): bool => in_array($type->id, $verifiedTypeIds, true))
            ->map(fn (DocumentType $type): string => $type->code)
            ->all();

        return ['fields' => $missingFields, 'documents' => array_values($missingDocuments)];
    }

    public function isReadyForSubmission(): bool
    {
        $missing = $this->missingRequirements();

        return $missing['fields'] === [] && $missing['documents'] === [];
    }
}
