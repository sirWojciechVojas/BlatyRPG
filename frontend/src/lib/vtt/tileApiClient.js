import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeTile, tileWritePayload } from "./tileNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createTileApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/tiles`;
  const item = (campaignId, sceneId, tileId) =>
    `${base(campaignId, sceneId)}/${segment(tileId, "tile_id")}`;
  return {
    async list(campaignId, sceneId, options = {}) {
      const payload = await client.request(base(campaignId, sceneId), options);
      return {
        items: (payload?.items || []).map(normalizeTile),
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
    async create(campaignId, sceneId, draft) {
      const payload = await client.request(base(campaignId, sceneId), {
        method: "POST",
        body: tileWritePayload(draft),
      });
      return normalizeTile(payload.tile);
    },
    async update(campaignId, sceneId, tileId, changes) {
      const payload = await client.request(item(campaignId, sceneId, tileId), {
        method: "PATCH",
        body: tileWritePayload(changes, true),
      });
      return normalizeTile(payload.tile);
    },
    async remove(campaignId, sceneId, tileId, revision) {
      return client.request(item(campaignId, sceneId, tileId), {
        method: "DELETE",
        body: { revision: Number(revision) },
      });
    },
  };
};

export const tileApiClient = createTileApiClient();
