import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeToken } from "./tokenNormalizer";

const segment = (value, name) => {
  const id = Number(value);
  if (!Number.isSafeInteger(id) || id < 1)
    throw new TypeError(`${name}_required`);
  return encodeURIComponent(String(id));
};

export const normalizeTokenTemplate = (source = {}) => ({
  id: Number(source.id) || null,
  name: String(source.name || ""),
  imageUrl: String(source.imageUrl || source.image_url || ""),
  imageAssetId: Number(source.imageAssetId || source.image_asset_id) || null,
  imageAsset: source.imageAsset || null,
  widthCells: Number(source.widthCells ?? source.width_cells ?? 1) || 1,
  heightCells: Number(source.heightCells ?? source.height_cells ?? 1) || 1,
  rotation: Number(source.rotation) || 0,
  facing: Number(source.facing ?? source.rotation) || 0,
  rotationHandleEnabled: source.rotationHandleEnabled === true,
  facingHandleEnabled: source.facingHandleEnabled === true,
  rotationFollowsFacing: source.rotationFollowsFacing === true,
  showInfoUnselected: source.showInfoUnselected === true,
  resourceBarPosition: String(source.resourceBarPosition || "below"),
  elevation: Number(source.elevation) || 0,
  disposition: String(source.disposition || "neutral"),
  movementRange: Math.max(0, Number(source.movementRange ?? 6) || 0),
  movementResetMode: String(source.movementResetMode || "turn"),
  resources: source.resources || {},
  vision: source.vision || {},
  revision: Number(source.revision) || 1,
  createdAt: source.createdAt || null,
  updatedAt: source.updatedAt || null,
});

export const createTokenTemplateApiClient = (client = jsonApiClient) => ({
  async list(campaignId, options = {}) {
    const payload = await client.request(
      `/campaigns/${segment(campaignId, "campaign_id")}/token-templates`,
      options,
    );
    return {
      items: (payload?.items || []).map(normalizeTokenTemplate),
      capabilities: payload?.capabilities || { canCreateToken: false },
    };
  },
  async instantiate(campaignId, sceneId, templateId, centerX, centerY) {
    const payload = await client.request(
      `/campaigns/${segment(campaignId, "campaign_id")}/scenes/${segment(
        sceneId,
        "scene_id",
      )}/tokens/from-template`,
      {
        method: "POST",
        body: {
          templateId: Number(templateId),
          centerX: Number(centerX),
          centerY: Number(centerY),
        },
      },
    );
    return payload?.token ? normalizeToken(payload.token) : null;
  },
});

export const tokenTemplateApiClient = createTokenTemplateApiClient();
