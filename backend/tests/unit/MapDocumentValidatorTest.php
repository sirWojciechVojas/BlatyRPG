<?php

use App\Services\MapBuilder\MapDocumentValidator;
use PHPUnit\Framework\TestCase;

final class MapDocumentValidatorTest extends TestCase
{
    public function testValidatesVersionedDocumentAndAssetIds(): void
    {
        $document = $this->document();
        $document['objects'][] = [
            'id' => 'asset-1', 'type' => 'asset', 'layerId' => 'objects',
            'levelId' => 'ground',
            'x' => 100, 'y' => 100, 'width' => 100, 'height' => 100,
            'assetId' => 'starter.tavern-table',
        ];
        $result = (new MapDocumentValidator())->validateDocument($document);
        self::assertTrue($result['valid'], json_encode($result['errors']));
    }

    public function testRejectsUnknownAssetsAndCoordinatesOutsideBounds(): void
    {
        $document = $this->document();
        $document['objects'][] = [
            'id' => 'asset-1', 'type' => 'asset', 'layerId' => 'objects',
            'levelId' => 'ground',
            'x' => 9000, 'y' => 100, 'width' => 100, 'height' => 100,
            'assetId' => 'starter.not-real',
        ];
        $result = (new MapDocumentValidator())->validateDocument($document);
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('objects.0.x', $result['errors']);
        self::assertArrayHasKey('objects.0.assetId', $result['errors']);
    }

    public function testAiCannotRemoveLockedObjects(): void
    {
        $document = $this->document();
        $document['objects'][] = [
            'id' => 'locked', 'type' => 'room', 'layerId' => 'objects',
            'levelId' => 'ground',
            'x' => 100, 'y' => 100, 'width' => 100, 'height' => 100, 'locked' => true,
        ];
        $result = (new MapDocumentValidator())->validateAiProposal(
            ['removeObjectIds' => ['locked'], 'objects' => []],
            $document
        );
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('removeObjectIds.0', $result['errors']);
    }

    public function testAiRespectsLayerLocks(): void
    {
        $document = $this->document();
        $document['layers'][0]['locked'] = true;
        $document['objects'][] = [
            'id' => 'layer-locked', 'type' => 'room', 'layerId' => 'objects',
            'levelId' => 'ground',
            'x' => 100, 'y' => 100, 'width' => 100, 'height' => 100,
        ];
        $result = (new MapDocumentValidator())->validateAiProposal(
            [
                'removeObjectIds' => ['layer-locked'],
                'objects' => [[
                    'id' => 'new-locked', 'type' => 'room', 'layerId' => 'objects',
                    'levelId' => 'ground',
                    'x' => 200, 'y' => 200, 'width' => 100, 'height' => 100,
                ]],
            ],
            $document
        );
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('removeObjectIds.0', $result['errors']);
        self::assertArrayHasKey('objects.0.layerId', $result['errors']);
    }

    public function testValidatesEditableBooleanRoomParts(): void
    {
        $document = $this->document();
        $document['objects'][] = [
            'id' => 'combined-room', 'type' => 'room', 'layerId' => 'objects',
            'levelId' => 'ground',
            'x' => 500, 'y' => 500, 'width' => 800, 'height' => 600,
            'shape' => 'composite',
            'booleanParts' => [
                [
                    'shape' => 'rect', 'operation' => 'add',
                    'x' => 500, 'y' => 500, 'width' => 800, 'height' => 600,
                ],
                [
                    'shape' => 'rect', 'operation' => 'subtract',
                    'x' => 500, 'y' => 500, 'width' => 200, 'height' => 200,
                ],
            ],
        ];
        $result = (new MapDocumentValidator())->validateDocument($document);
        self::assertTrue($result['valid'], json_encode($result['errors']));
    }

    private function document(): array
    {
        return [
            'schemaVersion' => 1,
            'width' => 4000,
            'height' => 3000,
            'pixelsPerMeter' => 100,
            'levels' => [['id' => 'ground', 'name' => 'Parter']],
            'activeLevelId' => 'ground',
            'layers' => [['id' => 'objects']],
            'objects' => [],
        ];
    }
}
