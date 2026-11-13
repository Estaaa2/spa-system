<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Fresh copy of the DSS demo scenario: remove, then seed again (dates move to today). */
class DssDemoScenarioResetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DssDemoScenarioRemoveSeeder::class);
        $this->call(DssDemoScenarioSeeder::class);
    }
}
