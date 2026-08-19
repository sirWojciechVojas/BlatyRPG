export const createActionGroup4 = (deps) => {
  const { nextTemplateId, suggestionByIdMap } = deps;
  return {
    createTemplatesFromSuggestions({ state, commit }, payload = {}) {
      const suggestionIds = Array.isArray(payload.suggestionIds)
        ? payload.suggestionIds
        : state.shopEditorState.selectedSuggestionIds || [];
      const selectedSet = new Set(
        suggestionIds.map((entry) => String(entry || "")).filter(Boolean),
      );
      const byId = {
        ...suggestionByIdMap(state.shopTemplateRecommendations || []),
        ...suggestionByIdMap(state.shopSuggestions || []),
      };
      const suggestions = selectedSet.size
        ? Array.from(selectedSet)
            .map((id) => byId[id])
            .filter(Boolean)
        : (state.shopSuggestions || []).filter(Boolean);
      if (!suggestions.length) {
        return { created: 0, suggestionTemplateMap: {} };
      }

      let nextId = nextTemplateId(state.templateItems);
      const suggestionUpdates = (state.shopSuggestions || []).map((entry) => ({
        ...entry,
      }));
      const recommendationUpdates = (
        state.shopTemplateRecommendations || []
      ).map((entry) => ({ ...entry }));
      const suggestionIndexById = suggestionUpdates.reduce(
        (acc, entry, index) => {
          acc[String(entry?.suggestionId || "")] = index;
          return acc;
        },
        {},
      );
      const recommendationIndexById = recommendationUpdates.reduce(
        (acc, entry, index) => {
          acc[String(entry?.suggestionId || "")] = index;
          return acc;
        },
        {},
      );
      const suggestionTemplateMap = {};
      const processed = new Set();
      let created = 0;

      const patchEntryWithTemplate = (
        collection,
        indexById,
        suggestionId,
        id,
      ) => {
        const localIndex = indexById[suggestionId];
        if (!Number.isFinite(localIndex)) {
          return;
        }
        collection[localIndex] = {
          ...collection[localIndex],
          action: "use_existing",
          templateId: id,
        };
      };

      suggestions.forEach((entry) => {
        const suggestionId = String(entry?.suggestionId || "");
        if (!suggestionId || processed.has(suggestionId)) {
          return;
        }
        processed.add(suggestionId);

        if (
          Number.isFinite(Number(entry?.templateId)) &&
          entry?.action !== "create_draft"
        ) {
          const existingId = Number(entry.templateId);
          suggestionTemplateMap[suggestionId] = existingId;
          patchEntryWithTemplate(
            suggestionUpdates,
            suggestionIndexById,
            suggestionId,
            existingId,
          );
          patchEntryWithTemplate(
            recommendationUpdates,
            recommendationIndexById,
            suggestionId,
            existingId,
          );
          return;
        }

        if (!(entry?.action === "create_draft" && entry?.draftTemplate)) {
          return;
        }

        const createdTemplateId = nextId;
        commit("createDraftTemplate", {
          ...entry.draftTemplate,
          ID: createdTemplateId,
        });
        suggestionTemplateMap[suggestionId] = createdTemplateId;
        patchEntryWithTemplate(
          suggestionUpdates,
          suggestionIndexById,
          suggestionId,
          createdTemplateId,
        );
        patchEntryWithTemplate(
          recommendationUpdates,
          recommendationIndexById,
          suggestionId,
          createdTemplateId,
        );
        created += 1;
        nextId += 1;
      });

      commit("setShopSuggestions", suggestionUpdates);
      commit("setShopTemplateRecommendations", recommendationUpdates);
      return {
        created,
        suggestionTemplateMap,
      };
    },
  };
};
