export const createActionGroup6 = (deps) => {
  const {
    clampInteger,
    cloneItem,
    isRecoverableShopApiError,
    resolveShopApiConfig,
    shopApiClient,
    shouldAllowShopMockFallback,
    shouldUseShopApi,
    weightedPickWithoutReplacement,
    withResolvedSuggestionIcons,
  } = deps;
  return {
    async rollShopAssortment({ state, commit, dispatch }, payload = {}) {
      const shopId = Number(payload.shopId ?? state.activeShopId);
      if (!Number.isFinite(shopId)) {
        return { appliedUnique: 0, appliedInstances: 0, suggestions: [] };
      }

      if (shouldUseShopApi()) {
        try {
          const config = resolveShopApiConfig(state);
          const response = await shopApiClient.rollAssortment(config, shopId, {
            targetInstances: payload.targetInstances,
            dryRun: payload.dryRun === true,
            clearExisting: payload.clearExisting !== false,
            uniqueItems: payload.uniqueItems,
            ownerCode: config.ownerCode,
          });
          if (payload.dryRun === true) {
            return {
              appliedUnique: Number(response?.appliedUnique || 0),
              appliedInstances: Number(response?.appliedInstances || 0),
              suggestions: withResolvedSuggestionIcons(response?.suggestions),
            };
          }
          if (response?.containerState) {
            commit("setContainerState", response.containerState);
          }
          if (payload.clearExisting !== false) {
            commit("setShopAssortment", { shopId, shopEntries: [] });
          }
          const resolvedSuggestions = withResolvedSuggestionIcons(
            response?.suggestions,
          );
          commit("applyShopSuggestions", {
            shopId,
            suggestions: resolvedSuggestions,
          });
          return {
            appliedUnique: Number(response?.appliedUnique || 0),
            appliedInstances: Number(response?.appliedInstances || 0),
            suggestions: resolvedSuggestions,
          };
        } catch (error) {
          if (
            !shouldAllowShopMockFallback() ||
            !isRecoverableShopApiError(error)
          ) {
            return { appliedUnique: 0, appliedInstances: 0, suggestions: [] };
          }
        }
      }

      const targetInstances = clampInteger(payload.targetInstances, 8, 20);
      const clearExisting = payload.clearExisting !== false;
      const dryRun = payload.dryRun === true;
      const suggestionsRaw = await dispatch("generateShopSuggestions", {
        shopId,
      });
      const suggestions = Array.isArray(suggestionsRaw) ? suggestionsRaw : [];
      if (!suggestions.length) {
        return { appliedUnique: 0, appliedInstances: 0, suggestions: [] };
      }

      const minUnique = Math.max(4, Math.floor(targetInstances * 0.5));
      const maxUnique = Math.max(minUnique, Math.floor(targetInstances * 0.85));
      const uniqueTarget = clampInteger(
        payload.uniqueItems ??
          minUnique + Math.floor(Math.random() * (maxUnique - minUnique + 1)),
        minUnique,
        Math.min(maxUnique, suggestions.length),
      );

      const profile = state.shopProfiles?.[shopId];
      const wealthBoostMap = {
        nedzny: -2,
        biedny: -1,
        standard: 0,
        bogaty: 1,
        elitarny: 2,
        luksusowy: 3,
      };
      const wealthBoost = Number(wealthBoostMap[profile?.wealthTier] ?? 0);
      const reputationBoostMap = {
        fatalna: -1,
        zla: -1,
        podejrzana: 0,
        neutralna: 0,
        dobra: 1,
        znakomita: 2,
      };
      const reputationBoost = Number(
        reputationBoostMap[profile?.reputation] ?? 0,
      );
      const seasonalBoostMap = {
        caloroczny: 0,
        sezonowy: 1,
        wiosna: 1,
        lato: 1,
        jesien: 1,
        zima: 1,
        zniwa: 2,
        jarmark: 2,
        swieta: 2,
      };
      const seasonalBoost = Number(seasonalBoostMap[profile?.seasonality] ?? 0);

      const picked = weightedPickWithoutReplacement(suggestions, uniqueTarget);
      const randomized = picked.map((entry) => {
        const baseQty = Math.max(1, Number(entry?.quantity || 1));
        const spread = Math.max(
          1,
          1 + Math.max(0, wealthBoost + reputationBoost + seasonalBoost),
        );
        const rolled = baseQty + Math.floor(Math.random() * spread);
        const quantity = Math.max(
          1,
          rolled + Math.min(0, wealthBoost + reputationBoost),
        );
        return {
          ...entry,
          quantity,
        };
      });

      let totalInstances = randomized.reduce(
        (acc, entry) => acc + Math.max(1, Number(entry?.quantity || 1)),
        0,
      );
      while (randomized.length && totalInstances < targetInstances) {
        const index = Math.floor(Math.random() * randomized.length);
        randomized[index] = {
          ...randomized[index],
          quantity: Math.max(1, Number(randomized[index].quantity || 1)) + 1,
        };
        totalInstances += 1;
      }
      while (randomized.length && totalInstances > targetInstances) {
        const mutable = randomized
          .map((entry, index) => ({
            index,
            qty: Math.max(1, Number(entry?.quantity || 1)),
          }))
          .filter((entry) => entry.qty > 1);
        if (!mutable.length) {
          break;
        }
        const selected = mutable[Math.floor(Math.random() * mutable.length)];
        randomized[selected.index] = {
          ...randomized[selected.index],
          quantity: selected.qty - 1,
        };
        totalInstances -= 1;
      }

      const payloadSuggestions = randomized.map((entry) => cloneItem(entry));
      if (dryRun) {
        return {
          appliedUnique: payloadSuggestions.length,
          appliedInstances: payloadSuggestions.reduce(
            (acc, entry) => acc + Math.max(1, Number(entry?.quantity || 1)),
            0,
          ),
          suggestions: payloadSuggestions,
        };
      }

      if (clearExisting) {
        commit("setShopAssortment", {
          shopId,
          shopEntries: [],
        });
      }
      await dispatch("applyShopSuggestions", {
        shopId,
        suggestions: payloadSuggestions,
      });
      commit("setShopEditorState", { selectedSuggestionIds: [] });
      return {
        appliedUnique: payloadSuggestions.length,
        appliedInstances: payloadSuggestions.reduce(
          (acc, entry) => acc + Math.max(1, Number(entry?.quantity || 1)),
          0,
        ),
        suggestions: payloadSuggestions,
      };
    },
  };
};
