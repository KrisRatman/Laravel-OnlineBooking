<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'phone' => '+79'.fake()->unique()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'telegram_chat_id' => null,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(['email' => null]);
    }

    public function withTelegram(string $chatId = '123456789'): static
    {
        return $this->state(['telegram_chat_id' => $chatId]);
    }
}
