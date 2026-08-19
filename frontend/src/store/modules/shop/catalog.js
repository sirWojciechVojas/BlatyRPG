export const createCatalogMutations = ({
  cloneItem,
  normalizeLegacyTemplateRecord,
}) => ({
  setArchivedTemplateItems(state, items = []) {
    state.archivedTemplateItems = Array.isArray(items)
      ? items.map((item) => normalizeLegacyTemplateRecord(cloneItem(item)))
      : [];
  },
  setFormStatus(state, payload = {}) {
    const scope = String(payload.scope || "shop");
    if (!Object.prototype.hasOwnProperty.call(state.formStatus, scope)) {
      return;
    }
    const status = String(payload.status || "clean");
    if (!["clean", "dirty", "saving", "error"].includes(status)) {
      return;
    }
    state.formStatus = { ...state.formStatus, [scope]: status };
  },
  setCatalogNodes(state, nodes) {
    state.catalogNodes = Array.isArray(nodes) ? nodes : [];
  },
  setWorldProfiles(state, profiles) {
    state.worldProfiles = Array.isArray(profiles) ? profiles : [];
  },
  setItemDictionaries(state, dictionaries = {}) {
    state.itemDictionaries = {
      icon_categories: Array.isArray(dictionaries.icon_categories)
        ? dictionaries.icon_categories
        : [],
      icon_subcategories: Array.isArray(dictionaries.icon_subcategories)
        ? dictionaries.icon_subcategories
        : [],
      classes: Array.isArray(dictionaries.classes) ? dictionaries.classes : [],
      genres: Array.isArray(dictionaries.genres) ? dictionaries.genres : [],
      attributes: Array.isArray(dictionaries.attributes)
        ? dictionaries.attributes
        : [],
    };
  },
});
