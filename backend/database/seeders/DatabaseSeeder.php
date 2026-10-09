<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data: one account per role (password "password") and a few events.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@eventpass.test')->exists()) {
            return; // already seeded (container restart)
        }

        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@eventpass.test']);
        $organizer = User::factory()->organizer()->create(['name' => 'Kyiv Live Agency', 'email' => 'organizer@eventpass.test']);
        User::factory()->create(['name' => 'Olena Buyer', 'email' => 'buyer@eventpass.test']);

        $events = [
            ['Jazz on the Dnipro', 'Kyiv', 'River Port Stage', 'An open-air evening of Ukrainian jazz quartets by the river, with a late jam session for anyone who brings an instrument.', 12, [['Standard', 45000, 200], ['Front row', 90000, 30]]],
            ['Frontend Days Lviv', 'Lviv', 'Lem Station', 'Two tracks of talks on Vue, Angular and the web platform, workshops in the afternoon and an after-party for speakers and guests.', 25, [['Early bird', 120000, 50], ['Regular', 180000, 250], ['Workshop pass', 320000, 40]]],
            ['Cherkasy Indie Night', 'Cherkasy', 'Dom Kultury', 'Four local indie bands, one long night. Doors open at 19:00.', 8, [['Entry', 30000, 150]]],
            ['Odesa Film Picnic', 'Odesa', 'Shevchenko Park lawn', 'Short films on a big outdoor screen. Bring a blanket; we bring popcorn.', 40, [['Lawn seat', 25000, 300], ['Picnic zone for two', 70000, 40]]],
        ];

        foreach ($events as [$title, $city, $venue, $description, $inDays, $types]) {
            $event = $organizer->organizedEvents()->create([
                'title' => $title,
                'city' => $city,
                'venue' => $venue,
                'description' => $description,
                // Evening in Kyiv, stored in UTC.
                'starts_at' => now('Europe/Kyiv')->addDays($inDays)->setTime(19, 0)->utc(),
                'ends_at' => now('Europe/Kyiv')->addDays($inDays)->setTime(23, 0)->utc(),
                'status' => EventStatus::Published,
            ]);

            foreach ($types as [$name, $price, $quantity]) {
                $event->ticketTypes()->create(['name' => $name, 'price' => $price, 'quantity' => $quantity, 'currency' => 'UAH']);
            }
        }

        Event::factory()->draft()->for($organizer, 'organizer')->create(['title' => 'Draft: Winter Gala']);
    }
}
