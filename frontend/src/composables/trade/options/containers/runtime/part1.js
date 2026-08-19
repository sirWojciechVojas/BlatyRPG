export const createContainersRuntimePart1 = (runtime) => {
  const TRASH_OWNER_GENERAL = runtime.OWNER_CODES.TRASH;
  Object.assign(runtime, {
    TRASH_OWNER_GENERAL,
  });
  const PLAYER_TRASH_SLOT_CAPACITY = 16;
  Object.assign(runtime, {
    PLAYER_TRASH_SLOT_CAPACITY,
  });
  const GENERAL_TRASH_SLOT_CAPACITY = null;
  Object.assign(runtime, {
    GENERAL_TRASH_SLOT_CAPACITY,
  });
  const t = (key, values = {}) => runtime.i18n.global.t(key, values);
  Object.assign(runtime, {
    t,
  });
  const itemLabel = (item) =>
    String(item?.label || item?.name || item?.NAME || "przedmiot")
      .trim()
      .replace(/\s+/g, " ");
  Object.assign(runtime, {
    itemLabel,
  });
  const notifyContainerInfo = ({
    zone,
    type = "info",
    title,
    message,
    details,
  }) => {
    runtime.notifyShop({
      zone,
      type,
      title,
      message,
      details,
    });
  };
  Object.assign(runtime, {
    notifyContainerInfo,
  });
  const resolveItemPlace = (entry, fallback = "STOISKO") =>
    String(entry?.ITEM_PLACE ?? entry?.SLOT ?? fallback);
  Object.assign(runtime, {
    resolveItemPlace,
  });
  const resolveTemplateIdFromEntry = (entry) => {
    if (typeof entry === "number" || typeof entry === "string") {
      const id = Number(entry);
      return Number.isFinite(id) ? id : null;
    }
    if (!entry || typeof entry !== "object") {
      return null;
    }
    const id = Number(entry.INV_ID ?? entry.ID);
    return Number.isFinite(id) ? id : null;
  };
  Object.assign(runtime, {
    resolveTemplateIdFromEntry,
  });
  const shopEntriesForStore = (shop) => {
    if (Array.isArray(shop?.shopEntries)) {
      return shop.shopEntries;
    }
    if (Array.isArray(shop?.items)) {
      return shop.items;
    }
    if (Array.isArray(shop?.itemIds)) {
      return shop.itemIds;
    }
    return [];
  };
  Object.assign(runtime, {
    shopEntriesForStore,
  });
  const toNumberOrFallback = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };
  Object.assign(runtime, {
    toNumberOrFallback,
  });
  const toRoundedQuantity = (value, fallback = 1) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
      return Math.max(1, Math.round(fallback));
    }
    return Math.max(1, Math.round(parsed));
  };
  Object.assign(runtime, {
    toRoundedQuantity,
  });
  const normalizeShopEntryForContainer = (entry, template = {}) => {
    const templateId = runtime.resolveTemplateIdFromEntry(entry);
    if (!Number.isFinite(templateId)) {
      return null;
    }
    const personalDesc = String(
      entry?.PERSONAL_DESC ?? template?.DESCRIPTION ?? "",
    );
    const personalCost = Math.max(
      0,
      runtime.toNumberOrFallback(
        entry?.PERSONAL_COST,
        Number(template?.PRIZE || 0),
      ),
    );
    return {
      INV_ID: templateId,
      ITEM_PLACE: runtime.resolveItemPlace(entry, "STOISKO"),
      SLOT: runtime.resolveItemPlace(entry, "STOISKO"),
      PERSONAL_PSEU: String(
        entry?.PERSONAL_PSEU ||
          entry?.NAME ||
          template?.NAME ||
          `Przedmiot ${templateId}`,
      ),
      PERSONAL_DESC: personalDesc,
      PERSONAL_COST: personalCost,
      QUANTITY: runtime.toRoundedQuantity(entry?.QUANTITY, 1),
      OWNER_OPT: String(entry?.OWNER_OPT || "DEFAULT"),
      OWNER: String(entry?.OWNER || "BG1"),
      NAME: String(entry?.NAME || template?.NAME || `Przedmiot ${templateId}`),
      DESCRIPTION: String(entry?.DESCRIPTION || personalDesc || ""),
      IMG_CLASS: String(entry?.IMG_CLASS || template?.IMG_CLASS || "v0001"),
      PRIZE: Math.max(
        0,
        runtime.toNumberOrFallback(
          entry?.PRIZE,
          Number(template?.PRIZE || personalCost),
        ),
      ),
      CHARGE: Math.max(
        0,
        runtime.toNumberOrFallback(
          entry?.CHARGE,
          Number(template?.CHARGE || 0),
        ),
      ),
    };
  };
  Object.assign(runtime, {
    normalizeShopEntryForContainer,
  });
  const stackSimilarityKey = (entry = {}) => {
    const templateId = runtime.toNumberOrFallback(entry?.INV_ID, NaN);
    if (!Number.isFinite(templateId)) {
      return "";
    }
    return [
      templateId,
      runtime.resolveItemPlace(entry, "").trim().toUpperCase(),
      String(entry?.PERSONAL_PSEU || "")
        .trim()
        .toLowerCase(),
      String(entry?.PERSONAL_DESC || "")
        .trim()
        .toLowerCase(),
      runtime.toNumberOrFallback(entry?.PERSONAL_COST, 0),
      String(entry?.IMG_CLASS || "")
        .trim()
        .toLowerCase(),
    ].join("|");
  };
  Object.assign(runtime, {
    stackSimilarityKey,
  });
  const stackCandidateGroupKey = (entry = {}) => {
    const templateId = runtime.toNumberOrFallback(entry?.INV_ID, NaN);
    if (!Number.isFinite(templateId)) {
      return "";
    }
    return `tpl:${templateId}`;
  };
  Object.assign(runtime, {
    stackCandidateGroupKey,
  });
  const ASSORTMENT_MERGE_FIELDS = [
    "NAME",
    "ITEM_PLACE",
    "PERSONAL_PSEU",
    "PERSONAL_DESC",
    "PERSONAL_COST",
    "DESCRIPTION",
    "IMG_CLASS",
    "PRIZE",
    "CHARGE",
  ];
  Object.assign(runtime, {
    ASSORTMENT_MERGE_FIELDS,
  });
  const normalizeMergeValue = (value, fallback = "") => {
    if (value === undefined || value === null) {
      return fallback;
    }
    return value;
  };
  Object.assign(runtime, {
    normalizeMergeValue,
  });
  const aggregateShopEntries = (entries = [], templatesMap = {}) => {
    const grouped = new Map();
    entries.forEach((entry) => {
      const templateId = runtime.resolveTemplateIdFromEntry(entry);
      if (!Number.isFinite(templateId)) {
        return;
      }
      const template = templatesMap[Number(templateId)] || {};
      const normalized = runtime.normalizeShopEntryForContainer(
        entry,
        template,
      );
      if (!normalized) {
        return;
      }
      const key = runtime.stackSimilarityKey(normalized);
      if (!key) {
        return;
      }
      if (!grouped.has(key)) {
        grouped.set(key, {
          ...normalized,
        });
        return;
      }
      const current = grouped.get(key);
      grouped.set(key, {
        ...current,
        QUANTITY:
          runtime.toRoundedQuantity(current.QUANTITY, 1) + normalized.QUANTITY,
      });
    });
    return Array.from(grouped.values()).map((entry) => ({
      ...entry,
      QUANTITY: runtime.toRoundedQuantity(entry.QUANTITY, 1),
    }));
  };
  Object.assign(runtime, {
    aggregateShopEntries,
  });
  return {
    TRASH_OWNER_GENERAL,
    PLAYER_TRASH_SLOT_CAPACITY,
    GENERAL_TRASH_SLOT_CAPACITY,
    t,
    itemLabel,
    notifyContainerInfo,
    resolveItemPlace,
    resolveTemplateIdFromEntry,
    shopEntriesForStore,
    toNumberOrFallback,
    toRoundedQuantity,
    normalizeShopEntryForContainer,
    stackSimilarityKey,
    stackCandidateGroupKey,
    ASSORTMENT_MERGE_FIELDS,
    normalizeMergeValue,
    aggregateShopEntries,
  };
};
