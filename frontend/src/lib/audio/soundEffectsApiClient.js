import { jsonApiClient } from "@/lib/api/jsonApiClient";

const path = (campaignId, suffix = "") =>
  `campaigns/${Number(campaignId)}/sound-effects${suffix}`;

export const createSoundEffectsApiClient = (api = jsonApiClient) => ({
  snapshot: (campaignId) => api.request(path(campaignId)),
  createScreen: (campaignId, payload = {}) =>
    api.request(path(campaignId, "/screens"), {
      method: "POST",
      body: payload,
    }),
  updateScreen: (campaignId, screenId, payload) =>
    api.request(path(campaignId, `/screens/${Number(screenId)}`), {
      method: "PATCH",
      body: payload,
    }),
  duplicateScreen: (campaignId, screenId) =>
    api.request(path(campaignId, `/screens/${Number(screenId)}/duplicate`), {
      method: "POST",
      body: {},
    }),
  deleteScreen: (campaignId, screenId) =>
    api.request(path(campaignId, `/screens/${Number(screenId)}`), {
      method: "DELETE",
    }),
  saveSlot: (campaignId, screenId, position, payload) =>
    api.request(
      path(
        campaignId,
        `/screens/${Number(screenId)}/slots/${Number(position)}`,
      ),
      { method: "PUT", body: payload },
    ),
  deleteSlot: (campaignId, screenId, position) =>
    api.request(
      path(
        campaignId,
        `/screens/${Number(screenId)}/slots/${Number(position)}`,
      ),
      { method: "DELETE" },
    ),
});

export const soundEffectsApiClient = createSoundEffectsApiClient();
