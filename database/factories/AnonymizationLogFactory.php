<?php

namespace Database\Factories;

use App\Enums\AnonymizationActionType;
use App\Models\AnonymizationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnonymizationLog>
 */
class AnonymizationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'action_type' => fake()->randomElement(AnonymizationActionType::cases())->value,
            'anonymized_fields' => [
                'name',
                'email',
                'password',
                'remember_token',
                'addresses.recipient_name',
                'addresses.phone',
            ],
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function forgottenManual(): static
    {
        return $this->state([
            'action_type' => AnonymizationActionType::ForgottenManual->value,
        ]);
    }

    public function autoGuest(): static
    {
        return $this->state([
            'action_type' => AnonymizationActionType::AutoAnonymizeGuest->value,
        ]);
    }

    public function autoRegistered(): static
    {
        return $this->state([
            'action_type' => AnonymizationActionType::AutoAnonymizeRegistered->value,
        ]);
    }
}
