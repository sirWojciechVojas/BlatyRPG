export const createContentRuntimePart1 = (runtime) => {
  const ICON_METADATA_STORAGE_KEY = "trade-icon-metadata-v1";
  Object.assign(runtime, {
    ICON_METADATA_STORAGE_KEY,
  });
  const ICON_COLLECTION_STORAGE_KEY = "trade-icon-collection-v1";
  Object.assign(runtime, {
    ICON_COLLECTION_STORAGE_KEY,
  });
  const ICON_CLASS_PATTERN = /^v\d{4}$/;
  Object.assign(runtime, {
    ICON_CLASS_PATTERN,
  });
  const INVENTORY_ICON_MAX = 1375;
  Object.assign(runtime, {
    INVENTORY_ICON_MAX,
  });
  const ICON_THEME_CATALOG = Array.isArray(runtime.iconTaxonomy?.types)
    ? runtime.iconTaxonomy.types
    : [];
  Object.assign(runtime, {
    ICON_THEME_CATALOG,
  });
  const TRADE_TYPE_ALIASES = {
    TOOL: "GADGET",
    ALCHEMY: "POTION",
    MAGIC: "MISC",
  };
  Object.assign(runtime, {
    TRADE_TYPE_ALIASES,
  });
  return {
    ICON_METADATA_STORAGE_KEY,
    ICON_COLLECTION_STORAGE_KEY,
    ICON_CLASS_PATTERN,
    INVENTORY_ICON_MAX,
    ICON_THEME_CATALOG,
    TRADE_TYPE_ALIASES,
  };
};
