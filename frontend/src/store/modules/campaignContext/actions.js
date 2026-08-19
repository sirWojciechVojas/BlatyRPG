const normalizedError = (error) => ({
  code: String(error?.code || error?.message || "unknown_error"),
  status: Number(error?.status || 0),
  network: error?.network === true,
  details: error?.payload?.errors || null,
});

const forbidden = () => {
  const error = new Error("forbidden");
  error.code = "forbidden";
  error.status = 403;
  return error;
};

const current = (state, generation, campaignId = state.campaignId) =>
  state.generation === generation &&
  Number(state.campaignId) === Number(campaignId);

const assertManage = (state) => {
  if (!state.capabilities.canManage) throw forbidden();
};

const start = (state, commit) => {
  const generation = state.generation;
  commit("REQUEST_STARTED", generation);
  return generation;
};

const finish = (state, commit, generation) => {
  if (current(state, generation)) commit("REQUEST_FINISHED", generation);
};

const fail = (state, commit, generation, error) => {
  if (current(state, generation)) {
    commit("REQUEST_FAILED", {
      generation,
      error: normalizedError(error),
    });
  }
  throw error;
};

export const createCampaignContextActions = (api) => ({
  async selectCampaign({ state, commit }, campaignId) {
    const id = Number(campaignId);
    commit("SWITCH_CAMPAIGN", { campaignId: id });
    const generation = start(state, commit);
    try {
      const campaign = await api.enter(id);
      if (!current(state, generation, id)) return null;
      commit("SET_CAMPAIGN", { generation, campaign });
      const requests = [api.listMembers(id), api.listCharacters(id)];
      if (campaign.capabilities.canManage) {
        requests.push(api.listInvitations(id));
      }
      const [memberResult, characterResult, invitations = []] =
        await Promise.all(requests);
      if (!current(state, generation, id)) return null;
      commit("SET_MEMBERS", {
        generation,
        members: memberResult.members,
      });
      commit("SET_CHARACTERS", {
        generation,
        characters: characterResult.characters,
      });
      commit("SET_INVITATIONS", { generation, invitations });
      finish(state, commit, generation);
      return campaign;
    } catch (error) {
      if (!current(state, generation, id)) return null;
      return fail(state, commit, generation, error);
    }
  },

  leaveCampaign({ commit }) {
    commit("SWITCH_CAMPAIGN", { campaignId: null });
  },

  async refresh({ state, dispatch }) {
    if (!state.campaignId) return null;
    return dispatch("selectCampaign", state.campaignId);
  },

  async updateSettings({ state, commit }, draft) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      const campaign = await api.updateSettings(state.campaignId, draft);
      if (!current(state, generation)) return null;
      commit("SET_CAMPAIGN", { generation, campaign });
      finish(state, commit, generation);
      return campaign;
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async invite({ state, commit }, draft) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      const invitation = await api.invite(state.campaignId, draft);
      if (!current(state, generation)) return null;
      commit("UPSERT_INVITATION", { generation, invitation });
      finish(state, commit, generation);
      return invitation;
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async revokeInvitation({ state, commit }, invitationId) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      await api.revokeInvitation(state.campaignId, invitationId);
      if (!current(state, generation)) return;
      commit("REMOVE_INVITATION", { generation, invitationId });
      finish(state, commit, generation);
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async changeMemberRole({ state, commit }, { userId, role }) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      const member = await api.changeMemberRole(state.campaignId, userId, role);
      if (!current(state, generation)) return null;
      commit("UPSERT_MEMBER", { generation, member });
      finish(state, commit, generation);
      return member;
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async removeMember({ state, commit }, userId) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      await api.removeMember(state.campaignId, userId);
      if (!current(state, generation)) return;
      commit("REMOVE_MEMBER", { generation, userId });
      finish(state, commit, generation);
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async loadCharacterPermissions({ state, commit }, characterId) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      const permissions = await api.listResourcePermissions(
        state.campaignId,
        "character",
        characterId,
      );
      if (!current(state, generation)) return [];
      commit("SET_CHARACTER_PERMISSIONS", {
        generation,
        characterId,
        permissions,
      });
      finish(state, commit, generation);
      return permissions;
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async setCharacterAccess(
    { state, commit, dispatch },
    { characterId, userId, accessLevel },
  ) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      await api.setResourcePermission(
        state.campaignId,
        "character",
        characterId,
        userId,
        accessLevel,
      );
      if (!current(state, generation)) return;
      finish(state, commit, generation);
      return dispatch("loadCharacterPermissions", characterId);
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async assignCharacterOwner(
    { state, commit, dispatch },
    { characterId, userId, primary },
  ) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      await api.assignCharacterOwner(
        state.campaignId,
        characterId,
        userId,
        primary,
      );
      if (!current(state, generation)) return;
      finish(state, commit, generation);
      return dispatch("refresh");
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },

  async updateCharacterVisibility(
    { state, commit, dispatch },
    { characterId, visibility },
  ) {
    assertManage(state);
    const generation = start(state, commit);
    try {
      await api.updateCharacterVisibility(
        state.campaignId,
        characterId,
        visibility,
      );
      if (!current(state, generation)) return;
      finish(state, commit, generation);
      return dispatch("refresh");
    } catch (error) {
      return fail(state, commit, generation, error);
    }
  },
});
