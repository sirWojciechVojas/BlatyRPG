import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeWall, wallWritePayload } from "./wallNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createWallApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/walls`;
  const item = (campaignId, sceneId, wallId) =>
    `${base(campaignId, sceneId)}/${segment(wallId, "wall_id")}`;

  return {
    async list(campaignId, sceneId, options = {}) {
      const payload = await client.request(base(campaignId, sceneId), options);
      return {
        items: (payload?.items || []).map(normalizeWall),
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
    async create(campaignId, sceneId, draft) {
      const payload = await client.request(base(campaignId, sceneId), {
        method: "POST",
        body: wallWritePayload(draft),
      });
      return normalizeWall(payload.wall);
    },
    async update(campaignId, sceneId, wallId, changes) {
      const payload = await client.request(item(campaignId, sceneId, wallId), {
        method: "PATCH",
        body: wallWritePayload(changes, true),
      });
      return normalizeWall(payload.wall);
    },
    async remove(campaignId, sceneId, wallId, revision) {
      return client.request(item(campaignId, sceneId, wallId), {
        method: "DELETE",
        body: { revision: numberRevision(revision) },
      });
    },
  };
};

const numberRevision = (value) => {
  const revision = Number(value);
  return Number.isFinite(revision) ? revision : 0;
};

export const wallApiClient = createWallApiClient();
