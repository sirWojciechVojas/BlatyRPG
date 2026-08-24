export const createSceneElementActions = (api, options) => {
  const {
    name,
    plural,
    receiveMutation,
    upsertMutation,
    removeMutation,
    selectMutation,
    phaseMutation,
    failureMutation,
    normalizeError,
  } = options;
  const title = name[0].toUpperCase() + name.slice(1);
  const loadAction = `load${plural}`;
  const createAction = `create${title}`;
  const updateAction = `update${title}`;
  const deleteAction = `delete${title}`;

  return {
    async [loadAction]({ state, commit }) {
      const sceneId = state.selectedSceneId;
      if (sceneId === null || typeof api?.list !== "function") return;
      commit(phaseMutation, "loading");
      try {
        const result = await api.list(state.campaignId, sceneId);
        if (state.selectedSceneId !== sceneId) return;
        commit(receiveMutation, { sceneId, ...result });
      } catch (error) {
        commit(failureMutation, normalizeError(error));
        throw error;
      }
    },
    async [createAction]({ state, commit }, draft) {
      const sceneId = state.selectedSceneId;
      if (sceneId === null || typeof api?.create !== "function") return null;
      commit(phaseMutation, "saving");
      try {
        const item = await api.create(state.campaignId, sceneId, draft);
        if (state.selectedSceneId === sceneId) {
          commit(upsertMutation, item);
          commit(selectMutation, item.id);
        }
        commit(phaseMutation, "ready");
        return item;
      } catch (error) {
        commit(failureMutation, normalizeError(error));
        throw error;
      }
    },
    async [updateAction]({ state, commit }, payload) {
      const item = payload.item || payload[name];
      const { changes } = payload;
      if (!item || typeof api?.update !== "function") return null;
      commit(phaseMutation, "saving");
      try {
        const updated = await api.update(
          state.campaignId,
          item.sceneId,
          item.id,
          { ...changes, revision: item.revision },
        );
        commit(upsertMutation, updated);
        commit(phaseMutation, "ready");
        return updated;
      } catch (error) {
        commit(failureMutation, normalizeError(error));
        throw error;
      }
    },
    async [deleteAction]({ state, commit }, item) {
      if (!item || typeof api?.remove !== "function") return;
      commit(phaseMutation, "saving");
      try {
        await api.remove(
          state.campaignId,
          item.sceneId,
          item.id,
          item.revision,
        );
        commit(removeMutation, {
          sceneId: item.sceneId,
          [`${name}Id`]: item.id,
        });
        commit(phaseMutation, "ready");
      } catch (error) {
        commit(failureMutation, normalizeError(error));
        throw error;
      }
    },
  };
};
