import { jsonApiClient } from "@/lib/api/jsonApiClient";

const segment = (value, name) => {
  if (value === null || value === undefined || value === "") {
    throw new TypeError(`${name}_required`);
  }
  return encodeURIComponent(String(value));
};

const normalizeSummary = (entry = {}) => ({
  id: Number(entry.id),
  characterId: Number(entry.characterId),
  type: String(entry.type || "quest"),
  title: String(entry.title || ""),
  status: String(entry.status || "in_progress"),
  summary: String(entry.summary || ""),
  sessionNumber:
    entry.sessionNumber === null || entry.sessionNumber === undefined
      ? null
      : Number(entry.sessionNumber),
  occurredOn: entry.occurredOn || null,
  visibility: String(entry.visibility || "private"),
  archived: entry.archived === true,
  updatedAt: entry.updatedAt || null,
  revision: Number(entry.revision || 1),
  capabilities: {
    canEdit: entry.capabilities?.canEdit === true,
    canEditPrivate: entry.capabilities?.canEditPrivate === true,
  },
});

const normalizeEntry = (entry = {}) => ({
  ...normalizeSummary(entry),
  ownerUserId: Number(entry.ownerUserId) || null,
  author: {
    id: Number(entry.author?.id) || null,
    name: String(entry.author?.name || ""),
  },
  trustLevel:
    entry.trustLevel === null || entry.trustLevel === undefined
      ? null
      : Number(entry.trustLevel),
  createdAt: entry.createdAt || null,
  sections: Array.isArray(entry.sections)
    ? entry.sections.map((section) => ({
        id: Number(section.id) || null,
        key: String(section.key || ""),
        content: String(section.content || ""),
        visibility: section.visibility || null,
      }))
    : [],
  checklist: Array.isArray(entry.checklist)
    ? entry.checklist.map((item) => ({
        id: Number(item.id),
        label: String(item.label || ""),
        isCompleted: item.isCompleted === true,
        completedAt: item.completedAt || null,
      }))
    : [],
  encounters: Array.isArray(entry.encounters)
    ? entry.encounters.map((item) => ({
        id: Number(item.id) || null,
        sessionNumber:
          item.sessionNumber === null || item.sessionNumber === undefined
            ? null
            : Number(item.sessionNumber),
        occurredOn: item.occurredOn || null,
        summary: String(item.summary || ""),
      }))
    : [],
  relations: Array.isArray(entry.relations)
    ? entry.relations.map((relation) => ({
        id: Number(relation.id),
        relationType: String(relation.relationType || "related"),
        target: normalizeSummary(relation.target),
      }))
    : [],
});

const normalizeDetail = (payload = {}) => ({
  entry: normalizeEntry(payload.entry),
  character: payload.character || null,
});

export const createJournalApiClient = (client = jsonApiClient) => {
  const base = (campaignId) =>
    `/campaigns/${segment(campaignId, "campaign_id")}/journal`;
  const item = (campaignId, entryId) =>
    `${base(campaignId)}/${segment(entryId, "entry_id")}`;

  return {
    async list(campaignId, characterId, filters = {}, options = {}) {
      const query = new URLSearchParams({ characterId: String(characterId) });
      for (const key of ["type", "status", "archived"]) {
        if (filters[key] !== undefined && filters[key] !== "") {
          query.set(key, String(filters[key]));
        }
      }
      const payload = await client.request(
        `${base(campaignId)}?${query}`,
        options,
      );
      return {
        items: Array.isArray(payload?.items)
          ? payload.items.map(normalizeSummary)
          : [],
        character: payload?.character || null,
        capabilities: {
          canCreate: payload?.capabilities?.canCreate === true,
          canManageCampaign: payload?.capabilities?.canManageCampaign === true,
        },
      };
    },
    async get(campaignId, entryId, options = {}) {
      return normalizeDetail(
        await client.request(item(campaignId, entryId), options),
      );
    },
    async create(campaignId, draft) {
      return normalizeDetail(
        await client.request(base(campaignId), { method: "POST", body: draft }),
      );
    },
    async update(campaignId, entryId, draft) {
      return normalizeDetail(
        await client.request(item(campaignId, entryId), {
          method: "PATCH",
          body: draft,
        }),
      );
    },
    async archive(campaignId, entryId, revision, archived) {
      return normalizeDetail(
        await client.request(`${item(campaignId, entryId)}/archive`, {
          method: "PATCH",
          body: { revision, archived },
        }),
      );
    },
    async remove(campaignId, entryId, revision) {
      return client.request(item(campaignId, entryId), {
        method: "DELETE",
        body: { revision },
      });
    },
    async addChecklistItem(campaignId, entryId, label) {
      return normalizeDetail(
        await client.request(`${item(campaignId, entryId)}/checklist`, {
          method: "POST",
          body: { label },
        }),
      );
    },
    async updateChecklistItem(campaignId, entryId, checklistId, changes) {
      return normalizeDetail(
        await client.request(
          `${item(campaignId, entryId)}/checklist/${segment(checklistId, "checklist_id")}`,
          { method: "PATCH", body: changes },
        ),
      );
    },
    async deleteChecklistItem(campaignId, entryId, checklistId) {
      return normalizeDetail(
        await client.request(
          `${item(campaignId, entryId)}/checklist/${segment(checklistId, "checklist_id")}`,
          { method: "DELETE" },
        ),
      );
    },
    async addRelation(campaignId, entryId, relation) {
      return normalizeDetail(
        await client.request(`${item(campaignId, entryId)}/relations`, {
          method: "POST",
          body: relation,
        }),
      );
    },
    async deleteRelation(campaignId, entryId, relationId) {
      return normalizeDetail(
        await client.request(
          `${item(campaignId, entryId)}/relations/${segment(relationId, "relation_id")}`,
          { method: "DELETE" },
        ),
      );
    },
  };
};

export const journalApiClient = createJournalApiClient();
