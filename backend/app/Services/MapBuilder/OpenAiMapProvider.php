<?php

namespace App\Services\MapBuilder;

/** OpenAI Responses + Images adapter. API keys never cross the server boundary. */
final class OpenAiMapProvider implements MapAiProviderInterface
{
    private $apiKey;
    private $baseUrl;
    private $textModel;
    private $imageModel;
    private $timeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $textModel = null,
        ?string $imageModel = null,
        ?int $timeout = null
    ) {
        $this->apiKey = trim((string) ($apiKey ?? getenv('OPENAI_API_KEY')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? getenv('OPENAI_API_BASE') ?: 'https://api.openai.com/v1'), '/');
        $this->textModel = trim((string) ($textModel ?? getenv('MAP_AI_TEXT_MODEL') ?: 'gpt-5.4-mini'));
        $this->imageModel = trim((string) ($imageModel ?? getenv('MAP_AI_IMAGE_MODEL') ?: 'gpt-image-2'));
        $this->timeout = max(10, min(300, (int) ($timeout ?? getenv('MAP_AI_TIMEOUT_SECONDS') ?: 120)));
    }

    public function name(): string
    {
        return 'openai';
    }

    public function configured(): bool
    {
        return $this->apiKey !== '' && $this->textModel !== '' && $this->imageModel !== '';
    }

    public function textModel(): string
    {
        return $this->textModel;
    }

    public function imageModel(): string
    {
        return $this->imageModel;
    }

    public function propose(array $context): array
    {
        $this->assertConfigured();
        $document = $context['document'] ?? [];
        $bounds = [
            'width' => (float) ($document['width'] ?? 4000),
            'height' => (float) ($document['height'] ?? 3000),
            'pixelsPerMeter' => (float) ($document['pixelsPerMeter'] ?? 100),
        ];
        $input = [
            'task' => (string) ($context['kind'] ?? 'layout'),
            'request' => (string) ($context['prompt'] ?? ''),
            'bounds' => $bounds,
            'availableAssetIds' => array_values($context['assetIds'] ?? []),
            'selection' => $context['selection'] ?? null,
            'existingObjects' => array_slice($document['objects'] ?? [], 0, 4000),
            'rules' => [
                'strictOrthographicTopDown' => true,
                'editableObjectsOnly' => true,
                'preserveLockedObjects' => true,
                'avoidBlockedDoorways' => true,
                'doorsAndWindowsMustTouchWalls' => true,
                'coordinatesMustStayInsideBounds' => true,
                'consistentPhysicalScale' => true,
            ],
        ];
        $payload = [
            'model' => $this->textModel,
            'store' => false,
            'max_output_tokens' => 12000,
            'instructions' => 'You are a tactical-map layout planner. Return only the schema. '
                . 'Use existing asset IDs. Design traversable rooms and keep passage clearance. '
                . 'All geometry is in top-left-origin pixels with the Y axis pointing down.',
            'input' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'map_builder_proposal',
                    'strict' => true,
                    'schema' => $this->proposalSchema(),
                ],
            ],
        ];
        $response = $this->request('/responses', $payload);
        $text = $this->responseText($response);
        $proposal = json_decode($text, true);
        if (!is_array($proposal)) {
            throw new MapBuilderException('ai_response_invalid', 'AI returned invalid structured output.', 502);
        }
        $proposal['objects'] = array_map([$this, 'normalizeObject'], $proposal['objects'] ?? []);
        $proposal['removeObjectIds'] = array_values(array_map('strval', $proposal['removeObjectIds'] ?? []));
        return [
            'proposal' => $proposal,
            'providerResponseId' => (string) ($response['id'] ?? ''),
            'usage' => $response['usage'] ?? null,
        ];
    }

    public function generateAsset(array $context): array
    {
        $this->assertConfigured();
        $prompt = trim((string) ($context['prompt'] ?? ''));
        $payload = [
            'model' => $this->imageModel,
            'prompt' => "Asset type: tactical map prop. Primary request: {$prompt}. "
                . 'Style: realistic PBR 3D render, modern high-end game-engine materials, strict 90-degree '
                . 'orthographic top-down view, coherent neutral daylight and soft contained shadow. '
                . 'Background: genuinely transparent. Subject fully visible and centered. '
                . 'No text, border, floor plane, people, watermark, perspective or isometric camera.',
            'background' => 'transparent',
            'output_format' => 'png',
            'quality' => 'medium',
            'size' => '1024x1024',
        ];
        $response = $this->request('/images/generations', $payload);
        $encoded = $response['data'][0]['b64_json'] ?? null;
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (!is_string($bytes) || $bytes === '') {
            throw new MapBuilderException('ai_response_invalid', 'AI did not return generated image bytes.', 502);
        }
        return [
            'bytes' => $bytes,
            'mimeType' => 'image/png',
            'name' => mb_substr($prompt !== '' ? $prompt : 'Wygenerowany asset', 0, 120) . '.png',
            'revisedPrompt' => (string) ($response['data'][0]['revised_prompt'] ?? ''),
            'usage' => $response['usage'] ?? null,
        ];
    }

    private function request(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new MapBuilderException('ai_request_invalid', 'AI request could not be encoded.', 500);
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($this->baseUrl . $path, false, $context);
        $status = $this->responseStatus($http_response_header ?? []);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $providerCode = is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
            throw new MapBuilderException('ai_provider_error', 'AI provider request failed.', 502, [
                'providerStatus' => $status,
                'providerCode' => $providerCode,
            ]);
        }
        return $decoded;
    }

    private function responseText(array $response): string
    {
        if (is_string($response['output_text'] ?? null)) return $response['output_text'];
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }
        return '';
    }

    private function responseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string) $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        return $status ?? 0;
    }

    private function normalizeObject(array $object): array
    {
        $object['assetId'] = $object['assetId'] ?: null;
        $object['points'] = is_array($object['points'] ?? null) ? $object['points'] : [];
        $object['visible'] = $object['visible'] !== false;
        $object['locked'] = !empty($object['locked']);
        $object['levelId'] = 'ground';
        if ($object['assetId'] === null) unset($object['assetId']);
        return $object;
    }

    private function proposalSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'removeObjectIds', 'objects', 'warnings'],
            'properties' => [
                'summary' => ['type' => 'string', 'maxLength' => 500],
                'removeObjectIds' => [
                    'type' => 'array', 'maxItems' => 2000,
                    'items' => ['type' => 'string', 'maxLength' => 128],
                ],
                'warnings' => [
                    'type' => 'array', 'maxItems' => 50,
                    'items' => ['type' => 'string', 'maxLength' => 300],
                ],
                'objects' => [
                    'type' => 'array', 'maxItems' => 2000,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'id', 'type', 'layerId', 'x', 'y', 'width', 'height',
                            'rotation', 'opacity', 'visible', 'locked', 'assetId',
                            'points', 'color', 'shape', 'thickness',
                        ],
                        'properties' => [
                            'id' => ['type' => 'string', 'maxLength' => 128],
                            'type' => ['type' => 'string', 'enum' => [
                                'asset', 'terrain', 'brush', 'road', 'river', 'fence',
                                'room', 'wall', 'door', 'window', 'text', 'marker', 'light',
                            ]],
                            'layerId' => ['type' => 'string', 'enum' => [
                                'terrain', 'rooms', 'objects', 'walls', 'lights', 'annotations',
                            ]],
                            'x' => ['type' => 'number'],
                            'y' => ['type' => 'number'],
                            'width' => ['type' => 'number'],
                            'height' => ['type' => 'number'],
                            'rotation' => ['type' => 'number'],
                            'opacity' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                            'visible' => ['type' => 'boolean'],
                            'locked' => ['type' => 'boolean'],
                            'assetId' => ['type' => ['string', 'null']],
                            'points' => [
                                'type' => 'array', 'maxItems' => 2048,
                                'items' => [
                                    'type' => 'object', 'additionalProperties' => false,
                                    'required' => ['x', 'y'],
                                    'properties' => [
                                        'x' => ['type' => 'number'],
                                        'y' => ['type' => 'number'],
                                    ],
                                ],
                            ],
                            'color' => ['type' => 'string', 'maxLength' => 9],
                            'shape' => ['type' => 'string', 'enum' => ['', 'rect', 'circle', 'polygon']],
                            'thickness' => ['type' => 'number', 'minimum' => 1, 'maximum' => 2000],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function assertConfigured(): void
    {
        if (!$this->configured()) {
            throw new MapBuilderException('ai_not_configured', 'Map AI provider is not configured.', 503);
        }
    }
}

