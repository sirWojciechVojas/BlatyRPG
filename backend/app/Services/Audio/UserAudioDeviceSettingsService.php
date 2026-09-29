<?php

namespace App\Services\Audio;

use App\Models\UserAudioDeviceSettingModel;
use App\Services\Auth\AuthException;
use CodeIgniter\Database\BaseConnection;

final class UserAudioDeviceSettingsService
{
    private $db;
    private $settings;
    private $validator;

    public function __construct(
        ?BaseConnection $db = null,
        ?UserAudioDeviceSettingModel $settings = null,
        ?UserAudioDeviceSettingsValidator $validator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->settings = $settings ?: new UserAudioDeviceSettingModel($this->db);
        $this->validator = $validator ?: new UserAudioDeviceSettingsValidator();
    }

    public function show(int $userId): array
    {
        $row = $this->settings->find($this->validUserId($userId));
        return $this->present(is_array($row) ? $row : []);
    }

    public function update(int $userId, array $payload): array
    {
        $userId = $this->validUserId($userId);
        $data = $this->validator->validate($payload);
        $now = date('Y-m-d H:i:s');
        $row = [
            'microphone_device_id' => $data['microphoneDeviceId'],
            'output_device_id' => $data['outputDeviceId'],
            'external_input_1_device_id' => $data['externalInputs']['external-1'],
            'external_input_2_device_id' => $data['externalInputs']['external-2'],
            'updated_at' => $now,
        ];

        $builder = $this->db->table('user_audio_device_settings');
        $exists = $builder->where('user_id', $userId)->countAllResults() > 0;
        $written = $exists
            ? $builder->where('user_id', $userId)->update($row)
            : $builder->insert($row + ['user_id' => $userId, 'created_at' => $now]);

        if (!$written) {
            throw new AuthException(
                'audio_device_settings_write_failed',
                'Audio device settings could not be saved.',
                500
            );
        }

        return $this->show($userId);
    }

    private function validUserId(int $userId): int
    {
        if ($userId < 1) {
            throw new AuthException('unauthorized', 'Authentication is required.', 401);
        }
        return $userId;
    }

    private function present(array $row): array
    {
        return [
            'microphoneDeviceId' => (string) ($row['microphone_device_id'] ?? ''),
            'outputDeviceId' => (string) ($row['output_device_id'] ?? ''),
            'externalInputs' => [
                'external-1' => (string) ($row['external_input_1_device_id'] ?? ''),
                'external-2' => (string) ($row['external_input_2_device_id'] ?? ''),
            ],
        ];
    }
}
