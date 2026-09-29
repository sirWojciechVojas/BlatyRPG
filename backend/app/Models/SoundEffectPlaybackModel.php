<?php

namespace App\Models;

class SoundEffectPlaybackModel extends BaseJsonModel
{
    protected $table = 'sound_effect_playbacks';
    protected $primaryKey = 'playback_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = [
        'playback_id', 'campaign_id', 'slot_id', 'audio_track_id',
        'started_at_ms', 'execute_at_ms', 'duration_seconds', 'volume',
        'loop_enabled', 'fade_in_ms', 'fade_out_ms', 'audience_scope',
        'recipient_user_ids_json', 'created_by_user_id', 'stopped_at_ms',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $jsonFields = ['recipient_user_ids_json'];
}
