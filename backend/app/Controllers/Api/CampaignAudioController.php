<?php

namespace App\Controllers\Api;

use App\Services\Audio\AudioLibraryService;
use App\Services\Audio\JukeboxPlanningService;
use App\Services\Audio\JukeboxStateService;
use App\Services\Campaign\CampaignException;

class CampaignAudioController extends CampaignApiController
{
    private $audio;
    private $jukebox;
    private $planning;

    public function __construct()
    {
        parent::__construct();
        $this->audio = new AudioLibraryService();
        $this->jukebox = new JukeboxStateService();
        $this->planning = new JukeboxPlanningService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $auth = $this->auth();
            $id = $this->campaignId($campaignId);
            return array_merge(
                $this->audio->list($auth, $id),
                $this->planning->snapshot($auth, $id)
            );
        });
    }

    public function createPlaylist($campaignId = null)
    {
        return $this->execute(fn (): array => $this->planning->createPlaylist(
            $this->auth(), $this->campaignId($campaignId), $this->jsonPayload()
        ), 201);
    }

    public function renamePlaylist($campaignId = null, $playlistId = null)
    {
        return $this->execute(fn (): array => $this->planning->renamePlaylist(
            $this->auth(), $this->campaignId($campaignId),
            $this->positiveId($playlistId, 'playlist_not_found'), $this->jsonPayload()
        ));
    }

    public function deletePlaylist($campaignId = null, $playlistId = null)
    {
        return $this->execute(fn (): array => $this->planning->deletePlaylist(
            $this->auth(), $this->campaignId($campaignId),
            $this->positiveId($playlistId, 'playlist_not_found')
        ));
    }

    public function addPlaylistItem($campaignId = null, $playlistId = null)
    {
        return $this->execute(fn (): array => $this->planning->addPlaylistItem(
            $this->auth(), $this->campaignId($campaignId),
            $this->positiveId($playlistId, 'playlist_not_found'), $this->jsonPayload()
        ), 201);
    }

    public function movePlaylistItem($campaignId = null, $playlistId = null, $itemId = null)
    {
        return $this->execute(fn (): array => $this->planning->movePlaylistItem(
            $this->auth(), $this->campaignId($campaignId),
            $this->positiveId($playlistId, 'playlist_not_found'),
            $this->positiveId($itemId, 'playlist_item_not_found'), $this->jsonPayload()
        ));
    }

    public function removePlaylistItem($campaignId = null, $playlistId = null, $itemId = null)
    {
        return $this->execute(fn (): array => $this->planning->removePlaylistItem(
            $this->auth(), $this->campaignId($campaignId),
            $this->positiveId($playlistId, 'playlist_not_found'),
            $this->positiveId($itemId, 'playlist_item_not_found')
        ));
    }

    public function addQueueTrack($campaignId = null, $channelId = null)
    {
        return $this->execute(fn (): array => $this->planning->addQueueTrack(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId, $this->jsonPayload()
        ), 201);
    }

    public function addQueuePlaylist($campaignId = null, $channelId = null, $playlistId = null)
    {
        return $this->execute(fn (): array => $this->planning->addQueuePlaylist(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId,
            $this->positiveId($playlistId, 'playlist_not_found')
        ), 201);
    }

    public function startPlaylist($campaignId = null, $channelId = null, $playlistId = null)
    {
        return $this->execute(fn (): array => $this->planning->startPlaylist(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId,
            $this->positiveId($playlistId, 'playlist_not_found')
        ));
    }

    public function moveQueueItem($campaignId = null, $channelId = null, $itemId = null)
    {
        return $this->execute(fn (): array => $this->planning->moveQueueItem(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId,
            $this->positiveId($itemId, 'queue_item_not_found'), $this->jsonPayload()
        ));
    }

    public function removeQueueItem($campaignId = null, $channelId = null, $itemId = null)
    {
        return $this->execute(fn (): array => $this->planning->removeQueueItem(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId,
            $this->positiveId($itemId, 'queue_item_not_found')
        ));
    }

    public function clearQueue($campaignId = null, $channelId = null)
    {
        return $this->execute(fn (): array => $this->planning->clearQueue(
            $this->auth(), $this->campaignId($campaignId), (string) $channelId
        ));
    }

    public function upload($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->audio->upload(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->request->getFile('file'),
                $this->request->getPost()
            );
        }, 201);
    }

    public function external($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->audio->addExternal(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->jsonPayload()
            );
        }, 201);
    }

    public function attach($campaignId = null, $trackId = null)
    {
        return $this->execute(function () use ($campaignId, $trackId): array {
            return $this->audio->attachTrack(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->positiveId($trackId, 'audio_track_not_found')
            );
        });
    }

    public function remove($campaignId = null, $trackId = null)
    {
        return $this->execute(function () use ($campaignId, $trackId): array {
            return $this->audio->remove(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->positiveId($trackId, 'audio_track_not_found')
            );
        });
    }

    public function deletePersonal($campaignId = null, $trackId = null)
    {
        return $this->execute(function () use ($campaignId, $trackId): array {
            return $this->audio->deletePersonalTrack(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->positiveId($trackId, 'audio_track_not_found')
            );
        });
    }

    public function updatePersonal($campaignId = null, $trackId = null)
    {
        return $this->execute(function () use ($campaignId, $trackId): array {
            return $this->audio->updatePersonalTrack(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->positiveId($trackId, 'audio_track_not_found'),
                $this->jsonPayload()
            );
        });
    }

    public function jukeboxState($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->jukebox->state($this->auth(), $this->campaignId($campaignId));
        });
    }

    public function file($campaignId = null, $trackId = null)
    {
        try {
            $result = $this->audio->assetForPlayback(
                $this->auth(),
                $this->campaignId($campaignId),
                $this->positiveId($trackId, 'audio_track_not_found')
            );
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            return $this->rangeResponse($result['track'], $result['path']);
        } catch (CampaignException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }

    private function rangeResponse(array $track, string $path)
    {
        $size = (int) filesize($path);
        $start = 0;
        $end = max(0, $size - 1);
        $status = 200;
        $range = trim((string) $this->request->getHeaderLine('Range'));
        if ($range !== '') {
            if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches)) {
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', 'bytes */' . $size);
            }
            if ($matches[1] === '' && $matches[2] !== '') {
                $suffix = min($size, (int) $matches[2]);
                $start = max(0, $size - $suffix);
            } else {
                $start = (int) ($matches[1] === '' ? 0 : $matches[1]);
                $end = (int) ($matches[2] === '' ? $end : $matches[2]);
            }
            if ($start < 0 || $start >= $size || $end < $start) {
                return $this->response->setStatusCode(416)
                    ->setHeader('Content-Range', 'bytes */' . $size);
            }
            $end = min($end, $size - 1);
            $status = 206;
        }
        $length = $end - $start + 1;
        $handle = fopen($path, 'rb');
        if (!$handle || fseek($handle, $start) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new CampaignException('audio_storage_failed', 'Audio file could not be read.', 500);
        }
        $body = (string) fread($handle, $length);
        fclose($handle);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($track['original_name'] ?? 'audio'));
        $response = $this->response->setStatusCode($status)
            ->setHeader('Content-Type', (string) ($track['mime_type'] ?? 'application/octet-stream'))
            ->setHeader('Accept-Ranges', 'bytes')
            ->setHeader('Content-Length', (string) strlen($body))
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($body);
        if ($status === 206) {
            $response->setHeader('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
        }
        return $response;
    }

    private function campaignId($value): int
    {
        return $this->positiveId($value, 'campaign_not_found');
    }
}
