<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nnjeim\World\Actions\SeedAction;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedWorldData();
        $this->callConfiguredSeeders('gymie.seeding.before');

        $this->call([
            ShieldSeeder::class,
            UserSeeder::class,
            ServiceSeeder::class,
            PlanSeeder::class,
            EnquirySeeder::class,
            FollowUpSeeder::class,
            MemberSeeder::class,
            SubscriptionSeeder::class,
            InvoiceSeeder::class,
            ExpenseSeeder::class,
            AttendanceSeeder::class,
        ]);

        $this->callConfiguredSeeders('gymie.seeding.after');

        if (app()->environment(['local', 'development'])) {
            $this->call(DashboardDemoSeeder::class);
        }
    }

    /**
     * Seed supporting world data when the package is available.
     */
    private function seedWorldData(): void
    {
        try {
            $this->call(SeedAction::class);
        } catch (Throwable $e) {
            $this->command?->warn('Skipping world data seed: '.$e->getMessage());
        }
    }

    private function callConfiguredSeeders(string $configKey): void
    {
        $seeders = array_values(array_filter(
            (array) config($configKey, []),
            fn (mixed $seeder): bool => is_string($seeder) && is_subclass_of($seeder, Seeder::class),
        ));

        if ($seeders !== []) {
            $this->call($seeders);
        }
    }
}
