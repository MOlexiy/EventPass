<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->unique()->catchPhrase(),
            'description' => fake()->paragraphs(3, true),
            'venue' => fake()->company().' Hall',
            'city' => fake()->randomElement(['Kyiv', 'Lviv', 'Odesa', 'Cherkasy', 'Kharkiv']),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->modify('+3 hours'),
            'status' => EventStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }

    public function past(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHours(3)]);
    }
}
