<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // FX rate is a PLACEHOLDER — the admin must set the current rate in
        // Filament before salary conversions are shown as authoritative.
        Setting::query()->firstOrCreate(
            ['key' => 'fx.usd_to_ttd'],
            ['value' => [
                'rate' => 6.80,
                'note' => 'Placeholder rate — update with the current TTD/USD rate in Admin > Settings.',
                'needs_review' => true,
            ]],
        );
    }
}
