import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeToken, tokenWritePayload } from "./tokenNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createTokenApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/tokens`;
  const item = (campaignId, sceneId, tokenId) =>
    `${base(campaignId, sceneId)}/${segment(tokenId, "token_id")}`;

  return {
    async list(campaignId, sceneId, options = {}) {
      const payload = await client.request(base(campaignId, sceneId), options);
      return {
        items: (payload?.items || []).map(normalizeToken),
        capabilities: payload?.capabilities || { canCreate: false },
      };
    },
    async create(campaignId, sceneId, draft) {
      const payload = await client.request(base(campaignId, sceneId), {
        method: "POST",
        body: tokenWritePayload(draft),
      });
      return normalizeToken(payload.token);
    },
    async update(campaignId, sceneId, tokenId, changes) {
      const payload = await client.request(item(campaignId, sceneId, tokenId), {
        method: "PATCH",
        body: tokenWritePayload(changes, true),
      });
      return normalizeToken(payload.token);
    },
    async remove(campaignId, sceneId, tokenId, revision) {
      return client.request(item(campaignId, sceneId, tokenId), {
        method: "DELETE",
        body: { revision: Number(revision) },
      });
    },
  };
};

export const tokenApiClient = createTokenApiClient();
