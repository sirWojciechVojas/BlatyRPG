<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Calendar\CalendarException;
use App\Services\Calendar\CampaignCalendarService;
use CodeIgniter\API\ResponseTrait;

class CampaignCalendarController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $calendar;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->calendar = new CampaignCalendarService();
    }

    public function show($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->show((int) $campaignId, $this->auth());
        });
    }

    public function setState($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->setState((int) $campaignId, $this->auth(), $this->jsonPayload());
        });
    }

    public function advance($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->advance((int) $campaignId, $this->auth(), $this->jsonPayload());
        });
    }

    public function events($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->listEvents((int) $campaignId, $this->auth(), $this->request->getGet());
        });
    }

    public function createEvent($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->createEvent((int) $campaignId, $this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function updateEvent($campaignId = null, $eventId = null)
    {
        return $this->execute(function () use ($campaignId, $eventId): array {
            return $this->calendar->updateEvent(
                (int) $campaignId,
                (int) $eventId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function deleteEvent($campaignId = null, $eventId = null)
    {
        return $this->execute(function () use ($campaignId, $eventId): array {
            return $this->calendar->deleteEvent(
                (int) $campaignId,
                (int) $eventId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function setMorrslieb($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->calendar->setMorrslieb((int) $campaignId, $this->auth(), $this->jsonPayload());
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
            throw new CalendarException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new CalendarException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $status = 200)
    {
        try {
            return $this->respond($operation(), $status);
        } catch (CalendarException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
