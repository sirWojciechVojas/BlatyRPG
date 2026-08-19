export const createActionGroup3 = (deps) => {
  const {
    clampInteger,
    createDefaultShopProfile,
    expandSuggestionForApply,
    isRecoverableShopApiError,
    loadDemoSuggestionEngine,
    nextTemplateId,
    resolveShopApiConfig,
    shopApiClient,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
    withResolvedSuggestionIcons,
  } = deps;
  return {
    async generateShopSuggestions({ state, commit }, payload = {}) {
      const shopId = Number(payload.shopId ?? state.activeShopId);
      const shop = state.shops.find((entry) => Number(entry.id) === shopId);
      if (!shop) {
        commit("setShopSuggestions", []);
        commit("setShopTemplateRecommendations", []);
        return [];
      }

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.generateSuggestions(
            config,
            shopId,
          );
          const suggestions = withResolvedSuggestionIcons(
            response?.suggestions,
          );
          const recommendations = withResolvedSuggestionIcons(
            response?.recommendations,
          );
          if (suggestions.length) {
            commit("setShopSuggestions", suggestions);
            commit("setShopTemplateRecommendations", recommendations);
            return suggestions;
          }
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            commit("setShopSuggestions", []);
            commit("setShopTemplateRecommendations", []);
            return [];
          }
        }
      }

      const profile =
        state.shopProfiles?.[shopId] || createDefaultShopProfile(shop);
      const startingId = nextTemplateId(state.templateItems);
      const generateShopSuggestionBundle = await loadDemoSuggestionEngine();
      const bundle = generateShopSuggestionBundle({
        templates: state.templateItems,
        catalogNodes: state.catalogNodes,
        profile,
        nextTemplateId: startingId,
      });
      const suggestions = Array.isArray(bundle?.suggestions)
        ? bundle.suggestions
        : [];
      const recommendations = Array.isArray(bundle?.recommendations)
        ? bundle.recommendations
        : [];
      commit("setShopSuggestions", suggestions);
      commit("setShopTemplateRecommendations", recommendations);
      return suggestions;
    },
    async promoteRecommendationsToSuggestions({ state, commit }, payload = {}) {
      const count = clampInteger(payload?.count ?? 30, 1, 120);
      if (count <= 0) {
        return 0;
      }

      if (shouldUseShopApi()) {
        const shopId = Number(payload?.shopId ?? state.activeShopId);
        if (Number.isFinite(shopId)) {
          try {
            const config = resolveShopApiConfig(state);
            const response = await shopApiClient.promoteSuggestions(
              config,
              shopId,
              { count },
            );
            const suggestions = withResolvedSuggestionIcons(
              response?.suggestions,
            );
            commit("setShopSuggestions", suggestions);
            return Number(response?.added || suggestions.length || 0);
          } catch (error) {
            if (
              !shouldAllowShopMockFallback() ||
              !isRecoverableShopApiError(error)
            ) {
              return 0;
            }
          }
        }
      }

      const existingIds = new Set(
        (state.shopSuggestions || []).map((entry) =>
          String(entry?.suggestionId || ""),
        ),
      );
      const promoted = [];
      (state.shopTemplateRecommendations || []).forEach((entry) => {
        if (promoted.length >= count) {
          return;
        }
        const suggestionId = String(entry?.suggestionId || "");
        if (!suggestionId || existingIds.has(suggestionId)) {
          return;
        }
        existingIds.add(suggestionId);
        promoted.push({ ...entry });
      });
      if (!promoted.length) {
        return 0;
      }
      commit("setShopSuggestions", [
        ...(state.shopSuggestions || []),
        ...promoted,
      ]);
      return promoted.length;
    },
    async applyShopSuggestions({ state, commit, dispatch }, payload = {}) {
      const shopId = Number(payload.shopId ?? state.activeShopId);
      if (shouldUseShopApi() && Number.isFinite(shopId)) {
        const sourceSuggestions = Array.isArray(payload.suggestions)
          ? payload.suggestions
          : null;
        const suggestionIds = Array.isArray(payload.suggestionIds)
          ? payload.suggestionIds
          : [];
        const selectedSet = new Set(suggestionIds.map(String));
        const selectedSuggestions = withResolvedSuggestionIcons(
          sourceSuggestions ||
            (selectedSet.size
              ? (state.shopSuggestions || []).filter((entry) =>
                  selectedSet.has(String(entry?.suggestionId || "")),
                )
              : state.shopSuggestions || []),
        );
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.applySuggestions(
            config,
            shopId,
            {
              suggestionIds,
              suggestions: selectedSuggestions,
              replaceExisting: payload.replaceExisting === true,
              ownerCode: config.ownerCode,
            },
          );
          if (response?.containerState) {
            commit("setContainerState", response.containerState);
          }
          if (Array.isArray(response?.cachedSuggestions)) {
            commit(
              "setShopSuggestions",
              withResolvedSuggestionIcons(response.cachedSuggestions),
            );
          }
          if (Array.isArray(response?.recommendations)) {
            commit(
              "setShopTemplateRecommendations",
              withResolvedSuggestionIcons(response.recommendations),
            );
          }
          if (payload.refresh !== false) {
            await dispatch("loadTradingData", {
              campaignId: state.campaignId,
              ownerCode: config.ownerCode,
              forceReload: true,
            });
          }
          commit("setShopEditorState", { selectedSuggestionIds: [] });
          return Number(response?.applied || 0);
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return 0;
          }
        }
      }

      const explicitSuggestions = Array.isArray(payload.suggestions)
        ? payload.suggestions
        : null;
      const sourceSuggestions = explicitSuggestions
        ? explicitSuggestions
        : (() => {
            const suggestionIds = Array.isArray(payload.suggestionIds)
              ? payload.suggestionIds
              : state.shopEditorState.selectedSuggestionIds || [];
            const selectedSet = new Set(suggestionIds);
            return (state.shopSuggestions || []).filter((entry) =>
              selectedSet.size ? selectedSet.has(entry.suggestionId) : true,
            );
          })();
      const suggestions = sourceSuggestions.flatMap((entry) =>
        expandSuggestionForApply(entry, {
          variantId: payload.variantId,
          forceSingle: Boolean(payload.forceSingleVariant),
        }),
      );
      commit("applyShopSuggestions", {
        shopId: payload.shopId ?? state.activeShopId,
        suggestions,
      });
      commit("setShopEditorState", { selectedSuggestionIds: [] });
      return suggestions.length;
    },
  };
};
