<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Data\WfrpData;
use CodeIgniter\Database\Seeder;

class CharacterProfessionsLegacySeeder extends Seeder
{
    public function run()
    {
        if (!class_exists(WfrpData::class)) {
            echo "Brak WfrpData!\n";
            return;
        }

        if (!method_exists(WfrpData::class, 'getBgStart') || !method_exists(WfrpData::class, 'getBgCurrent')) {
            echo "WfrpData nie zawiera danych w_bg_start / w_bg_current.\n";
            return;
        }

        $bgStartRows = WfrpData::getBgStart();
        $bgCurrentRows = WfrpData::getBgCurrent();

        if (empty($bgStartRows) || empty($bgCurrentRows)) {
            echo "Brak danych w tabelach w_bg_start / w_bg_current w WfrpData.\n";
            return;
        }

        // Mapowanie USEDNAME_ID -> usedname (kolumna 1 lub 2)
        $usednameMap = [];
        foreach ($bgStartRows as $row) {
            $id = (int)($row[0] ?? 0);
            $usedname = $row[1] ?? ($row[2] ?? null);
            if ($id && $usedname) {
                $usednameMap[$id] = $usedname;
            }
        }

        $db = \Config\Database::connect();
        $charTable = $db->table('characters');
        $pivot = $db->table('character_professions');
        $profTable = $db->table('professions');

        $inserted = 0;
        $skippedMissingProf = 0;
        $skippedMissingChar = 0;

        foreach ($bgCurrentRows as $row) {
            $usednameId = (int)($row[0] ?? 0);
            $curCareerId = (int)($row[1] ?? 0);
            $prevCareersRaw = $row[2] ?? '';
            $prevCareerIds = array_filter(array_map('intval', array_filter(array_map('trim', explode(',', (string)$prevCareersRaw)))), fn($v) => $v > 0);

            if (!$usednameId || !$curCareerId) {
                continue;
            }

            $usedname = $usednameMap[$usednameId] ?? null;
            if (!$usedname) {
                continue;
            }

            $char = $charTable->where('name', $usedname)->get()->getRow();
            if (!$char) {
                $char = $charTable->where("JSON_UNQUOTE(JSON_EXTRACT(data, '$.details.true_name')) =", $usedname)->get()->getRow();
            }
            if (!$char) {
                $skippedMissingChar++;
                continue;
            }

            // sprawdź czy profesja istnieje w nowej tabeli
            $profExists = $profTable->where('id', $curCareerId)->countAllResults();
            if ($profExists == 0) {
                $skippedMissingProf++;
                continue;
            }

            $exists = $pivot->where('character_id', $char->id)
                ->where('profession_id', $curCareerId)
                ->countAllResults();
            if ($exists > 0) {
                continue;
            }

            $pivot->insert([
                'character_id' => $char->id,
                'profession_id' => $curCareerId,
                'is_current' => 1,
                'is_finished' => 0,
                'started_at' => null,
                'finished_at' => null,
            ]);
            $inserted++;

            // Dodaj poprzednie profesje jako zakończone (is_current = 0, is_finished = 1)
            foreach ($prevCareerIds as $prevId) {
                $profExistsPrev = $profTable->where('id', $prevId)->countAllResults();
                if ($profExistsPrev == 0) {
                    $skippedMissingProf++;
                    continue;
                }

                $existsPrev = $pivot->where('character_id', $char->id)
                    ->where('profession_id', $prevId)
                    ->countAllResults();
                if ($existsPrev > 0) {
                    continue;
                }

                $pivot->insert([
                    'character_id' => $char->id,
                    'profession_id' => $prevId,
                    'is_current' => 0,
                    'is_finished' => 1,
                    'started_at' => null,
                    'finished_at' => null,
                ]);
                $inserted++;
            }
        }

        echo "Uzupełniono character_professions: $inserted rekordów. Pominieto (brak profesji): $skippedMissingProf, brak postaci: $skippedMissingChar.\n";
    }
}
