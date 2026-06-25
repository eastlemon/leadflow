<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Connect;
use Illuminate\Database\Seeder;

class ConnectSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['system_name' => 'alfa',  'display_name' => 'Альфа-Банк'],
            ['system_name' => 'psb',   'display_name' => 'Промсвязьбанк'],
            ['system_name' => 'vtb',   'display_name' => 'ВТБ'],
            ['system_name' => 'ural',  'display_name' => 'Урал'],
        ];

        foreach ($banks as $bank) {
            Connect::updateOrCreate(
                ['system_name' => $bank['system_name']],
                [
                    'display_name' => $bank['display_name'],
                    'is_active'    => true,
                    'tune'         => ['system_name' => $bank['system_name']],
                ],
            );
        }
    }
}
