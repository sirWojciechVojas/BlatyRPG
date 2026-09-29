<?php

use App\Services\Shop\LegacyCharacterInventoryImporter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LegacyCharacterInventoryImporterTest extends CIUnitTestCase
{
    public function testItPreservesLegacySlotQuantityAndOverrides(): void
    {
        $payload = LegacyCharacterInventoryImporter::instancePayload(
            [17, 7, 'armR1', 'Atlasowy miecz', 'Pamiątka', 321, 3, 1, 4],
            [7, 'Miecz', 'Opis podstawowy']
        );
        $meta = json_decode($payload['data_override_json'], true);

        $this->assertSame('Atlasowy miecz', $payload['name_override']);
        $this->assertSame('Pamiątka', $payload['note']);
        $this->assertSame('armR1', $meta['SLOT']);
        $this->assertSame('armR1', $meta['ITEM_PLACE']);
        $this->assertSame(3, $meta['QUANTITY']);
        $this->assertSame(17, $meta['LEGACY_INVENTORY_ID']);
    }

    public function testItCreatesAUsableCurrentTemplatePayload(): void
    {
        $payload = LegacyCharacterInventoryImporter::templatePayload(
            [42, 'Kusza', 'Opis', 'Detale', 'WEAPON', 91, 'arms', 'v0170', 9600, 50]
        );

        $this->assertSame('Kusza', $payload['name']);
        $this->assertSame('WEAPON', $payload['item_class']);
        $this->assertSame('arms', $payload['item_genre']);
        $this->assertSame('v0170', $payload['img_class']);
        $this->assertSame(9600, $payload['prize']);
    }

    public function testItPreservesLegacyWeaponMetadataInTemplatePayload(): void
    {
        $weapon = LegacyCharacterInventoryImporter::weaponPayload([
            91, 'Miecz dwuręczny', 'Opis broni', 'sieczna', 'broń dwuręczna',
            'zwykła', '2K6', '+1WW', 250, '2', 'S+2', '', '', 'ciężka', '1', 0,
        ]);
        $payload = LegacyCharacterInventoryImporter::templatePayload(
            [91, 'Miecz dwuręczny', 'Opis', '', 'WEAPON', 91, 'arms', 'item', 250, 0],
            'wfrp_empire',
            $weapon
        );
        $savedWeapon = json_decode($payload['weapon_json'], true);

        $this->assertSame('broń dwuręczna', $savedWeapon['HANDED']);
        $this->assertSame('S+2', $savedWeapon['DAMAGE']);
    }
}
