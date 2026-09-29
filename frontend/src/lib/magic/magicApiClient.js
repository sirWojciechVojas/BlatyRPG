import { jsonApiClient } from "@/lib/api/jsonApiClient";

const base = (campaignId, characterId) =>
  `/campaigns/${Number(campaignId)}/characters/${Number(characterId)}/magic`;

export const createMagicApiClient = (client = jsonApiClient) => ({
  get(campaignId, characterId, options = {}) {
    return client.request(base(campaignId, characterId), options);
  },

  preferences(campaignId, characterId, changes) {
    return client.request(`${base(campaignId, characterId)}/preferences`, {
      method: "PATCH",
      body: changes,
    });
  },

  requestLearning(campaignId, characterId, request) {
    return client.request(`${base(campaignId, characterId)}/learning`, {
      method: "POST",
      body: request,
    });
  },

  decideLearning(campaignId, requestId, decision) {
    return client.request(
      `/campaigns/${Number(campaignId)}/magic/learning/${Number(requestId)}/decision`,
      { method: "POST", body: decision },
    );
  },

  createCast(campaignId, characterId, declaration) {
    return client.request(`${base(campaignId, characterId)}/casts`, {
      method: "POST",
      body: declaration,
    });
  },

  channel(campaignId, castId) {
    return client.request(
      `/campaigns/${Number(campaignId)}/magic/casts/${Number(castId)}/channel`,
      { method: "POST", body: {} },
    );
  },

  advanceCast(campaignId, castId) {
    return client.request(
      `/campaigns/${Number(campaignId)}/magic/casts/${Number(castId)}/advance`,
      { method: "POST", body: {} },
    );
  },

  resolveCast(campaignId, castId) {
    return client.request(
      `/campaigns/${Number(campaignId)}/magic/casts/${Number(castId)}/resolve`,
      { method: "POST", body: {} },
    );
  },

  cancelCast(campaignId, castId) {
    return client.request(
      `/campaigns/${Number(campaignId)}/magic/casts/${Number(castId)}/cancel`,
      { method: "POST", body: {} },
    );
  },

  createRitual(campaignId, characterId, ritual) {
    return client.request(`${base(campaignId, characterId)}/rituals`, {
      method: "POST",
      body: ritual,
    });
  },
});

export const magicApiClient = createMagicApiClient();
