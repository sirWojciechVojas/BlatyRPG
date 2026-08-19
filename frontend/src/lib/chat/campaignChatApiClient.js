import { jsonApiClient } from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

export const normalizeChatCapabilities = (value = {}) => ({
  canRead: value.canRead === true || value.can_read === true,
  canSend: value.canSend === true || value.can_send === true,
  canModerate: value.canModerate === true || value.can_moderate === true,
});

export const normalizeChatMessage = (value = {}) => {
  const author = value.author || {};
  return {
    id: positiveId(value.id),
    campaignId: positiveId(value.campaignId ?? value.campaign_id),
    type: String(value.type || value.message_type || "text"),
    body: String(value.body || ""),
    author: {
      id: positiveId(author.id ?? value.author_user_id),
      name: String(author.name || value.author_name || ""),
      isCurrentUser: Boolean(
        author.isCurrentUser ?? author.is_current_user ?? false,
      ),
    },
    clientNonce: value.clientNonce ?? value.client_nonce ?? null,
    metadata: value.metadata ?? value.metadata_json ?? null,
    createdAt: value.createdAt ?? value.created_at ?? null,
  };
};

const queryString = (page = {}) => {
  const params = new URLSearchParams();
  if (positiveId(page.beforeId)) params.set("beforeId", page.beforeId);
  if (positiveId(page.afterId)) params.set("afterId", page.afterId);
  if (positiveId(page.limit)) params.set("limit", page.limit);
  const value = params.toString();
  return value ? `?${value}` : "";
};

const normalizeList = (payload = {}) => ({
  items: (Array.isArray(payload.items) ? payload.items : [])
    .map(normalizeChatMessage)
    .filter((message) => message.id),
  pagination: {
    limit: Number(payload.pagination?.limit) || 50,
    beforeId: positiveId(
      payload.pagination?.beforeId ?? payload.pagination?.before_id,
    ),
    afterId: positiveId(
      payload.pagination?.afterId ?? payload.pagination?.after_id,
    ),
    hasMoreBefore: Boolean(
      payload.pagination?.hasMoreBefore ?? payload.pagination?.has_more_before,
    ),
    hasMoreAfter: Boolean(
      payload.pagination?.hasMoreAfter ?? payload.pagination?.has_more_after,
    ),
  },
  capabilities: normalizeChatCapabilities(payload.capabilities),
  sync: {
    transport: String(payload.sync?.transport || "polling"),
    recommendedIntervalMs: Number(payload.sync?.recommendedIntervalMs) || 4000,
  },
});

export const createCampaignChatApiClient = (client = jsonApiClient) => {
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/chat/messages`;

  return {
    async list(campaignId, page = {}, requestOptions = {}) {
      const payload = await client.request(
        `${base(campaignId)}${queryString(page)}`,
        requestOptions,
      );
      return normalizeList(payload);
    },

    async send(campaignId, body, clientNonce) {
      const payload = await client.request(base(campaignId), {
        method: "POST",
        body: { body: String(body), clientNonce },
      });
      return {
        message: normalizeChatMessage(payload.message),
        capabilities: normalizeChatCapabilities(payload.capabilities),
        duplicate: payload.duplicate === true,
      };
    },
  };
};

export const campaignChatApiClient = createCampaignChatApiClient();
