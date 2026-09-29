import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeToken } from "./tokenNormalizer";

const segment = (value, name) => {
  const normalized = Number(value);
  if (!Number.isInteger(normalized) || normalized < 1) {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(normalized));
};

const normalizeLink = (source = {}) => ({
  id: Number(source.id),
  campaignId: Number(source.campaignId),
  sourceSceneId: Number(source.sourceSceneId),
  sourceTokenId: Number(source.sourceTokenId),
  targetSceneId: Number(source.targetSceneId),
  targetTokenId: Number(source.targetTokenId),
  enabled: source.enabled === true,
  divergedFields: Array.isArray(source.divergedFields)
    ? source.divergedFields.map(String)
    : [],
  lastSyncedAt: source.lastSyncedAt || null,
  createdBy: Number(source.createdBy) || null,
});

const normalizeResult = (payload = {}) => ({
  ...payload,
  links: Array.isArray(payload.links)
    ? payload.links.map(normalizeLink)
    : undefined,
  link: payload.link ? normalizeLink(payload.link) : undefined,
  synchronizedTokens: (payload.synchronizedTokens || []).map((entry) => ({
    ...entry,
    token: normalizeToken(entry.token || entry),
  })),
});

export const createTokenSyncApiClient = (client = jsonApiClient) => {
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/token-sync`;
  const link = (campaignId, linkId) =>
    `${base(campaignId)}/links/${segment(linkId, "link_id")}`;

  return {
    async list(campaignId) {
      const payload = await client.request(base(campaignId));
      return {
        scenes: Array.isArray(payload?.scenes) ? payload.scenes : [],
        tokens: Array.isArray(payload?.tokens) ? payload.tokens : [],
        links: (payload?.links || []).map(normalizeLink),
        syncFields: Array.isArray(payload?.syncFields)
          ? payload.syncFields.map(String)
          : [],
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
    preview(campaignId, sourceTokenId, targetTokenIds) {
      return client.request(`${base(campaignId)}/preview`, {
        method: "POST",
        body: { sourceTokenId, targetTokenIds },
      });
    },
    async transfer(campaignId, request) {
      return normalizeResult(
        await client.request(`${base(campaignId)}/transfer`, {
          method: "POST",
          body: request,
        }),
      );
    },
    async createLinks(campaignId, request) {
      return normalizeResult(
        await client.request(`${base(campaignId)}/links`, {
          method: "POST",
          body: request,
        }),
      );
    },
    async updateLink(campaignId, linkId, enabled) {
      return normalizeResult(
        await client.request(link(campaignId, linkId), {
          method: "PATCH",
          body: { enabled: enabled === true },
        }),
      );
    },
    async applyLink(campaignId, linkId) {
      return normalizeResult(
        await client.request(`${link(campaignId, linkId)}/apply`, {
          method: "POST",
        }),
      );
    },
    async deleteLink(campaignId, linkId) {
      return client.request(link(campaignId, linkId), { method: "DELETE" });
    },
  };
};

export const tokenSyncApiClient = createTokenSyncApiClient();
