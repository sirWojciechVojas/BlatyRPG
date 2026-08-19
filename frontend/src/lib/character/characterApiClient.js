import { jsonApiClient } from "@/lib/api/jsonApiClient";

const unwrapItems = (payload) => {
  for (const value of [
    payload?.items,
    payload?.characters,
    payload?.data?.items,
  ]) {
    if (Array.isArray(value)) return value;
  }
  return [];
};

const plainObject = (value) =>
  value && typeof value === "object" && !Array.isArray(value) ? value : {};

export const normalizeCharacter = (source = {}) => ({
  id: Number(source.id) || null,
  campaignId: Number(source.campaignId ?? source.campaign_id) || null,
  ownerUserId: Number(source.ownerUserId ?? source.user_id) || null,
  systemId: Number(source.systemId ?? source.system_id) || null,
  universeId: Number(source.universeId ?? source.universe_id) || null,
  name: String(source.name || ""),
  data: plainObject(source.data),
  avatarUrl: String(
    source.avatarUrl ?? source.avatar_url ?? source.avatar ?? "",
  ),
  assets: plainObject(source.assets),
  brass: Math.max(0, Number(source.brass) || 0),
  primaryCurrencyCode: String(
    source.primaryCurrencyCode ?? source.primary_currency_code ?? "",
  ),
  revision: Math.max(1, Number(source.revision) || 1),
  isLegacyUnassigned: Boolean(
    source.isLegacyUnassigned ?? source.is_legacy_unassigned,
  ),
  capabilities: {
    canEdit: Boolean(
      source.capabilities?.canEdit ?? source.capabilities?.can_edit,
    ),
    canDelete: Boolean(
      source.capabilities?.canDelete ?? source.capabilities?.can_delete,
    ),
  },
  createdAt: source.createdAt ?? source.created_at ?? null,
  updatedAt: source.updatedAt ?? source.updated_at ?? null,
});

const campaignQuery = (campaignId) => {
  const id = Number(campaignId);
  if (!Number.isInteger(id) || id < 1) {
    throw new TypeError("campaignId must be a positive integer");
  }
  return `campaignId=${encodeURIComponent(id)}`;
};

const unwrapCharacter = (payload) =>
  normalizeCharacter(
    payload?.character ?? payload?.data?.character ?? payload?.data ?? payload,
  );

export const createCharacterApiClient = (client = jsonApiClient) => ({
  async list(campaignId, options = {}) {
    const payload = await client.request(
      `/characters?${campaignQuery(campaignId)}`,
      options,
    );
    return {
      characters: unwrapItems(payload)
        .map(normalizeCharacter)
        .filter((item) => item.id),
      capabilities: {
        canCreate: Boolean(
          payload?.capabilities?.canCreate ?? payload?.capabilities?.can_create,
        ),
      },
    };
  },

  async get(campaignId, characterId, options = {}) {
    const payload = await client.request(
      `/characters/${Number(characterId)}?${campaignQuery(campaignId)}`,
      options,
    );
    return unwrapCharacter(payload);
  },

  async create(campaignId, draft) {
    const payload = await client.request("/characters", {
      method: "POST",
      body: {
        campaignId: Number(campaignId),
        systemId: Number(draft.systemId),
        universeId: Number(draft.universeId),
        name: String(draft.name || "").trim(),
        data: plainObject(draft.data),
        avatarUrl: String(draft.avatarUrl || "").trim(),
      },
    });
    return unwrapCharacter(payload);
  },
  async update(campaignId, characterId, draft) {
    const payload = await client.request(
      `/characters/${Number(characterId)}?${campaignQuery(campaignId)}`,
      {
        method: "PUT",
        body: {
          name: String(draft.name || "").trim(),
          data: plainObject(draft.data),
          avatarUrl: String(draft.avatarUrl || "").trim(),
          revision: Math.max(1, Number(draft.revision) || 1),
          updatedAt: draft.updatedAt || null,
        },
      },
    );
    return unwrapCharacter(payload);
  },

  async delete(campaignId, characterId) {
    const path =
      "/characters/" + Number(characterId) + "?" + campaignQuery(campaignId);
    return client.request(path, { method: "DELETE" });
  },
});

export const characterApiClient = createCharacterApiClient();
