import { jsonApiClient } from "@/lib/api/jsonApiClient";

const idSegment = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) throw new TypeError(`${name}_required`);
  return id;
};

export const createProfessionApiClient = (client = jsonApiClient) => ({
  catalog(campaignId, options = {}) {
    return client.request(
      `/campaigns/${idSegment(campaignId, "campaign_id")}/professions`,
      { signal: options.signal },
    );
  },
  systemCatalog(systemId, options = {}) {
    return client.request(
      `/systems/${idSegment(systemId, "system_id")}/professions`,
      { signal: options.signal },
    );
  },
  characterHistory(campaignId, characterId, options = {}) {
    return client.request(
      `/campaigns/${idSegment(
        campaignId,
        "campaign_id",
      )}/characters/${idSegment(characterId, "character_id")}/professions`,
      { signal: options.signal },
    );
  },
  changeCharacterProfession(campaignId, characterId, professionId) {
    return client.request(
      `/campaigns/${idSegment(
        campaignId,
        "campaign_id",
      )}/characters/${idSegment(characterId, "character_id")}/profession`,
      {
        method: "PUT",
        body: { professionId: idSegment(professionId, "profession_id") },
      },
    );
  },
  reorderCharacterProfessions(campaignId, characterId, historyIds) {
    if (!Array.isArray(historyIds)) {
      throw new TypeError("history_ids_required");
    }
    return client.request(
      `/campaigns/${idSegment(
        campaignId,
        "campaign_id",
      )}/characters/${idSegment(
        characterId,
        "character_id",
      )}/professions/history-order`,
      {
        method: "PUT",
        body: {
          historyIds: historyIds.map((id) =>
            idSegment(id, "profession_history_id"),
          ),
        },
      },
    );
  },
  activateCharacterProfession(campaignId, characterId, historyId) {
    return client.request(
      `/campaigns/${idSegment(
        campaignId,
        "campaign_id",
      )}/characters/${idSegment(
        characterId,
        "character_id",
      )}/professions/${idSegment(historyId, "profession_history_id")}/activate`,
      { method: "PUT" },
    );
  },
  deleteCharacterProfession(campaignId, characterId, historyId) {
    return client.request(
      `/campaigns/${idSegment(
        campaignId,
        "campaign_id",
      )}/characters/${idSegment(
        characterId,
        "character_id",
      )}/professions/${idSegment(historyId, "profession_history_id")}`,
      { method: "DELETE" },
    );
  },
});

export const professionApiClient = createProfessionApiClient();
