<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\HTTP\ResponseInterface;

final class MediaController extends BaseController
{
    private $authContext;
    private $media;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->media = new MediaService();
    }

    /** POST /api/media/uploads */
    public function createUpload(): ResponseInterface
    {
        return $this->execute(function (): array {
            return $this->media->initiateUpload($this->auth(), $this->payload());
        }, 201);
    }

    /** POST /api/media/uploads/{id}/complete */
    public function completeUpload($id = null): ResponseInterface
    {
        return $this->execute(function () use ($id): array {
            $payload = $this->payload();
            $result = $payload['uploadResult'] ?? $payload['upload_result'] ?? $payload;
            return ['asset' => $this->media->completeUpload($this->auth(), $this->id($id), (array) $result)];
        });
    }

    /** GET /api/media/{id}?variant=thumbnail */
    public function show($id = null): ResponseInterface
    {
        return $this->execute(function () use ($id): array {
            $variant = trim((string) $this->request->getGet('variant'));
            return ['asset' => $this->media->get($this->auth(), $this->id($id), $variant !== '' ? $variant : null)];
        });
    }

    private function execute(callable $operation, int $successStatus = 200): ResponseInterface
    {
        try {
            return $this->response->setStatusCode($successStatus)->setJSON($operation());
        } catch (MediaException $exception) {
            return $this->response->setStatusCode($exception->status())->setJSON([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'errors' => (object) $exception->errors(),
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Media API failure: {message}', ['message' => $exception->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'code' => 'media_operation_failed',
                'message' => 'Media operation failed.',
            ]);
        }
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function payload(): array
    {
        $payload = $this->request->getJSON(true);
        return is_array($payload) ? $payload : [];
    }

    private function id($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new MediaException('validation_failed', 'Media asset ID is invalid.', 422);
        }
        return (int) $id;
    }
}
