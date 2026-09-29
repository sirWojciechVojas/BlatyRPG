import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { lightWritePayload, normalizeLight } from "./lightNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createLightApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/lights`;
  const item = (campaignId, sceneId, lightId) =>
    `${base(campaignId, sceneId)}/${segment(lightId, "light_id")}`;
  return {
    async list(campaignId, sceneId, options = {}) {
      const payload = await client.request(base(campaignId, sceneId), options);
      return {
        items: (payload?.items || []).map(normalizeLight),
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
    async create(campaignId, sceneId, draft) {
      const payload = await client.request(base(campaignId, sceneId), {
        method: "POST",
        body: lightWritePayload(draft),
      });
      return normalizeLight(payload.light);
    },
    async update(campaignId, sceneId, lightId, changes) {
      const payload = await client.request(item(campaignId, sceneId, lightId), {
        method: "PATCH",
        body: lightWritePayload(changes, true),
      });
      return normalizeLight(payload.light);
    },
    async remove(campaignId, sceneId, lightId, revision) {
      return client.request(item(campaignId, sceneId, lightId), {
        method: "DELETE",
        body: { revision: Number(revision) },
      });
    },
  };
};

export const lightApiClient = createLightApiClient();
