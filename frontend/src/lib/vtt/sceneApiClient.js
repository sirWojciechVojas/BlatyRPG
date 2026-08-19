import { jsonApiClient } from "@/lib/api/jsonApiClient";
import {
  normalizeCapabilities,
  normalizeScene,
  normalizeSceneCollection,
  toSceneWritePayload,
} from "./sceneNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

const normalizeSceneResponse = (payload = {}) => ({
  scene: normalizeScene(payload.scene),
  capabilities: normalizeCapabilities(payload.capabilities),
});

export const createSceneApiClient = (client = jsonApiClient) => {
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes`;
  const item = (campaignId, sceneId) =>
    `${base(campaignId)}/${segment(sceneId, "scene_id")}`;

  return {
    async list(campaignId, options = {}) {
      const payload = await client.request(base(campaignId), options);
      return normalizeSceneCollection(payload);
    },
    async get(campaignId, sceneId, options = {}) {
      const payload = await client.request(item(campaignId, sceneId), options);
      return normalizeSceneResponse(payload);
    },
    async create(campaignId, draft) {
      const payload = await client.request(base(campaignId), {
        method: "POST",
        body: toSceneWritePayload(draft),
      });
      return normalizeSceneResponse(payload);
    },
    async update(campaignId, sceneId, changes) {
      const payload = await client.request(item(campaignId, sceneId), {
        method: "PATCH",
        body: toSceneWritePayload(changes, true),
      });
      return normalizeSceneResponse(payload);
    },
    async remove(campaignId, sceneId, revision) {
      const payload = await client.request(item(campaignId, sceneId), {
        method: "DELETE",
        body: { revision: Number(revision) },
      });
      return {
        activeSceneId:
          payload?.activeSceneId ?? payload?.active_scene_id ?? null,
      };
    },
    async activate(campaignId, sceneId, revision) {
      const payload = await client.request(
        `${item(campaignId, sceneId)}/activate`,
        { method: "POST", body: { revision: Number(revision) } },
      );
      return {
        ...normalizeSceneResponse(payload),
        activeSceneId:
          payload.activeSceneId ?? payload.active_scene_id ?? sceneId,
      };
    },
  };
};

export const sceneApiClient = createSceneApiClient();
