<?php

namespace App\Services\Character;

use CodeIgniter\Database\BaseConnection;

/** Performs one conditional write so concurrent sheet edits cannot overwrite. */
final class CharacterRevisionWriter
{
    private $db;
    private $policy;

    public function __construct(
        ?BaseConnection $db = null,
        ?CharacterRevisionPolicy $policy = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->policy = $policy ?: new CharacterRevisionPolicy();
    }

    public function update(
        int $characterId,
        array $current,
        array $data,
        ?int $expectedRevision,
        ?string $expectedUpdatedAt
    ): void {
        $revision = $this->policy->assertExpected(
            $current,
            $expectedRevision,
            $expectedUpdatedAt
        );
        $write = $this->databaseData($data);
        $write['updated_at'] = $this->policy->nextTimestamp($current);

        $ok = $this->db->table('characters')
            ->set($write)
            ->set('revision', 'revision + 1', false)
            ->where('id', $characterId)
            ->where('revision', $revision)
            ->update();
        if (!$ok) {
            throw new CharacterException(
                'character_write_failed',
                'Character could not be saved.',
                500
            );
        }
        if ($this->db->affectedRows() !== 1) {
            $this->policy->throwConflict($revision);
        }
    }

    private function databaseData(array $data): array
    {
        if (!array_key_exists('data', $data)) {
            return $data;
        }
        $encoded = json_encode(
            $data['data'],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            throw new CharacterException(
                'validation_failed',
                'Character data could not be encoded.',
                422,
                ['data' => 'Character data must be valid JSON.']
            );
        }
        $data['data'] = $encoded;
        return $data;
    }
}
