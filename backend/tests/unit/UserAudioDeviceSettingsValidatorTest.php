<?php

use App\Services\Audio\UserAudioDeviceSettingsValidator;
use App\Services\Auth\AuthException;
use CodeIgniter\Test\CIUnitTestCase;

final class UserAudioDeviceSettingsValidatorTest extends CIUnitTestCase
{
    public function testItNormalizesDefaultAndNamedAccountDevices(): void
    {
        $result = (new UserAudioDeviceSettingsValidator())->validate([
            'microphoneDeviceId' => 'microphone-admin',
            'outputDeviceId' => '',
            'externalInputs' => [
                'external-1' => 'voicemeeter-aux',
                'external-2' => null,
            ],
        ]);

        $this->assertSame('microphone-admin', $result['microphoneDeviceId']);
        $this->assertNull($result['outputDeviceId']);
        $this->assertSame('voicemeeter-aux', $result['externalInputs']['external-1']);
        $this->assertNull($result['externalInputs']['external-2']);
    }

    public function testItRejectsUnknownInputsAndUnsafeIdentifiers(): void
    {
        try {
            (new UserAudioDeviceSettingsValidator())->validate([
                'microphoneDeviceId' => "unsafe\nidentifier",
                'outputDeviceId' => null,
                'externalInputs' => ['external-3' => 'not-supported'],
                'userId' => 99,
            ]);
            $this->fail('Invalid device settings should throw.');
        } catch (AuthException $exception) {
            $this->assertSame('audio_device_settings_invalid', $exception->errorCode());
            $this->assertSame(422, $exception->status());
            $this->assertArrayHasKey('microphoneDeviceId', $exception->details());
            $this->assertArrayHasKey('externalInputs.external-3', $exception->details());
            $this->assertArrayHasKey('userId', $exception->details());
        }
    }
}
