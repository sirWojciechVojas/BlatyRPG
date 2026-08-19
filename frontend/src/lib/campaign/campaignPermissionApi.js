import {
  arrayFromPayload,
  normalizeResourcePermission,
} from "./campaignModels";

const RESOURCE_TYPES = new Set([
  "campaign",
  "character",
  "scene",
  "journal",
  "item",
  "resource",
]);
const ACCESS_LEVELS = new Set(["none", "limited", "observer", "owner"]);

const positiveId = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) {
    throw new TypeError(`${name}_must_be_positive_integer`);
  }
  return id;
};

const basePath = (campaignId, type, resourceId) => {
  const normalizedType = String(type || "")
    .trim()
    .toLowerCase();
  if (!RESOURCE_TYPES.has(normalizedType)) {
    throw new TypeError("invalid_resource_type");
  }
  return (
    `/campaigns/${positiveId(campaignId, "campaignId")}/resources/` +
    `${normalizedType}/${positiveId(resourceId, "resourceId")}/permissions`
  );
};

const accessLevel = (value, errorCode = "invalid_access_level") => {
  const normalized = String(value || "")
    .trim()
    .toLowerCase();
  if (!ACCESS_LEVELS.has(normalized)) throw new TypeError(errorCode);
  return normalized;
};

export const createCampaignPermissionMethods = (client) => ({
  async listResourcePermissions(campaignId, type, resourceId, options = {}) {
    const payload = await client.request(
      basePath(campaignId, type, resourceId),
      options,
    );
    return arrayFromPayload(payload, ["permissions", "items"])
      .map(normalizeResourcePermission)
      .filter((item) => item.id);
  },

  async setResourcePermission(campaignId, type, resourceId, userId, value) {
    const normalized = accessLevel(value);
    const payload = await client.request(
      `${basePath(campaignId, type, resourceId)}/${positiveId(userId, "userId")}`,
      { method: "PUT", body: { accessLevel: normalized } },
    );
    return normalizeResourcePermission(
      payload?.permission ?? payload?.data?.permission ?? payload,
    );
  },

  removeResourcePermission(campaignId, type, resourceId, userId) {
    return client.request(
      `${basePath(campaignId, type, resourceId)}/${positiveId(userId, "userId")}`,
      { method: "DELETE" },
    );
  },

  updateCharacterVisibility(campaignId, characterId, visibility) {
    return client.request(
      `/campaigns/${positiveId(campaignId, "campaignId")}/characters/` +
        `${positiveId(characterId, "characterId")}/visibility`,
      {
        method: "PATCH",
        body: { visibility: accessLevel(visibility, "invalid_visibility") },
      },
    );
  },

  assignCharacterOwner(campaignId, characterId, userId, primary = false) {
    return client.request(
      `/campaigns/${positiveId(campaignId, "campaignId")}/characters/` +
        `${positiveId(characterId, "characterId")}/owners`,
      {
        method: "POST",
        body: {
          userId: positiveId(userId, "userId"),
          primary: primary === true,
        },
      },
    );
  },
});
