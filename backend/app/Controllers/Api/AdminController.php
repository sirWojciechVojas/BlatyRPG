<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Admin\AdminException;
use App\Services\Admin\AdminAudioLibraryService;
use App\Services\Admin\AdminCompendiumService;
use App\Services\Admin\AdminProfessionService;
use App\Services\Admin\AdminService;
use App\Services\Admin\AdminTokenTemplateService;
use App\Services\Auth\AuthContextService;
use CodeIgniter\API\ResponseTrait;

class AdminController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $admin;
    private $compendium;
    private $professions;
    private $audio;
    private $tokenTemplates;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->admin = new AdminService();
        $this->compendium = new AdminCompendiumService();
        $this->professions = new AdminProfessionService();
        $this->audio = new AdminAudioLibraryService();
        $this->tokenTemplates = new AdminTokenTemplateService();
    }

    public function overview()
    {
        return $this->execute(function (): array {
            return $this->admin->overview($this->auth());
        });
    }

    public function createUser()
    {
        return $this->execute(function (): array {
            return $this->admin->createUser($this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function audioOverview()
    {
        return $this->execute(function (): array {
            return $this->audio->overview($this->auth());
        });
    }

    public function createAudioLibrary()
    {
        return $this->execute(function (): array {
            return $this->audio->createLibrary($this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function updateAudioLibrary($libraryId = null)
    {
        return $this->execute(function () use ($libraryId): array {
            return $this->audio->updateLibrary(
                $this->auth(), $this->positiveId($libraryId), $this->jsonPayload()
            );
        });
    }

    public function deleteAudioLibrary($libraryId = null)
    {
        return $this->execute(function () use ($libraryId): array {
            return $this->audio->deleteLibrary($this->auth(), $this->positiveId($libraryId));
        });
    }

    public function uploadAudioTrack($libraryId = null)
    {
        return $this->execute(function () use ($libraryId): array {
            return $this->audio->upload(
                $this->auth(), $this->positiveId($libraryId),
                $this->request->getFile('file'), $this->request->getPost()
            );
        }, 201);
    }

    public function createExternalAudioTrack($libraryId = null)
    {
        return $this->execute(function () use ($libraryId): array {
            return $this->audio->addExternal(
                $this->auth(), $this->positiveId($libraryId), $this->jsonPayload()
            );
        }, 201);
    }

    public function updateAudioTrack($trackId = null)
    {
        return $this->execute(function () use ($trackId): array {
            return $this->audio->updateTrack(
                $this->auth(), $this->positiveId($trackId), $this->jsonPayload()
            );
        });
    }

    public function deleteAudioTrack($trackId = null)
    {
        return $this->execute(function () use ($trackId): array {
            return $this->audio->deleteTrack($this->auth(), $this->positiveId($trackId));
        });
    }

    public function tokenTemplates()
    {
        return $this->execute(fn (): array => $this->tokenTemplates->list($this->auth()));
    }

    public function createTokenTemplate()
    {
        return $this->execute(fn (): array => $this->tokenTemplates->create(
            $this->auth(),
            $this->tokenTemplatePayload(),
            $this->request->getFile('file')
        ), 201);
    }

    public function updateTokenTemplate($templateId = null)
    {
        return $this->execute(fn (): array => $this->tokenTemplates->update(
            $this->auth(),
            $this->positiveId($templateId),
            $this->tokenTemplatePayload(),
            $this->request->getFile('file')
        ));
    }

    public function deleteTokenTemplate($templateId = null)
    {
        return $this->execute(fn (): array => $this->tokenTemplates->delete(
            $this->auth(),
            $this->positiveId($templateId),
            $this->jsonPayload()
        ));
    }

    public function tokenTemplateAssetFile($assetId = null)
    {
        try {
            $result = $this->tokenTemplates->asset(
                $this->auth(), $this->positiveId($assetId)
            );
            $asset = $result['asset'];
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            return $this->response
                ->setHeader('Content-Type', (string) $asset['mime_type'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Cache-Control', 'private, max-age=3600')
                ->setHeader('Content-Disposition', 'inline; filename="' . addcslashes((string) $asset['original_name'], "\\\"") . '"')
                ->setBody((string) file_get_contents($result['path']));
        } catch (AdminException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) $payload['errors'] = $exception->details();
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }

    public function compendiumOverview()
    {
        return $this->execute(function (): array {
            return $this->compendium->overview($this->auth());
        });
    }

    public function professions()
    {
        return $this->execute(fn (): array => $this->professions->overview(
            $this->auth()
        ));
    }

    public function createProfession()
    {
        return $this->execute(fn (): array => $this->professions->create(
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function decodeProfessionRequirements()
    {
        return $this->execute(fn (): array => $this->professions
            ->decodeRequirements($this->auth(), $this->jsonPayload()));
    }

    public function updateProfession($professionId = null)
    {
        return $this->execute(fn (): array => $this->professions->update(
            $this->auth(),
            $this->positiveId($professionId),
            $this->jsonPayload()
        ));
    }

    public function deleteProfession($professionId = null)
    {
        return $this->execute(fn (): array => $this->professions->delete(
            $this->auth(),
            $this->positiveId($professionId),
            $this->jsonPayload()
        ));
    }

    public function uploadProfessionImage($professionId = null, $slot = null)
    {
        return $this->execute(fn (): array => $this->professions->uploadImage(
            $this->auth(),
            $this->positiveId($professionId),
            (string) $slot,
            $this->request->getFile('file')
        ));
    }

    public function deleteProfessionImage($professionId = null, $slot = null)
    {
        return $this->execute(fn (): array => $this->professions->deleteImage(
            $this->auth(),
            $this->positiveId($professionId),
            (string) $slot
        ));
    }

    public function createCompendiumWorld()
    {
        return $this->execute(function (): array {
            return $this->compendium->createWorld($this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function updateCompendiumWorld($universeId = null)
    {
        return $this->execute(function () use ($universeId): array {
            return $this->compendium->updateWorld(
                $this->auth(), $this->positiveId($universeId), $this->jsonPayload()
            );
        });
    }

    public function updateCompendiumEntryPolicy($universeId = null, $entryId = null)
    {
        return $this->execute(function () use ($universeId, $entryId): array {
            return $this->compendium->updateEntryPolicy(
                $this->auth(), $this->positiveId($universeId),
                $this->positiveId($entryId), $this->jsonPayload()
            );
        });
    }

    public function updateCompendiumProfile($universeId = null, $profileId = null)
    {
        return $this->execute(function () use ($universeId, $profileId): array {
            return $this->compendium->updateMechanicalProfile(
                $this->auth(), $this->positiveId($universeId),
                $this->positiveId($profileId), $this->jsonPayload()
            );
        });
    }

    public function updateCompendiumSource($sourceId = null)
    {
        return $this->execute(function () use ($sourceId): array {
            return $this->compendium->updateSource(
                $this->auth(), $this->positiveId($sourceId), $this->jsonPayload()
            );
        });
    }

    public function rollbackCompendiumImport($universeId = null, $runId = null)
    {
        return $this->execute(function () use ($universeId, $runId): array {
            return $this->compendium->rollbackImport(
                $this->auth(), $this->positiveId($universeId), $this->positiveId($runId)
            );
        });
    }

    public function syncCompendiumWfrp2($universeId = null)
    {
        return $this->execute(function () use ($universeId): array {
            return $this->compendium->syncWfrp2Catalog(
                $this->auth(), $this->positiveId($universeId)
            );
        });
    }

    public function changeUserRole($userId = null)
    {
        return $this->execute(function () use ($userId): array {
            return $this->admin->changeUserRole(
                $this->auth(),
                (int) $userId,
                $this->jsonPayload()
            );
        });
    }

    public function attachCharacterCampaign($characterId = null, $campaignId = null)
    {
        return $this->execute(function () use ($characterId, $campaignId): array {
            return $this->admin->attachCharacterCampaign(
                $this->auth(), (int) $characterId, (int) $campaignId
            );
        });
    }

    public function detachCharacterCampaign($characterId = null, $campaignId = null)
    {
        return $this->execute(function () use ($characterId, $campaignId): array {
            return $this->admin->detachCharacterCampaign(
                $this->auth(), (int) $characterId, (int) $campaignId
            );
        });
    }

    public function attachCharacterOwner(
        $characterId = null,
        $campaignId = null,
        $userId = null
    ) {
        return $this->execute(function () use ($characterId, $campaignId, $userId): array {
            return $this->admin->attachCharacterOwner(
                $this->auth(), (int) $characterId, (int) $campaignId, (int) $userId
            );
        });
    }

    public function detachCharacterOwner(
        $characterId = null,
        $campaignId = null,
        $userId = null
    ) {
        return $this->execute(function () use ($characterId, $campaignId, $userId): array {
            return $this->admin->detachCharacterOwner(
                $this->auth(), (int) $characterId, (int) $campaignId, (int) $userId
            );
        });
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new AdminException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new AdminException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function tokenTemplatePayload(): array
    {
        if (stripos($this->request->getHeaderLine('Content-Type'), 'multipart/form-data') === false) {
            return $this->jsonPayload();
        }
        $raw = $this->request->getPost('payload');
        if (!is_string($raw) || trim($raw) === '') return [];
        $payload = json_decode($raw, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new AdminException('invalid_json', 'The payload field must contain a JSON object.', 400);
        }
        return $payload;
    }

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new AdminException('not_found', 'Resource was not found.', 404);
        return (int) $id;
    }

    private function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (AdminException $exception) {
            $payload = [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
