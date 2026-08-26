import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeCombat } from "./combatNormalizer";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

export const createCombatApiClient = (client = jsonApiClient) => {
  const base = (campaignId, sceneId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
      sceneId,
      "scene_id",
    )}/combat`;
  return {
    async get(campaignId, sceneId) {
      const payload = await client.request(base(campaignId, sceneId));
      return {
        combat: normalizeCombat(payload?.combat, sceneId),
        capabilities: payload?.capabilities || { canManage: false },
      };
    },
  };
};

export const combatApiClient = createCombatApiClient();
