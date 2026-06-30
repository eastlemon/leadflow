<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\PipelineReceiver;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds pipelines and receivers mirroring the TellFax connect tree.
 *
 * TellFax connect structure:
 *   Connect #1  (skorozvon "Свежая база") → alfa, psb, ural
 *   Connect #14 (file_upload "Действующий бизнес") → alfa, dadata, vtb, psb, stub
 *   Connect #39 (file_upload "Массовая загрузка") → vtb, ural, psb
 *
 * API keys are placeholder values — fill real ones via Filament admin.
 */
class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        // Find or create the default admin user.
        $user = User::query()
            ->where('email', 'admin@leadflow.local')
            ->first();

        if (! $user) {
            $user = User::factory()->create([
                'name'  => 'Admin',
                'email' => 'admin@leadflow.local',
            ]);
        }

        // ── Pipeline 1: Skorozvon "Свежая база" ─────────────────────
        $skorozvonPipeline = Pipeline::create([
            'user_id'          => $user->id,
            'provider'         => 'skorozvon',
            'name'             => 'Свежая база',
            'is_active'        => true,
            'provider_config'  => [
                'url'            => 'https://app1.skorozvon.ru/',
                'username'       => 'pochtanamail2018@mail.ru',
                'api_key'        => '<fill-skorozvon-api-key>',
                'client_id'      => '<fill-skorozvon-client-id>',
                'client_secret'  => '<fill-skorozvon-client-secret>',
                'lead_word'      => 'Лид, ЛидРКО, ЛидЭквайринг, ГорячийЛид',
            ],
        ]);

        // Receivers for Skorozvon pipeline (Connect #3, #21, #30)
        PipelineReceiver::create([
            'pipeline_id'  => $skorozvonPipeline->id,
            'system_name'  => 'alfa',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'         => '1',
                'delay'            => '1',
                'baseUrl'          => 'https://partner.alfabank.ru/',
                'API_KEY'          => '<fill-alfa-api-key>',
                'advCode'          => 'pahomenkov_transfer',
                'inn_skip_list'    => '91',
                'okved_skip_list'  => '-',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $skorozvonPipeline->id,
            'system_name'  => 'psb',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'         => '1',
                'delay'            => '5',
                'baseUrl'          => 'https://api.lk.psb.services/fo/v1.0.0',
                'email'            => '<fill-psb-email>',
                'password'          => '<fill-psb-password>',
                'off_days'         => '-',
                'inn_skip_list'    => '90, 91, 84, 85, 80, 81, 94, 96, 86, 20, 95, 12, 07, 08, 06, 04, 05, 09, 13, 14, 19, 30',
                'inn_only'         => '-',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $skorozvonPipeline->id,
            'system_name'  => 'ural',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'        => '1',
                'delay'            => '0',
                'baseUrl'          => 'https://api.lk.digital.uralsib.ru/',
                'token'            => '<fill-ural-token>',
                'off_days'         => '60',
                'inn_only'         => '78,47,77,50',
                'skip_exist'       => 'no',
            ],
        ]);

        // ── Pipeline 2: File upload "Действующий бизнес" ─────────────
        $filePipeline = Pipeline::create([
            'user_id'          => $user->id,
            'provider'         => 'file_upload',
            'name'             => 'Действующий бизнес',
            'is_active'        => true,
            'provider_config'  => [
                'custom_name' => 'Действующий бизнес',
                'uniq_name'  => 'pochtanamail2023',
                'skip_exist' => 'no',
            ],
        ]);

        // Receivers for file upload pipeline (Connect #15, #19, #20, #22, #23)
        PipelineReceiver::create([
            'pipeline_id'  => $filePipeline->id,
            'system_name'  => 'alfa',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '0',
                'is_score'         => '1',
                'delay'            => '1',
                'baseUrl'          => 'https://partner.alfabank.ru/',
                'API_KEY'          => '<fill-alfa-api-key>',
                'advCode'          => 'pahomenkov_transfer',
                'inn_skip_list'    => '91',
                'okved_skip_list'  => '-',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $filePipeline->id,
            'system_name'  => 'vtb',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'         => '1',
                'delay'            => '1',
                'baseUrl'          => 'https://gw.api.vtb.ru:443/openapi/smb/lecs/lead-impers/v1/',
                'authUrl'          => 'https://open.api.vtb.ru:443/passport/oauth2/token',
                'clientId'         => '<fill-vtb-client-id>',
                'clientSecret'     => '<fill-vtb-client-secret>',
                'city_black_list'  => '-',
                'off_days'         => '-',
                'inn_skip_list'    => '27,22,42,31,39,48,51,53,60,11,13,89,86,65,41,49',
                'partyUid'         => '<fill-vtb-party-uid>',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $filePipeline->id,
            'system_name'  => 'psb',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '0',
                'is_score'         => '1',
                'delay'            => '2',
                'baseUrl'          => 'https://api.lk.psb.services/fo/v1.0.0',
                'email'            => '<fill-psb-email>',
                'password'         => '<fill-psb-password>',
                'off_days'         => '-',
                'inn_skip_list'    => '-',
                'inn_only'         => '30, 25, 66, 18, 16, 42, 24, 23, 50, 77, 52, 54, 56, 59, 55, 61, 47, 78, 72, 02, 74',
                'skip_exist'       => 'no',
            ],
        ]);

        // ── Pipeline 3: File upload "Массовая загрузка" ──────────────
        $massPipeline = Pipeline::create([
            'user_id'          => $user->id,
            'provider'         => 'file_upload',
            'name'             => 'Массовая загрузка',
            'is_active'        => true,
            'provider_config'  => [
                'custom_name' => 'Массовая загрузка',
                'uniq_name'  => 'pochtanamail2026',
                'skip_exist' => 'no',
            ],
        ]);

        // Receivers for mass upload pipeline (Connect #40, #41, #42)
        PipelineReceiver::create([
            'pipeline_id'  => $massPipeline->id,
            'system_name'  => 'vtb',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'         => '1',
                'delay'            => '1,1',
                'baseUrl'          => 'https://gw.api.vtb.ru:443/openapi/smb/lecs/lead-impers/v1/',
                'authUrl'          => 'https://open.api.vtb.ru:443/passport/oauth2/token',
                'clientId'         => '<fill-vtb-client-id>',
                'clientSecret'     => '<fill-vtb-client-secret>',
                'city_black_list'  => '06, 38, 25, 27, 52, 55, 27',
                'off_days'         => '90',
                'inn_skip_list'    => '-',
                'partyUid'         => '<fill-vtb-party-uid>',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $massPipeline->id,
            'system_name'  => 'ural',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '0',
                'is_score'         => '1',
                'delay'            => '2,1',
                'baseUrl'          => 'https://api.lk.digital.uralsib.ru/',
                'token'            => '<fill-ural-token>',
                'off_days'         => '60',
                'inn_only'         => '47,78',
                'skip_exist'       => 'no',
            ],
        ]);

        PipelineReceiver::create([
            'pipeline_id'  => $massPipeline->id,
            'system_name'  => 'psb',
            'is_active'    => true,
            'tune'         => [
                'is_on'            => '1',
                'send_immediately' => '1',
                'is_score'         => '1',
                'delay'            => '1,1',
                'baseUrl'          => 'https://api.lk.psb.services/fo/v1.0.0',
                'email'            => '<fill-psb-email>',
                'password'         => '<fill-psb-password>',
                'off_days'         => '-',
                'inn_skip_list'    => '90, 91, 84, 85, 80, 81, 94, 96, 86, 20, 95, 12, 07, 08, 06, 04, 05, 09, 13, 14, 19, 30',
                'inn_only'         => '-',
                'skip_exist'       => 'no',
            ],
        ]);

        $this->command->info('Seeded 3 pipelines with 8 receivers total.');
    }
}