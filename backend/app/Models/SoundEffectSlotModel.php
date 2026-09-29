<?php

namespace App\Models;

class SoundEffectSlotModel extends BaseJsonModel
{
    protected $table = 'sound_effect_slots';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'screen_id', 'slot_position', 'audio_track_id', 'name',
        'icon', 'color', 'shortcut', 'volume', 'play_mode', 'loop_enabled',
        'fade_in_ms', 'fade_out_ms', 'stop_others', 'audience_scope',
        'recipient_user_ids_json', 'created_by_user_id', 'updated_by_user_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $jsonFields = ['recipient_user_ids_json'];
}
