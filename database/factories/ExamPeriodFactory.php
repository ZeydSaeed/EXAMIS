<?php

namespace Database\Factories;

use App\Enums\ExamPeriodStatus;
use App\Models\DocumentType;
use App\Models\ExamPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPeriod>
 */
class ExamPeriodFactory extends Factory
{
    /**
     * Defaults to a period that is currently accepting applications.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'EX'.fake()->unique()->numerify('####'),
            'name_ar' => 'الدورة الأولى للامتحانات الخارجية',
            'name_en' => 'First external examination round',
            'academic_year' => '2026-2027',
            'status' => ExamPeriodStatus::Open,
            'opens_at' => now()->subDays(7),
            'closes_at' => now()->addDays(30),
            'eligible_branch_codes' => null,
            'eligible_category_codes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamPeriodStatus::Closed,
            'opens_at' => now()->subDays(60),
            'closes_at' => now()->subDay(),
        ]);
    }

    /**
     * Attach document types as requirements of the period.
     */
    public function requiring(DocumentType ...$types): static
    {
        return $this->afterCreating(function (ExamPeriod $period) use ($types): void {
            foreach ($types as $type) {
                $period->documentTypes()->attach($type->id, ['is_required' => true]);
            }
        });
    }
}
