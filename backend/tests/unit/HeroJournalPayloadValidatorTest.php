<?php

namespace Tests\Unit;

use App\Services\Journal\HeroJournalPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class HeroJournalPayloadValidatorTest extends CIUnitTestCase
{
    public function testSensitiveSectionsDefaultToPrivate(): void
    {
        $result = (new HeroJournalPayloadValidator())->validateCreate([
            'type' => 'npc',
            'title' => 'Magister Elric',
            'status' => 'in_progress',
            'visibility' => 'campaign',
            'sections' => [
                ['key' => 'player_knowledge', 'content' => 'Kupiec z Altdorfu.'],
                ['key' => 'subjective_impression', 'content' => 'Nie ufam mu.'],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['nested']['sections'][0]['visibility']);
        $this->assertSame('private', $result['nested']['sections'][1]['visibility']);
    }

    public function testRejectsUnknownTypesAndRelations(): void
    {
        $validator = new HeroJournalPayloadValidator();
        $entry = $validator->validateCreate(['type' => 'secret', 'title' => 'X']);
        $relation = $validator->validateRelation([
            'targetEntryId' => 2,
            'relationType' => 'unsafe',
        ]);

        $this->assertFalse($entry['valid']);
        $this->assertArrayHasKey('type', $entry['errors']);
        $this->assertFalse($relation['valid']);
    }
}
