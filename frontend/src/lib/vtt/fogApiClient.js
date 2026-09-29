import { jsonApiClient } from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) throw new TypeError(`${name}_required`);
  return encodeURIComponent(String(id));
};

export const normalizeFog = (value = {}) => ({
  sceneId: Number(value.sceneId ?? value.scene_id),
  userId: Number(value.userId ?? value.user_id),
  cellSize: Number(value.cellSize ?? value.cell_size) || 64,
  exploredRanges: Array.isArray(value.exploredRanges)
    ? value.exploredRanges
    : [],
  forcedHiddenRanges: Array.isArray(value.forcedHiddenRanges)
    ? value.forcedHiddenRanges
    : [],
  visionTokenIds: Array.isArray(value.visionTokenIds)
    ? value.visionTokenIds.map(Number).filter(Number.isInteger)
    : [],
  revision: Number(value.revision) || 0,
  changed: value.changed === true,
  resetAll: value.resetAll === true,
  shared: value.shared === true,
  explorationMode: String(value.explorationMode || "individual"),
  capabilities: value.capabilities || { canManage: false },
});

export const createFogApiClient = (client = jsonApiClient) => {
  const path = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(sceneId, "scene_id")}/fog`;
  return {
    async get(campaignId, sceneId, userId = null) {
      const suffix =
        Number(userId) > 0 ? `?userId=${segment(userId, "user_id")}` : "";
      return normalizeFog(
        await client.request(`${path(campaignId, sceneId)}${suffix}`),
      );
    },
    async patch(campaignId, sceneId, changes) {
      return normalizeFog(
        await client.request(path(campaignId, sceneId), {
          method: "PATCH",
          body: changes,
        }),
      );
    },
    async reset(campaignId, sceneId) {
      return normalizeFog(
        await client.request(`${path(campaignId, sceneId)}/reset`, {
          method: "POST",
          body: {},
        }),
      );
    },
  };
};

export const fogApiClient = createFogApiClient();
