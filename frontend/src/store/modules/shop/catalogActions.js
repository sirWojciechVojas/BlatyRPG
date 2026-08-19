export const createCatalogActions = ({
  resolveShopApiConfig,
  shopApiClient,
  shouldUseShopApi,
}) => ({
  async saveItemDictionaryEntry({ state, commit }, payload = {}) {
    if (!shouldUseShopApi()) return null;
    try {
      const entryId = Number(payload.id);
      const response =
        Number.isFinite(entryId) && entryId > 0
          ? await shopApiClient.updateItemDictionaryEntry(
              resolveShopApiConfig(state),
              entryId,
              payload,
            )
          : await shopApiClient.createItemDictionaryEntry(
              resolveShopApiConfig(state),
              payload,
            );
      if (response?.dictionaries) {
        commit("setItemDictionaries", response.dictionaries);
      }
      return response?.entry || null;
    } catch (error) {
      return null;
    }
  },
  async archiveItemDictionaryEntry({ state, commit }, entryIdRaw) {
    const entryId = Number(entryIdRaw);
    if (!Number.isFinite(entryId) || !shouldUseShopApi()) return false;
    try {
      const response = await shopApiClient.deleteItemDictionaryEntry(
        resolveShopApiConfig(state),
        entryId,
      );
      if (response?.dictionaries) {
        commit("setItemDictionaries", response.dictionaries);
      }
      return response?.ok === true;
    } catch (error) {
      return false;
    }
  },
  async loadArchivedTemplates({ state, commit }, filters = {}) {
    if (!shouldUseShopApi()) {
      commit("setArchivedTemplateItems", []);
      return [];
    }
    const config = resolveShopApiConfig(state);
    try {
      const response = await shopApiClient.listTemplates(config, {
        ...filters,
        status: "archived",
      });
      const items = Array.isArray(response?.items) ? response.items : [];
      commit("setArchivedTemplateItems", items);
      return items;
    } catch (error) {
      return [];
    }
  },
  async restoreArchivedTemplate({ state, commit }, templateIdRaw) {
    const templateId = Number(templateIdRaw);
    if (!Number.isFinite(templateId) || !shouldUseShopApi()) {
      return null;
    }
    try {
      const response = await shopApiClient.restoreTemplate(
        resolveShopApiConfig(state),
        templateId,
      );
      const restored = response?.template || null;
      if (restored) {
        commit("addTemplateItem", restored);
        commit(
          "setArchivedTemplateItems",
          state.archivedTemplateItems.filter(
            (entry) => Number(entry.ID) !== templateId,
          ),
        );
      }
      return restored;
    } catch (error) {
      return null;
    }
  },
  async duplicateTemplateRecord({ state, commit }, payload = {}) {
    const templateId = Number(payload.templateId ?? payload.ID);
    if (!Number.isFinite(templateId) || !shouldUseShopApi()) {
      return null;
    }
    try {
      const response = await shopApiClient.duplicateTemplate(
        resolveShopApiConfig(state),
        templateId,
        { name: payload.name },
      );
      const created = response?.template || null;
      if (created) {
        commit("addTemplateItem", created);
      }
      return created;
    } catch (error) {
      return null;
    }
  },
});
