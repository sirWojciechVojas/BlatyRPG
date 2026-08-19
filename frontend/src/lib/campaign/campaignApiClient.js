import { jsonApiClient } from "@/lib/api/jsonApiClient";
import {
  arrayFromPayload,
  normalizeCampaign,
  normalizeCampaignCapabilities,
  normalizeCampaignCharacter,
  normalizeCampaignMember,
  normalizeInvitation,
} from "./campaignModels";
import { createCampaignPermissionMethods } from "./campaignPermissionApi";

export { normalizeCampaign } from "./campaignModels";

const positiveId = (value, name = "id") => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) {
    throw new TypeError(`${name}_must_be_positive_integer`);
  }
  return id;
};

const campaignPath = (campaignId, suffix = "") =>
  `/campaigns/${positiveId(campaignId, "campaignId")}${suffix}`;

const campaignFrom = (payload) =>
  normalizeCampaign(
    payload?.campaign ?? payload?.data?.campaign ?? payload?.data ?? payload,
  );

const capabilitiesFrom = (payload) =>
  normalizeCampaignCapabilities(
    payload?.capabilities ?? payload?.data?.capabilities,
  );

const collection = (payload, names, normalize) => ({
  items: arrayFromPayload(payload, names)
    .map(normalize)
    .filter((item) => item.id),
  capabilities: capabilitiesFrom(payload),
});

const trimmedSettings = (draft = {}) => {
  const body = {};
  if (draft.name !== undefined) body.name = String(draft.name || "").trim();
  if (draft.description !== undefined) {
    body.description = String(draft.description || "").trim();
  }
  if (draft.bannerUrl !== undefined) {
    body.bannerUrl = String(draft.bannerUrl || "").trim();
  }
  if (draft.status !== undefined) {
    body.status = String(draft.status || "")
      .trim()
      .toLowerCase();
  }
  if (draft.settings !== undefined) body.settings = draft.settings;
  return body;
};

export const createCampaignApiClient = (client = jsonApiClient) => ({
  async list(options = {}) {
    const payload = await client.request("/campaigns", options);
    const result = collection(
      payload,
      ["campaigns", "items"],
      normalizeCampaign,
    );
    return {
      campaigns: result.items,
      capabilities: {
        canCreate: Boolean(
          payload?.capabilities?.canCreate ??
          payload?.capabilities?.can_create ??
          payload?.data?.capabilities?.canCreate ??
          payload?.data?.capabilities?.can_create,
        ),
      },
    };
  },

  async create(draft) {
    const payload = await client.request("/campaigns", {
      method: "POST",
      body: {
        name: String(draft.name || "").trim(),
        description: String(draft.description || "").trim(),
        systemType: String(draft.systemType || "").trim(),
      },
    });
    return campaignFrom(payload);
  },

  async get(campaignId, options = {}) {
    const payload = await client.request(
      campaignPath(campaignId, "/settings"),
      options,
    );
    return campaignFrom(payload);
  },

  async enter(campaignId) {
    const payload = await client.request(campaignPath(campaignId, "/enter"), {
      method: "POST",
    });
    return campaignFrom(payload);
  },

  async updateSettings(campaignId, draft) {
    const payload = await client.request(
      campaignPath(campaignId, "/settings"),
      { method: "PATCH", body: trimmedSettings(draft) },
    );
    return campaignFrom(payload);
  },

  async listMembers(campaignId, options = {}) {
    const payload = await client.request(
      campaignPath(campaignId, "/members"),
      options,
    );
    const result = collection(
      payload,
      ["members", "items"],
      normalizeCampaignMember,
    );
    return { members: result.items, capabilities: result.capabilities };
  },

  async changeMemberRole(campaignId, userId, role) {
    const payload = await client.request(
      campaignPath(campaignId, `/members/${positiveId(userId, "userId")}`),
      {
        method: "PATCH",
        body: {
          role: String(role || "")
            .trim()
            .toLowerCase(),
        },
      },
    );
    return normalizeCampaignMember(
      payload?.member ?? payload?.data?.member ?? payload,
    );
  },

  removeMember(campaignId, userId) {
    return client.request(
      campaignPath(campaignId, `/members/${positiveId(userId, "userId")}`),
      { method: "DELETE" },
    );
  },

  async listInvitations(campaignId, options = {}) {
    const payload = await client.request(
      campaignPath(campaignId, "/invitations"),
      options,
    );
    return arrayFromPayload(payload, ["invitations", "items"])
      .map(normalizeInvitation)
      .filter((item) => item.id);
  },

  async listMyInvitations(options = {}) {
    const payload = await client.request("/campaign-invitations", options);
    return arrayFromPayload(payload, ["invitations", "items"])
      .map(normalizeInvitation)
      .filter((item) => item.id);
  },

  async invite(campaignId, draft) {
    const body = {
      role: String(draft.role || "player")
        .trim()
        .toLowerCase(),
      message: String(draft.message || "").trim() || undefined,
    };
    if (Number(draft.userId) > 0) body.userId = Number(draft.userId);
    else body.identifier = String(draft.identifier || "").trim();
    const payload = await client.request(
      campaignPath(campaignId, "/invitations"),
      { method: "POST", body },
    );
    return normalizeInvitation(
      payload?.invitation ?? payload?.data?.invitation ?? payload,
    );
  },

  respondToInvitation(invitationId, response) {
    const action = response === "accept" ? "accept" : "reject";
    return client.request(
      `/campaign-invitations/${positiveId(invitationId, "invitationId")}/${action}`,
      { method: "POST" },
    );
  },

  revokeInvitation(campaignId, invitationId) {
    return client.request(
      campaignPath(
        campaignId,
        `/invitations/${positiveId(invitationId, "invitationId")}`,
      ),
      { method: "DELETE" },
    );
  },

  async listCharacters(campaignId, options = {}) {
    const payload = await client.request(
      campaignPath(campaignId, "/characters"),
      options,
    );
    const result = collection(
      payload,
      ["characters", "items"],
      normalizeCampaignCharacter,
    );
    return { characters: result.items, capabilities: result.capabilities };
  },

  ...createCampaignPermissionMethods(client),

  realtimeTicket(campaignId, clientInstanceId) {
    return client.request(campaignPath(campaignId, "/realtime-ticket"), {
      method: "POST",
      body: { clientInstanceId: String(clientInstanceId || "") },
    });
  },
});

export const campaignApiClient = createCampaignApiClient();
