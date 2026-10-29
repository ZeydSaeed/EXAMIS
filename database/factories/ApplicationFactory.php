<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Models\Application;
use App\Models\ExamPeriod;
use App\Models\User;
use App\Services\Applications\ReferenceNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * An empty draft owned by a new applicant in an open period.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_period_id' => ExamPeriod::factory(),
        ];
    }

    /**
     * Every field required for submission filled with valid synthetic data
     * (codes match ReferenceDataSeeder).
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'علي',
            'great_grandfather_name' => 'حسن',
            'mother_first_name' => 'فاطمة',
            'mother_father_name' => 'جاسم',
            'birth_date' => '2006-03-15',
            'gender' => Gender::Male,
            'national_id_number' => fake()->unique()->numerify('19##########'),
            'phone' => fake()->numerify('0770#######'),
            'district' => 'حي الحسين',
            'mahalla' => (string) fake()->numberBetween(100, 999),
            'alley' => (string) fake()->numberBetween(1, 60),
            'house_number' => (string) fake()->numberBetween(1, 200),
            'school_name' => 'إعدادية النجاح للبنين',
            'intermediate_school_name' => 'متوسطة الفرات للبنين',
            'last_grade' => 'الخامس الإعدادي',
            'failed_years' => 0,
            'left_years' => 1,
            'branch_code' => 'IND',
            'category_code' => 'IND_ELC',
        ]);
    }

    /**
     * A complete application already in a submitted (or later) status.
     */
    public function inStatus(ApplicationStatus $status): static
    {
        return $this->complete()->state(function (array $attributes) use ($status) {
            if ($status === ApplicationStatus::Draft) {
                return [];
            }

            $digits = fake()->unique()->numerify('######');

            return [
                'reference_number' => 'EXT-'.$digits.ReferenceNumberGenerator::checkDigit($digits),
                'submitted_at' => now()->subDays(2),
                'last_submitted_at' => now()->subDays(2),
                'declaration_accepted_at' => now()->subDays(2),
                'decided_at' => $status->isDecided() ? now()->subDay() : null,
            ];
        })->afterMaking(function (Application $application) use ($status): void {
            $application->status = $status;
        });
    }

    public function submitted(): static
    {
        return $this->inStatus(ApplicationStatus::Submitted);
    }

    public function assignedTo(User $reviewer): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to' => $reviewer->id,
            'assigned_at' => now(),
        ]);
    }
}
