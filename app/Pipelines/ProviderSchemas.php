<?php

declare(strict_types=1);

namespace App\Pipelines;

/**
 * Describes configuration fields for each provider type.
 *
 * Just like BankAdapter::configSchema() describes what fields a bank
 * receiver needs in its `tune`, this class describes what fields a
 * provider (source of leads) needs in its `provider_config`.
 */
final class ProviderSchemas
{
    /**
     * @return array<string, array{type: string, label: string, required?: bool, default?: mixed, hint?: string}>
     */
    public static function get(string $provider): array
    {
        return match ($provider) {
            'skorozvon'   => self::skorozvon(),
            'file_upload' => self::fileUpload(),
            default       => [],
        };
    }

    /** @return string[] */
    public static function types(): array
    {
        return [
            'skorozvon'   => 'Скорозвон',
            'file_upload' => 'Загрузка файлов',
        ];
    }

    /** @return array<string, array{type: string, label: string, required?: bool, hint?: string}> */
    private static function skorozvon(): array
    {
        return [
            'url'            => ['type' => 'url',      'label' => 'URL',               'required' => true],
            'username'       => ['type' => 'email',     'label' => 'Логин',              'required' => true],
            'api_key'        => ['type' => 'password',  'label' => 'API Key',            'required' => true],
            'client_id'      => ['type' => 'text',      'label' => 'Client ID',          'hint' => 'Скорозвон Client ID'],
            'client_secret'  => ['type' => 'password',  'label' => 'Client Secret',      'hint' => 'Скорозвон Client Secret'],
            'lead_word'      => ['type' => 'text',      'label' => 'Lead Words',         'hint' => 'Ключевые слова статусов через запятую'],
            'scenario_id'    => ['type' => 'text',      'label' => 'Scenario ID',        'hint' => 'ID сценария Скорозвон'],
            'call_project_id'=> ['type' => 'text',      'label' => 'Call Project ID',    'hint' => 'ID проекта звонков'],
        ];
    }

    /** @return array<string, array{type: string, label: string, required?: bool, hint?: string}> */
    private static function fileUpload(): array
    {
        return [
            'custom_name' => ['type' => 'text', 'label' => 'Название аккаунта'],
            'uniq_name'   => ['type' => 'text', 'label' => 'Уникальное имя',  'hint' => 'Идентификатор для загрузки файлов'],
            'skip_exist'  => ['type' => 'select','label' => 'Skip Existing',   'default' => 'no'],
        ];
    }
}