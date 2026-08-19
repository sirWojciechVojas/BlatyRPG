import { emptyCampaignCapabilities } from "./state";

const applies = (state, generation) => state.generation === generation;

export const campaignContextMutations = {
  SWITCH_CAMPAIGN(state, { campaignId, campaign = null }) {
    state.generation += 1;
    state.campaignId = Number(campaignId) || null;
    state.currentCampaign = campaign;
    state.members = [];
    state.invitations = [];
    state.characters = [];
    state.characterPermissions = {};
    state.capabilities = emptyCampaignCapabilities();
    state.phase = state.campaignId ? "loading" : "idle";
    state.pendingRequests = 0;
    state.error = null;
    state.unauthorized = false;
  },
  REQUEST_STARTED(state, generation) {
    if (!applies(state, generation)) return;
    state.pendingRequests += 1;
    state.error = null;
    state.unauthorized = false;
  },
  REQUEST_FINISHED(state, generation) {
    if (!applies(state, generation)) return;
    state.pendingRequests = Math.max(0, state.pendingRequests - 1);
    if (!state.pendingRequests) state.phase = "ready";
  },
  REQUEST_FAILED(state, { generation, error }) {
    if (!applies(state, generation)) return;
    state.pendingRequests = Math.max(0, state.pendingRequests - 1);
    state.phase = "error";
    state.error = error;
    state.unauthorized = error.status === 401 || error.status === 403;
  },
  SET_CAMPAIGN(state, { generation, campaign }) {
    if (!applies(state, generation)) return;
    state.currentCampaign = campaign;
    state.capabilities = campaign.capabilities || emptyCampaignCapabilities();
  },
  SET_MEMBERS(state, { generation, members }) {
    if (applies(state, generation)) state.members = members;
  },
  SET_INVITATIONS(state, { generation, invitations }) {
    if (applies(state, generation)) state.invitations = invitations;
  },
  SET_CHARACTERS(state, { generation, characters }) {
    if (applies(state, generation)) state.characters = characters;
  },
  SET_CHARACTER_PERMISSIONS(state, { generation, characterId, permissions }) {
    if (!applies(state, generation)) return;
    state.characterPermissions = {
      ...state.characterPermissions,
      [Number(characterId)]: permissions,
    };
  },
  UPSERT_MEMBER(state, { generation, member }) {
    if (!applies(state, generation)) return;
    const index = state.members.findIndex(
      (item) => Number(item.userId) === Number(member.userId),
    );
    if (index < 0) state.members.push(member);
    else state.members.splice(index, 1, member);
  },
  REMOVE_MEMBER(state, { generation, userId }) {
    if (!applies(state, generation)) return;
    state.members = state.members.filter(
      (item) => Number(item.userId) !== Number(userId),
    );
  },
  UPSERT_INVITATION(state, { generation, invitation }) {
    if (!applies(state, generation)) return;
    const index = state.invitations.findIndex(
      (item) => Number(item.id) === Number(invitation.id),
    );
    if (index < 0) state.invitations.unshift(invitation);
    else state.invitations.splice(index, 1, invitation);
  },
  REMOVE_INVITATION(state, { generation, invitationId }) {
    if (!applies(state, generation)) return;
    state.invitations = state.invitations.filter(
      (item) => Number(item.id) !== Number(invitationId),
    );
  },
};
