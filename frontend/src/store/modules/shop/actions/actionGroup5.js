export const createActionGroup5 = (deps) => {
  const {
    findSuggestionInCollections,
    isRecoverableShopApiError,
    resolveShopApiConfig,
    shopApiClient,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
  } = deps;
  return {
    async materializeShopSuggestion({ state, commit, dispatch }, payload = {}) {
      const suggestionId = String(payload?.suggestionId || "");
      const mode = String(payload?.mode || "").trim();
      if (!suggestionId || !mode) {
        return { created: 0, applied: 0, suggestionTemplateMap: {} };
      }
      const shopId = Number(payload?.shopId ?? state.activeShopId);

      if (shouldUseShopApi() && Number.isFinite(shopId)) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.materializeSuggestion(
            config,
            shopId,
            {
              suggestionId,
              mode,
              variantId: String(payload?.variantId || ""),
              ownerCode: config.ownerCode,
            },
          );
          if (response?.containerState) {
            // The endpoint returns the changed container snapshot, so callers
            // do not need to wait for a full bootstrap before continuing.
            commit("setContainerState", response.containerState);
          }
          if (Array.isArray(response?.cachedSuggestions)) {
            commit("setShopSuggestions", response.cachedSuggestions);
          }
          if (Array.isArray(response?.recommendations)) {
            commit("setShopTemplateRecommendations", response.recommendations);
          }
          if (payload?.refresh !== false) {
            await dispatch("loadTradingData", {
              forceReload: true,
              ownerCode: config.ownerCode,
            });
          }
          return {
            created: Number(response?.created || 0),
            applied: Number(response?.applied || 0),
            suggestionTemplateMap: response?.suggestionTemplateMap || {},
          };
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return { created: 0, applied: 0, suggestionTemplateMap: {} };
          }
        }
      }

      const baseSuggestion = findSuggestionInCollections(state, suggestionId);
      if (!baseSuggestion) {
        return { created: 0, applied: 0, suggestionTemplateMap: {} };
      }

      let selectedSuggestion = { ...baseSuggestion };
      let createdResult = { created: 0, suggestionTemplateMap: {} };
      let resolvedTemplateId = Number(selectedSuggestion?.templateId);

      const needsTemplate =
        mode === "template_only" || mode === "template_plus_item";
      if (
        needsTemplate &&
        selectedSuggestion?.action === "create_draft" &&
        !Number.isFinite(resolvedTemplateId)
      ) {
        createdResult = await dispatch("createTemplatesFromSuggestions", {
          suggestionIds: [suggestionId],
        });
        const mappedId = Number(
          createdResult?.suggestionTemplateMap?.[suggestionId],
        );
        if (Number.isFinite(mappedId)) {
          resolvedTemplateId = mappedId;
        }
        const refreshedSuggestion = findSuggestionInCollections(
          state,
          suggestionId,
        );
        if (refreshedSuggestion) {
          selectedSuggestion = { ...refreshedSuggestion };
        }
      }

      if (Number.isFinite(resolvedTemplateId)) {
        selectedSuggestion.templateId = resolvedTemplateId;
      }
      if (mode === "template_only") {
        return {
          created: Number(createdResult?.created || 0),
          applied: 0,
          suggestionTemplateMap: createdResult?.suggestionTemplateMap || {},
        };
      }
      if (mode === "item_only" && !Number.isFinite(resolvedTemplateId)) {
        return {
          created: Number(createdResult?.created || 0),
          applied: 0,
          suggestionTemplateMap: createdResult?.suggestionTemplateMap || {},
        };
      }

      const applied = await dispatch("applyShopSuggestions", {
        shopId,
        suggestions: [
          {
            ...selectedSuggestion,
            quantity: 1,
            forceVariantId: payload?.variantId || "",
            forceSingleVariant: true,
          },
        ],
      });
      return {
        created: Number(createdResult?.created || 0),
        applied: Number(applied || 0),
        suggestionTemplateMap: createdResult?.suggestionTemplateMap || {},
      };
    },
  };
};
