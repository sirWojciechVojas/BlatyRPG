<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Persistent, character-scoped WFRP 2e magic state.
 *
 * Definitions are deliberately separate from grants: publishing a spell never
 * teaches it to a character.  Player-visible and GM-private payloads are also
 * stored separately so the API never has to redact a shared JSON document.
 */
class CreateHeroMagic extends Migration
{
    public function up()
    {
        $this->createCatalog();
        $this->createHeroState();
        $this->createLearning();
        $this->createCasting();
        $this->createRitualsAndHistory();
        $this->seedVerifiedCatalog();
    }

    public function down()
    {
        foreach ([
            'hero_magic_history',
            'ritual_research',
            'cast_dice',
            'spell_casts',
            'hero_experience_expenses',
            'spell_learning_requests',
            'hero_spell_reveals',
            'hero_spell_preferences',
            'hero_spell_grants',
            'hero_magic_profiles',
            'hero_magic_path_spells',
            'hero_magic_spells',
            'hero_magic_paths',
            'hero_magic_traditions',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function createCatalog(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'system_code' => $this->varchar(32, 'wfrp2ed'),
            'setting_code' => $this->varchar(48, 'old_world'),
            'code' => $this->varchar(32),
            'name_pl' => $this->varchar(96),
            'name_en' => ['type' => 'VARCHAR', 'constraint' => 96, 'null' => true],
            'wind_code' => $this->varchar(16),
            'college_pl' => $this->varchar(96),
            'accent_color' => $this->varchar(16, '#7a3c25'),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['system_code', 'setting_code', 'code']);
        $this->forge->createTable('hero_magic_traditions', true);

        $this->forge->addField([
            'id' => $this->id(),
            'tradition_id' => $this->uint(),
            'code' => $this->varchar(24),
            'name_pl' => $this->varchar(64),
            'name_en' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'verified_complete' => $this->boolean(false),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tradition_id', 'code']);
        $this->forge->addForeignKey('tradition_id', 'hero_magic_traditions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_magic_paths', true);

        $this->forge->addField([
            'id' => $this->id(),
            'tradition_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'system_code' => $this->varchar(32, 'wfrp2ed'),
            'setting_code' => $this->varchar(48, 'old_world'),
            'code' => $this->varchar(80),
            'name_pl' => $this->varchar(160),
            'name_en' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'magic_type' => $this->varchar(32, 'arcane'),
            'casting_number' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'casting_time' => $this->varchar(80),
            'range_text' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'target_type' => $this->varchar(32, 'narrative'),
            'duration_text' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'ingredient_name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'ingredient_bonus' => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 0],
            'effect_summary' => ['type' => 'TEXT'],
            'defense_text' => ['type' => 'TEXT', 'null' => true],
            'special_rules_json' => ['type' => 'JSON', 'null' => true],
            'source_title' => $this->varchar(160, 'Królestwa Magii'),
            'source_page' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'source_version' => $this->varchar(32, 'pl-2e'),
            'verification_status' => $this->varchar(24, 'verified'),
            'definition_version' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'is_published' => $this->boolean(true),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['system_code', 'setting_code', 'code', 'definition_version']);
        $this->forge->addKey(['tradition_id', 'is_published']);
        $this->forge->addForeignKey('tradition_id', 'hero_magic_traditions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('hero_magic_spells', true);

        $this->forge->addField([
            'path_id' => $this->uint(),
            'spell_id' => $this->uint(),
            'sort_order' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey(['path_id', 'spell_id'], true);
        $this->forge->addUniqueKey(['path_id', 'sort_order']);
        $this->forge->addForeignKey('path_id', 'hero_magic_paths', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_magic_path_spells', true);
    }

    private function createHeroState(): void
    {
        $this->forge->addField([
            'id' => $this->id(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'tradition_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'path_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'has_arcane_magic' => $this->boolean(false),
            'chaos_dice' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('tradition_id', 'hero_magic_traditions', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('path_id', 'hero_magic_paths', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('hero_magic_profiles', true);

        $this->forge->addField([
            'id' => $this->id(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'spell_id' => $this->uint(),
            'grant_type' => $this->varchar(32),
            'source_reference' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'granted_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'granted_at' => $this->datetime(),
            'verification_status' => $this->varchar(24, 'verified'),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id', 'spell_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('granted_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('hero_spell_grants', true);

        $this->forge->addField([
            'id' => $this->id(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'spell_id' => $this->uint(),
            'is_favorite' => $this->boolean(false),
            'is_pinned' => $this->boolean(false),
            'pin_order' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
            'personal_note' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id', 'spell_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_spell_preferences', true);

        $this->forge->addField([
            'id' => $this->id(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'spell_id' => $this->uint(),
            'revealed_by_user_id' => $this->uint(),
            'source_label' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'revealed_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id', 'spell_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('revealed_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_spell_reveals', true);
    }

    private function createLearning(): void
    {
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'request_type' => $this->varchar(24),
            'spell_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'path_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'xp_cost' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'source_label' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'player_note' => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true],
            'world_date' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'status' => $this->varchar(24, 'pending'),
            'public_decision_note' => ['type' => 'VARCHAR', 'constraint' => 600, 'null' => true],
            'gm_private_note' => ['type' => 'TEXT', 'null' => true],
            'idempotency_key' => $this->varchar(128),
            'requested_by_user_id' => $this->uint(),
            'decided_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'decided_at' => $this->datetime(),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id', 'idempotency_key']);
        $this->forge->addKey(['campaign_id', 'status', 'created_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('path_id', 'hero_magic_paths', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('requested_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('decided_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('spell_learning_requests', true);

        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'learning_request_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'amount' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'balance_after' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('learning_request_id');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('learning_request_id', 'spell_learning_requests', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('hero_experience_expenses', true);
    }

    private function createCasting(): void
    {
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'spell_id' => $this->uint(),
            'idempotency_key' => $this->varchar(128),
            'status' => $this->varchar(24, 'declared'),
            'power_dice_count' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'chaos_dice_count' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'actions_required' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'actions_completed' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'ingredient_kind' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'ingredient_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'ingredient_name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'ingredient_bonus' => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 0],
            'targets_json' => ['type' => 'JSON', 'null' => true],
            'channel_attempted' => $this->boolean(false),
            'channel_succeeded' => $this->boolean(false),
            'channel_roll' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'channel_bonus' => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 0],
            'spell_snapshot_json' => ['type' => 'JSON'],
            'result_json' => ['type' => 'JSON', 'null' => true],
            'resolved_at' => $this->datetime(),
            'cancelled_at' => $this->datetime(),
            'created_by_user_id' => $this->uint(),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'character_id', 'idempotency_key']);
        $this->forge->addKey(['campaign_id', 'character_id', 'created_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('spell_id', 'hero_magic_spells', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('spell_casts', true);

        $this->forge->addField([
            'id' => $this->bigId(),
            'cast_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'die_role' => $this->varchar(32),
            'die_order' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'result' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'included_in_power' => $this->boolean(false),
            'included_in_curse' => $this->boolean(true),
            'included_in_automatic_failure' => $this->boolean(false),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['cast_id', 'die_role', 'die_order']);
        $this->forge->addForeignKey('cast_id', 'spell_casts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('cast_dice', true);
    }

    private function createRitualsAndHistory(): void
    {
        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'name' => $this->varchar(160),
            'intended_effect' => ['type' => 'TEXT'],
            'research_stage' => $this->varchar(24, 'concept'),
            'ritual_type' => ['type' => 'VARCHAR', 'constraint' => 48, 'null' => true],
            'language' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'minimum_magic' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
            'learning_cost_xp' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'casting_number' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'casting_time' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'requirements_json' => ['type' => 'JSON', 'null' => true],
            'ingredients_json' => ['type' => 'JSON', 'null' => true],
            'known_consequences' => ['type' => 'TEXT', 'null' => true],
            'gm_private_json' => ['type' => 'JSON', 'null' => true],
            'is_author_research' => $this->boolean(true),
            'status' => $this->varchar(24, 'draft'),
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_by_user_id' => $this->uint(),
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'character_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('ritual_research', true);

        $this->forge->addField([
            'id' => $this->bigId(),
            'campaign_id' => $this->uint(),
            'character_id' => $this->uint(),
            'event_type' => $this->varchar(40),
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'reference_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'public_payload_json' => ['type' => 'JSON'],
            'gm_payload_json' => ['type' => 'JSON', 'null' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => $this->datetime(),
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'character_id', 'created_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('hero_magic_history', true);
    }

    private function seedVerifiedCatalog(): void
    {
        $now = date('Y-m-d H:i:s');
        $traditions = [
            ['shadow', 'Cienia', 'Shadow', 'Ulgu', 'Cienia', '#665b7d'],
            ['metal', 'Metalu', 'Metal', 'Chamon', 'Złota', '#9b7a32'],
            ['heavens', 'Niebios', 'Heavens', 'Azyr', 'Niebios', '#376d9b'],
            ['fire', 'Ognia', 'Fire', 'Aqshy', 'Płomienia', '#9e3928'],
            ['death', 'Śmierci', 'Death', 'Shyish', 'Ametystu', '#6b487f'],
            ['light', 'Światła', 'Light', 'Hysh', 'Światła', '#c7a84c'],
            ['beasts', 'Zwierząt', 'Beasts', 'Ghur', 'Bursztynu', '#8a5a2b'],
            ['life', 'Życia', 'Life', 'Ghyran', 'Jadeitu', '#3d7650'],
        ];
        foreach ($traditions as [$code, $pl, $en, $wind, $college, $color]) {
            $this->db->table('hero_magic_traditions')->insert([
                'system_code' => 'wfrp2ed', 'setting_code' => 'old_world',
                'code' => $code, 'name_pl' => $pl, 'name_en' => $en,
                'wind_code' => $wind, 'college_pl' => $college,
                'accent_color' => $color, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $traditionId = (int) $this->db->insertID();
            foreach ([['main', 'Główna', 'Main'], ['mystic', 'Mistyczna', 'Mystic'], ['elemental', 'Żywiołu', 'Elemental']] as [$path, $pathPl, $pathEn]) {
                $this->db->table('hero_magic_paths')->insert([
                    'tradition_id' => $traditionId, 'code' => $path,
                    'name_pl' => $pathPl, 'name_en' => $pathEn,
                    'verified_complete' => $code === 'fire' && $path === 'elemental' ? 1 : 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $fire = $this->db->table('hero_magic_traditions')->where('code', 'fire')->get()->getRowArray();
        $path = $this->db->table('hero_magic_paths')->where([
            'tradition_id' => $fire['id'], 'code' => 'elemental',
        ])->get()->getRowArray();
        $spells = [
            ['scorch', 'Przypalenie', 4, '1 akcja', 'Dotyk', 'touch', null, 'Kawałek węgla drzewnego', 1, 'Wypala otwartą ranę; jest traktowane jak pomoc medyczna, ale nie przywraca Żywotności.'],
            ['uzhul-fire', "Ogień U’Zhul", 6, '1 akcja', '36 m', 'single', null, 'Zapałka', 1, 'Magiczny pocisk o Sile 4.'],
            ['fiery-crown', 'Ognista korona', 8, 'Akcja podwójna', 'Własna postać', 'self', 'Mag minut', 'Złota moneta', 1, 'Płomienna korona daje +20 do Dowodzenia i Zastraszania oraz zapewnia światło.'],
            ['fireball', 'Ognista kula', 12, '1 akcja', '48 m', 'missiles', 'Natychmiastowy', 'Bryłka siarki', 2, 'Tworzy liczbę magicznych pocisków równą Mag; każdy ma Siłę 3 i może trafić widoczny cel.'],
            ['aqshy-shield', 'Tarcza Aqshy', 12, 'Akcja podwójna', 'Własna postać', 'self', '1k10 minut', 'Żelazny amulet', 2, '+20 do Odporności przeciwko ognistym atakom.'],
            ['flaming-sword', 'Gorejący miecz', 14, '1 akcja', 'Własna postać', 'self', 'Mag rund; możliwe przedłużanie testem SW', 'Pochodnia', 1, 'Tworzy magiczny miecz z cechą Druzgoczący i Siłą 4; podczas działania daje +1 Atak.'],
            ['hearts-fire', 'Żar serc', 16, 'Akcja podwójna', 'Dotyk', 'touch', '10 minut', 'Kosmyk rudych włosów', 2, 'Rozpala gwałtowne emocje; szczegóły efektu rozstrzyga definicja i MG.'],
            ['fiery-blast', 'Ognisty podmuch', 22, 'Akcja podwójna', '48 m', 'missiles', 'Natychmiastowy', null, 0, 'Tworzy ogniste podmuchy traktowane jako magiczne pociski; rozdział i obrażenia rozstrzyga moduł walki.'],
            ['fiery-breath', 'Ognisty dech', 25, 'Akcja podwójna', 'Stożek 16 m', 'area', 'Natychmiastowy', 'Łuska smoka', 3, 'Stożek ognia; trafienia i test obrony rozstrzyga moduł walki.'],
            ['doomfire', 'Pożoga zagłady', 31, '3 akcje', '48 m; promień 5 m', 'area', 'Do śmierci wszystkich istot w sferze', 'Ząb smoka', 3, 'Tworzy niszczycielską strefę ognia; efekty wymagają rozstrzygnięcia walki i MG.'],
        ];
        foreach ($spells as $order => [$code, $name, $cn, $time, $range, $target, $duration, $ingredient, $bonus, $summary]) {
            $special = $code === 'fireball'
                ? ['missiles' => ['count' => 'magic', 'strength' => 3], 'requiresLineOfSight' => true]
                : ($code === 'flaming-sword' ? ['effects' => [['trait' => 'attacks', 'delta' => 1], ['temporaryWeapon' => true]]] : []);
            $this->db->table('hero_magic_spells')->insert([
                'tradition_id' => (int) $fire['id'], 'system_code' => 'wfrp2ed',
                'setting_code' => 'old_world', 'code' => $code, 'name_pl' => $name,
                'magic_type' => 'arcane', 'casting_number' => $cn,
                'casting_time' => $time, 'range_text' => $range,
                'target_type' => $target, 'duration_text' => $duration,
                'ingredient_name' => $ingredient, 'ingredient_bonus' => $bonus,
                'effect_summary' => $summary,
                'special_rules_json' => json_encode($special, JSON_UNESCAPED_UNICODE),
                'source_title' => 'Królestwa Magii', 'source_page' => in_array($code, ['fireball', 'aqshy-shield', 'flaming-sword'], true) ? 151 : 150,
                'source_version' => 'pl-2e', 'verification_status' => 'verified',
                'definition_version' => 1, 'is_published' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->table('hero_magic_path_spells')->insert([
                'path_id' => (int) $path['id'], 'spell_id' => (int) $this->db->insertID(),
                'sort_order' => $order + 1,
            ]);
        }
    }

    private function id(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true];
    }

    private function bigId(): array
    {
        return ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true];
    }

    private function uint(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true];
    }

    private function varchar(int $length, ?string $default = null): array
    {
        $field = ['type' => 'VARCHAR', 'constraint' => $length];
        if ($default !== null) {
            $field['default'] = $default;
        }
        return $field;
    }

    private function boolean(bool $default): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => $default ? 1 : 0];
    }

    private function datetime(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }
}
