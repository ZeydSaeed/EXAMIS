<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::lower(fake()->unique()->bothify('user.????##')),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Applicant,
            'locale' => 'ar',
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * A staff account holding the given roles.
     */
    public function staff(Role ...$roles): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ])->afterCreating(function (User $user) use ($roles): void {
            foreach ($roles as $role) {
                $user->roleAssignments()->create(['role' => $role]);
            }
        });
    }

    /**
     * A password-less student account (ADR-0009) with a phone number, QR id,
     * student number and the access code "ABCDE-23456".
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'password' => null,
            'phone' => '0770'.fake()->unique()->numerify('#######'),
            'public_id' => (string) Str::uuid(),
            'student_number' => fake()->unique()->numberBetween(10000, 99999),
            'access_code' => 'ABCDE-23456',
            'access_code_issued_at' => now(),
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }
}
