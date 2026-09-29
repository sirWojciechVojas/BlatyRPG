import { jsonApiClient } from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) throw new TypeError(`${name}_required`);
  return id;
};

export const createBestiaryApiClient = (client = jsonApiClient) => {
  const base = (campaignId, characterId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/characters/${segment(
      characterId,
      "character_id",
    )}/bestiary`;
  const assignmentBase = (campaignId, entryId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/bestiary/entries/${segment(
      entryId,
      "entry_id",
    )}`;

  return {
    list(campaignId, characterId) {
      return client.request(base(campaignId, characterId));
    },
    entry(campaignId, characterId, entryId) {
      return client.request(
        `${base(campaignId, characterId)}/${segment(entryId, "entry_id")}`,
      );
    },
    assignments(campaignId, entryId) {
      return client.request(
        `${assignmentBase(campaignId, entryId)}/assignments`,
      );
    },
    setAssignment(campaignId, entryId, characterId, level) {
      return client.request(
        `${assignmentBase(campaignId, entryId)}/characters/${segment(
          characterId,
          "character_id",
        )}`,
        { method: "PUT", body: { level } },
      );
    },
    setAssignments(campaignId, entryId, characterIds, level) {
      if (!Array.isArray(characterIds) || !characterIds.length) {
        throw new TypeError("character_ids_required");
      }
      return client.request(
        `${assignmentBase(campaignId, entryId)}/assignments`,
        {
          method: "PUT",
          body: {
            characterIds: characterIds.map((id) => segment(id, "character_id")),
            level,
          },
        },
      );
    },
    saveAssignments(campaignId, entryId, assignments, sectionKeys) {
      if (!Array.isArray(assignments)) {
        throw new TypeError("assignments_required");
      }
      if (sectionKeys !== undefined && !Array.isArray(sectionKeys)) {
        throw new TypeError("section_keys_required");
      }
      if (!assignments.length && sectionKeys === undefined) {
        throw new TypeError("assignments_required");
      }
      const body = {
        assignments: assignments.map((assignment) => ({
          characterId: segment(assignment.characterId, "character_id"),
          level: String(assignment.level || ""),
        })),
      };
      if (sectionKeys !== undefined) {
        body.sectionKeys = sectionKeys.map((key) => String(key));
      }
      return client.request(
        `${assignmentBase(campaignId, entryId)}/assignments`,
        {
          method: "PUT",
          body,
        },
      );
    },
  };
};

export const bestiaryApiClient = createBestiaryApiClient();
