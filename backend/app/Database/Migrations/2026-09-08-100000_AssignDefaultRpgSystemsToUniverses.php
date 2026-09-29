<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Assigns a deliberate primary RPG game to every seeded setting. */
final class AssignDefaultRpgSystemsToUniverses extends Migration
{
    private const DEFAULT_GAMES = [
        'old_world' => 'wfrp2ed',
        'wh40k_galaxy' => 'wh40k_d100',
        'lovecraft_1920' => 'coc7e',
        'lovecraft_now' => 'delta_green',
        'pulp_cthulhu' => 'coc7e',
        'kult_metropolis' => 'kult_dl',
        'forgotten_realms' => 'dnd5e',
        'golarion' => 'pf2e',
        'middle_earth' => 'one_ring_2e',
        'wiedxmin_world' => 'wiedzon',
        'rokugan' => 'l5r5e',
        'symbaroum_world' => 'symbaroum_sys',
        'earthdawn_world' => 'earthdawn',
        'theah' => '7th_sea',
        'conan_world' => 'conan_2d20',
        'dragon_age_world' => 'dragon_age',
        'westeros' => 'game_of_thrones',
        'post_apoc_usa' => 'neuroshima_sys',
        'commonwealth' => 'dzikie_pola',
        'dominium' => 'monastyr_sys',
        'wolsung_world' => 'wolsung_sys',
        'orchia' => 'krysztyly_czasu',
        'night_city' => 'cyberpunk_red',
        'sixth_world' => 'shadowrun5',
        'alien_uni' => 'yze_alien',
        'star_wars_gal' => 'star_wars_ffg',
        'expanse_sys' => 'expanse',
        'coriolis_3h' => 'yze_coriolis',
        'numenera_world' => 'numenera',
        'tales_loop_80s' => 'yze_tales',
        'twilight_world' => 'yze_twilight',
        'dune_universe' => 'dune_2d20',
        'federation' => 'startrek_2d20',
        'traveller_uni' => 'traveller',
        'paranoia_complex' => 'paranoia',
        'wod_modern' => 'v5',
        'wod_medieval' => 'v_dark_ages',
        'weird_west' => 'deadlands_classic',
        'mythic_europe' => 'ars_magica',
        'doskvol' => 'blades_dark',
        'apocalypse_world' => 'pbta_generic',
        'dungeon_world' => 'dungeon_world',
        'four_nations' => 'avatar_legends',
        'glorantha' => 'runequest',
    ];

    public function up()
    {
        foreach (self::DEFAULT_GAMES as $universeCode => $systemCode) {
            $universe = $this->db->table('rpg_universes')->select('id')
                ->where('code', $universeCode)->get()->getRowArray();
            $system = $this->db->table('rpg_systems')->select('id')
                ->where('code', $systemCode)->get()->getRowArray();
            if (!$universe || !$system) continue;
            $universeId = (int) $universe['id'];
            $systemId = (int) $system['id'];
            $this->db->table('rpg_system_universes')->ignore(true)->insert([
                'system_id' => $systemId,
                'universe_id' => $universeId,
                'is_active' => 0,
            ]);
            $this->db->table('rpg_universes')->where('id', $universeId)->update([
                'default_system_id' => $systemId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        foreach (self::DEFAULT_GAMES as $universeCode => $systemCode) {
            $universe = $this->db->table('rpg_universes u')->select('u.id')
                ->join('rpg_systems s', 's.id=u.default_system_id', 'inner')
                ->where('u.code', $universeCode)->where('s.code', $systemCode)
                ->get()->getRowArray();
            if (!$universe) continue;
            $this->db->table('rpg_universes')->where('id', (int) $universe['id'])->update([
                'default_system_id' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
