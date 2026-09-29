<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Admin\AdminException;
use App\Services\Admin\AdminMediaLibraryService;
use App\Services\Auth\AuthContextService;
use App\Services\Media\MediaException;
use CodeIgniter\HTTP\ResponseInterface;

final class AdminMediaController extends BaseController
{
    private $authContext;
    private $library;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->library = new AdminMediaLibraryService();
    }

    public function index(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->list($this->auth(), (array) $this->request->getGet()));
    }

    public function audioLibraries(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->audioLibraries($this->auth(), (array) $this->request->getGet()));
    }

    public function show($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->detail($this->auth(), $this->id($id)));
    }

    public function update($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->update($this->auth(), $this->id($id), $this->payload()));
    }

    public function delete($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->delete($this->auth(), $this->id($id)));
    }

    public function bulk(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->bulk($this->auth(), $this->payload()));
    }

    public function upload(): ResponseInterface
    {
        return $this->execute(function (): array {
            $file = $this->request->getFile('file');
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                throw new AdminException('validation_failed', 'Choose a valid file.', 422, ['file' => 'invalid']);
            }
            $input = $this->multipartPayload();
            $input['filename'] = $file->getClientName();
            $input['mimeType'] = $file->getMimeType();
            return $this->library->upload($this->auth(), $file->getTempName(), $input);
        }, 201);
    }

    public function registerExternal(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->registerExternal($this->auth(), $this->payload()), 201);
    }

    public function publishPersonalAudioTrack($trackId = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->publishPersonalAudioTrack(
            $this->auth(),
            $this->id($trackId),
            $this->payload()
        ));
    }

    public function initiateReplacement($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->initiateReplacement($this->auth(), $this->id($id), $this->payload()), 201);
    }

    public function completeReplacement($id = null, $stagingId = null): ResponseInterface
    {
        return $this->execute(function () use ($id, $stagingId): array {
            $payload = $this->payload();
            return $this->library->completeReplacement(
                $this->auth(), $this->id($id), $this->id($stagingId),
                (array) ($payload['uploadResult'] ?? $payload)
            );
        });
    }

    public function retryPurge($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->retryPurge($this->auth(), $this->id($id)));
    }

    public function collections(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->collections($this->auth()));
    }

    public function createCollection(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->createCollection($this->auth(), $this->payload()), 201);
    }

    public function updateCollection($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->updateCollection($this->auth(), $this->id($id), $this->payload()));
    }

    public function deleteCollection($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->deleteCollection($this->auth(), $this->id($id)));
    }

    public function addCollectionAssets($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->changeCollectionAssets($this->auth(), $this->id($id), $this->payload(), true));
    }

    public function removeCollectionAssets($id = null): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->changeCollectionAssets($this->auth(), $this->id($id), $this->payload(), false));
    }

    public function createCharacterSet(): ResponseInterface
    {
        return $this->execute(fn (): array => $this->library->createCharacterSet($this->auth(), $this->payload()), 201);
    }

    private function execute(callable $operation, int $status = 200): ResponseInterface
    {
        try {
            return $this->response->setStatusCode($status)->setJSON($operation());
        } catch (AdminException $exception) {
            return $this->error($exception->status(), $exception->errorCode(), $exception->getMessage(), $exception->details());
        } catch (MediaException $exception) {
            return $this->error($exception->status(), $exception->errorCode(), $exception->getMessage(), $exception->errors());
        } catch (\Throwable $exception) {
            log_message('error', 'Admin media API failure: {message}', ['message' => $exception->getMessage()]);
            return $this->error(500, 'media_operation_failed', 'Media operation failed.');
        }
    }

    private function error(int $status, string $code, string $message, array $errors = []): ResponseInterface
    {
        $body = ['code' => $code, 'message' => $message];
        if ($errors) $body['errors'] = $errors;
        return $this->response->setStatusCode($status)->setJSON($body);
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function payload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new AdminException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) throw new AdminException('invalid_json', 'Request body must be a JSON object.', 400);
        return $payload;
    }

    private function multipartPayload(): array
    {
        $payload = (array) $this->request->getPost();
        foreach (['tags', 'customMetadata'] as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field])) continue;
            $decoded = json_decode($payload[$field], true);
            if (!is_array($decoded)) throw new AdminException('validation_failed', $field . ' must contain valid JSON.', 422);
            $payload[$field] = $decoded;
        }
        return $payload;
    }

    private function id($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new AdminException('media_not_found', 'Media resource was not found.', 404);
        return (int) $id;
    }
}
