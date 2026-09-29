<?php

namespace App\Services\MapBuilder;

/** Strict boundary validator shared by persistence, publication and AI proposals. */
final class MapDocumentValidator
{
    public const SCHEMA_VERSION = 1;
    public const MAX_DOCUMENT_BYTES = 16777216;
    public const MAX_OBJECTS = 20000;

    private const OBJECT_TYPES = [
        'asset', 'terrain', 'brush', 'path', 'road', 'river', 'fence', 'room',
        'wall', 'door', 'window', 'text', 'marker', 'light', 'group',
    ];

    private const STARTER_ASSET_IDS = [
        'starter.oak', 'starter.pine', 'starter.boulder', 'starter.shrub',
        'starter.tavern-table', 'starter.chair', 'starter.barrel', 'starter.bed',
        'starter.hearth', 'starter.door', 'starter.window', 'starter.torch',
        'starter.cart', 'starter.well', 'starter.crate', 'starter.campfire',
        'starter.material.grass', 'starter.material.dirt', 'starter.material.stone',
        'starter.material.wood', 'starter.material.water',
        'starter.composition.tavern-room', 'starter.composition.courtyard',
        'starter.composition.camp',
    ];

    public function validateDocument(array $document, ?callable $assetExists = null): array
    {
        $errors = [];
        if (($document['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            $errors['schemaVersion'] = 'Unsupported map document version.';
        }
        $width = $this->number($document['width'] ?? null, 800, 50000);
        $height = $this->number($document['height'] ?? null, 600, 50000);
        if ($width === null) $errors['width'] = 'Width must be between 800 and 50000.';
        if ($height === null) $errors['height'] = 'Height must be between 600 and 50000.';
        if ($this->number($document['pixelsPerMeter'] ?? null, 10, 1000) === null) {
            $errors['pixelsPerMeter'] = 'Scale must be between 10 and 1000 pixels per meter.';
        }
        $layers = $document['layers'] ?? null;
        $levels = $document['levels'] ?? null;
        $objects = $document['objects'] ?? null;
        if (!is_array($layers) || !$layers || count($layers) > 128) {
            $errors['layers'] = 'Map must contain between 1 and 128 layers.';
            $layers = [];
        }
        if (!is_array($levels) || !$levels || count($levels) > 32) {
            $errors['levels'] = 'Map must contain between 1 and 32 building levels.';
            $levels = [];
        }
        if (!is_array($objects) || count($objects) > self::MAX_OBJECTS) {
            $errors['objects'] = 'Map object collection is invalid.';
            $objects = [];
        }
        $layerIds = [];
        foreach ($layers as $index => $layer) {
            $id = is_array($layer) ? trim((string) ($layer['id'] ?? '')) : '';
            if (!$this->identifier($id) || isset($layerIds[$id])) {
                $errors["layers.{$index}.id"] = 'Layer identifier is invalid or duplicated.';
            } else {
                $layerIds[$id] = true;
            }
        }
        $levelIds = [];
        foreach ($levels as $index => $level) {
            $id = is_array($level) ? trim((string) ($level['id'] ?? '')) : '';
            if (!$this->identifier($id) || isset($levelIds[$id])) {
                $errors["levels.{$index}.id"] = 'Level identifier is invalid or duplicated.';
            } else {
                $levelIds[$id] = true;
            }
        }
        if (!isset($levelIds[(string) ($document['activeLevelId'] ?? '')])) {
            $errors['activeLevelId'] = 'Active level does not exist.';
        }
        $objectIds = [];
        foreach ($objects as $index => $object) {
            if (!is_array($object)) {
                $errors["objects.{$index}"] = 'Object must be a JSON object.';
                continue;
            }
            $id = trim((string) ($object['id'] ?? ''));
            if (!$this->identifier($id) || isset($objectIds[$id])) {
                $errors["objects.{$index}.id"] = 'Object identifier is invalid or duplicated.';
            } else {
                $objectIds[$id] = true;
            }
            $type = strtolower(trim((string) ($object['type'] ?? '')));
            if (!in_array($type, self::OBJECT_TYPES, true)) {
                $errors["objects.{$index}.type"] = 'Object type is unsupported.';
            }
            if (!isset($layerIds[(string) ($object['layerId'] ?? '')])) {
                $errors["objects.{$index}.layerId"] = 'Object references an unknown layer.';
            }
            if (!isset($levelIds[(string) ($object['levelId'] ?? '')])) {
                $errors["objects.{$index}.levelId"] = 'Object references an unknown building level.';
            }
            foreach (['x' => $width, 'y' => $height] as $field => $maximum) {
                $coordinate = $this->number($object[$field] ?? null, 0, (float) ($maximum ?: 50000));
                if ($coordinate === null) $errors["objects.{$index}.{$field}"] = 'Coordinate is outside map bounds.';
            }
            foreach (['width', 'height'] as $field) {
                if ($this->number($object[$field] ?? null, 1, 100000) === null) {
                    $errors["objects.{$index}.{$field}"] = 'Object size is invalid.';
                }
            }
            if (isset($object['points'])) {
                if (!is_array($object['points']) || count($object['points']) > 2048) {
                    $errors["objects.{$index}.points"] = 'Path contains too many points.';
                } else {
                    foreach ($object['points'] as $pointIndex => $point) {
                        if (!is_array($point)
                            || $this->number($point['x'] ?? null, 0, (float) ($width ?: 50000)) === null
                            || $this->number($point['y'] ?? null, 0, (float) ($height ?: 50000)) === null) {
                            $errors["objects.{$index}.points.{$pointIndex}"] = 'Path point is outside map bounds.';
                        }
                    }
                }
            }
            if (isset($object['booleanParts'])) {
                $parts = $object['booleanParts'];
                if (!is_array($parts) || count($parts) < 2 || count($parts) > 256) {
                    $errors["objects.{$index}.booleanParts"] = 'Room boolean parts are invalid.';
                } else {
                    foreach ($parts as $partIndex => $part) {
                        if (!is_array($part)
                            || !in_array(($part['shape'] ?? ''), ['rect', 'circle', 'polygon'], true)
                            || !in_array(($part['operation'] ?? ''), ['add', 'subtract'], true)) {
                            $errors["objects.{$index}.booleanParts.{$partIndex}"] = 'Boolean room part is invalid.';
                            continue;
                        }
                        foreach (['x' => $width, 'y' => $height] as $field => $maximum) {
                            if ($this->number($part[$field] ?? null, 0, (float) ($maximum ?: 50000)) === null) {
                                $errors["objects.{$index}.booleanParts.{$partIndex}.{$field}"] = 'Coordinate is outside map bounds.';
                            }
                        }
                        foreach (['width', 'height'] as $field) {
                            if ($this->number($part[$field] ?? null, 1, 100000) === null) {
                                $errors["objects.{$index}.booleanParts.{$partIndex}.{$field}"] = 'Part size is invalid.';
                            }
                        }
                        if (($part['shape'] ?? '') === 'polygon') {
                            $partPoints = $part['points'] ?? null;
                            if (!is_array($partPoints) || count($partPoints) < 3 || count($partPoints) > 2048) {
                                $errors["objects.{$index}.booleanParts.{$partIndex}.points"] = 'Polygon points are invalid.';
                            } else {
                                foreach ($partPoints as $pointIndex => $point) {
                                    if (!is_array($point)
                                        || $this->number($point['x'] ?? null, 0, (float) ($width ?: 50000)) === null
                                        || $this->number($point['y'] ?? null, 0, (float) ($height ?: 50000)) === null) {
                                        $errors["objects.{$index}.booleanParts.{$partIndex}.points.{$pointIndex}"]
                                            = 'Polygon point is outside map bounds.';
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $assetId = trim((string) ($object['assetId'] ?? ''));
            if ($assetId !== '' && !$this->assetExists($assetId, $assetExists)) {
                $errors["objects.{$index}.assetId"] = 'Object references an unknown asset.';
            }
        }
        $encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > self::MAX_DOCUMENT_BYTES) {
            $errors['document'] = 'Map document exceeds the 16 MB metadata limit.';
        }
        return ['valid' => !$errors, 'errors' => $errors, 'json' => $encoded ?: ''];
    }

    public function validateAiProposal(array $proposal, array $document, ?callable $assetExists = null): array
    {
        $errors = [];
        $objects = $proposal['objects'] ?? null;
        if (!is_array($objects) || count($objects) > 2000) {
            return ['valid' => false, 'errors' => ['objects' => 'AI proposal object list is invalid.']];
        }
        $locked = [];
        $lockedLayers = [];
        foreach ($document['layers'] ?? [] as $layer) {
            if (!empty($layer['locked'])) $lockedLayers[(string) ($layer['id'] ?? '')] = true;
        }
        foreach ($document['objects'] ?? [] as $object) {
            if (!empty($object['locked']) || isset($lockedLayers[(string) ($object['layerId'] ?? '')])) {
                $locked[(string) ($object['id'] ?? '')] = true;
            }
        }
        foreach ($proposal['removeObjectIds'] ?? [] as $index => $id) {
            if (isset($locked[(string) $id])) {
                $errors["removeObjectIds.{$index}"] = 'AI cannot remove a locked object.';
            }
        }
        $candidate = $document;
        $remove = array_fill_keys(array_map('strval', $proposal['removeObjectIds'] ?? []), true);
        $candidate['objects'] = array_values(array_filter(
            $candidate['objects'] ?? [],
            static fn (array $object): bool => !isset($remove[(string) ($object['id'] ?? '')])
        ));
        foreach ($objects as $index => $object) {
            if (isset($lockedLayers[(string) ($object['layerId'] ?? '')])) {
                $errors["objects.{$index}.layerId"] = 'AI cannot add objects to a locked layer.';
            }
            $candidate['objects'][] = $object;
        }
        $validated = $this->validateDocument($candidate, $assetExists);
        $errors += $validated['errors'];
        $walls = array_values(array_filter(
            $candidate['objects'],
            static fn (array $object): bool => in_array(($object['type'] ?? ''), ['wall', 'fence'], true)
        ));
        foreach ($objects as $index => $object) {
            if (!in_array(($object['type'] ?? ''), ['door', 'window'], true)) continue;
            if (!$this->nearWall($object, $walls, 100)) {
                $errors["objects.{$index}.opening"] = 'Door or window is not connected to a wall.';
            }
        }
        return ['valid' => !$errors, 'errors' => $errors];
    }

    public function starterAssetIds(): array
    {
        return self::STARTER_ASSET_IDS;
    }

    private function assetExists(string $assetId, ?callable $assetExists): bool
    {
        if (in_array($assetId, self::STARTER_ASSET_IDS, true)) return true;
        return strpos($assetId, 'custom.') === 0 && $assetExists && (bool) $assetExists($assetId);
    }

    private function identifier(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) === 1;
    }

    private function number($value, float $minimum, float $maximum): ?float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) return null;
        $number = (float) $value;
        return $number >= $minimum && $number <= $maximum ? $number : null;
    }

    private function nearWall(array $opening, array $walls, float $tolerance): bool
    {
        $x = (float) ($opening['x'] ?? 0);
        $y = (float) ($opening['y'] ?? 0);
        foreach ($walls as $wall) {
            $points = $wall['points'] ?? [];
            for ($index = 1; $index < count($points); $index++) {
                if ($this->pointSegmentDistance($x, $y, $points[$index - 1], $points[$index]) <= $tolerance) {
                    return true;
                }
            }
        }
        return !$walls;
    }

    private function pointSegmentDistance(float $x, float $y, array $start, array $end): float
    {
        $x1 = (float) ($start['x'] ?? 0);
        $y1 = (float) ($start['y'] ?? 0);
        $x2 = (float) ($end['x'] ?? 0);
        $y2 = (float) ($end['y'] ?? 0);
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        if ($dx === 0.0 && $dy === 0.0) return hypot($x - $x1, $y - $y1);
        $t = max(0, min(1, (($x - $x1) * $dx + ($y - $y1) * $dy) / ($dx * $dx + $dy * $dy)));
        return hypot($x - ($x1 + $t * $dx), $y - ($y1 + $t * $dy));
    }
}
