import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeRegion, regionWritePayload } from "./regionNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createRegionApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/regions`;
  const item = (campaignId, sceneId, regionId) =>
    `${base(campaignId, sceneId)}/${segment(regionId, "region_id")}`;
  return {
    async list(campaignId, sceneId, options = {}) {
      const payload = await client.request(base(campaignId, sceneId), options);
      return {
        items: (payload?.items || []).map(normalizeRegion),
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
    async create(campaignId, sceneId, draft) {
      const payload = await client.request(base(campaignId, sceneId), {
        method: "POST",
        body: regionWritePayload(draft),
      });
      return normalizeRegion(payload.region);
    },
    async update(campaignId, sceneId, regionId, changes) {
      const payload = await client.request(
        item(campaignId, sceneId, regionId),
        {
          method: "PATCH",
          body: regionWritePayload(changes, true),
        },
      );
      return normalizeRegion(payload.region);
    },
    remove(campaignId, sceneId, regionId, revision) {
      return client.request(item(campaignId, sceneId, regionId), {
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

export const regionApiClient = createRegionApiClient();
