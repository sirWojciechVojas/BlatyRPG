export const createContentMethodsPart1 = (runtime) => ({
  cloneForm(value) {
    return JSON.parse(JSON.stringify(value || {}));
  },
  syncLocalForm(target, value) {
    this.syncingForms = true;
    this[target] = this.cloneForm(value);
    this.$nextTick(() => {
      this.syncingForms = false;
    });
  },
  toNumberOrNull(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  },
  parseImgClassNumber(value) {
    const match = String(value || "")
      .toLowerCase()
      .match(/^v(\d{4})$/);
    if (!match) {
      return Number.NaN;
    }
    return Number(match[1]);
  },
  normalizeIconClassDraft(value) {
    const raw = String(value || "")
      .trim()
      .toLowerCase();
    if (!raw) {
      return "";
    }
    const numeric = raw.replace(/^v/u, "");
    if (!/^\d{1,4}$/u.test(numeric)) {
      return "";
    }
    const parsed = Number(numeric);
    if (
      !Number.isFinite(parsed) ||
      parsed < 1 ||
      parsed > runtime.INVENTORY_ICON_MAX
    ) {
      return "";
    }
    return `v${String(parsed).padStart(4, "0")}`;
  },
  isDefaultIconClass(iconClass) {
    return Boolean(runtime.inventoryIconMetadataMap?.[iconClass]);
  },
  isIconClassVisible(iconClass) {
    const normalized = this.normalizeIconClassDraft(iconClass);
    return Boolean(
      normalized && this.normalizedImgClassOptions.includes(normalized),
    );
  },
  sourceIconClassForDisplay(iconClass) {
    const normalized = this.normalizeIconClassDraft(iconClass);
    if (!normalized) {
      return "";
    }
    return (
      this.normalizeIconClassDraft(
        this.iconCollectionChanges?.remap?.[normalized],
      ) || normalized
    );
  },
  metadataIconClassForDisplay(iconClass) {
    return this.sourceIconClassForDisplay(iconClass) || iconClass;
  },
  sanitizeIconCollectionChanges(value = {}) {
    const added = {};
    Object.keys(value?.added || {}).forEach((iconClass) => {
      const normalized = this.normalizeIconClassDraft(iconClass);
      if (normalized) {
        added[normalized] = true;
      }
    });
    const remap = {};
    Object.entries(value?.remap || {}).forEach(
      ([displayClass, sourceClass]) => {
        const normalizedDisplay = this.normalizeIconClassDraft(displayClass);
        const normalizedSource = this.normalizeIconClassDraft(sourceClass);
        if (normalizedDisplay && normalizedSource) {
          remap[normalizedDisplay] = normalizedSource;
        }
      },
    );
    const removed = Array.from(
      new Set(
        (value?.removed || [])
          .map((iconClass) => this.normalizeIconClassDraft(iconClass))
          .filter(Boolean),
      ),
    );
    return {
      added,
      removed,
      remap,
    };
  },
  updateIconCollectionChanges(patch = {}) {
    this.iconCollectionChanges = this.sanitizeIconCollectionChanges({
      added: {
        ...(this.iconCollectionChanges?.added || {}),
        ...(patch.added || {}),
      },
      remap: patch.remap || this.iconCollectionChanges?.remap || {},
      removed: patch.removed || this.iconCollectionChanges?.removed || [],
    });
    this.persistIconCollectionChanges();
  },
  normalizeTradeTypeToken(value) {
    const raw = String(value || "")
      .trim()
      .toUpperCase();
    if (!raw) {
      return "";
    }
    const alias = runtime.TRADE_TYPE_ALIASES[raw] || raw;
    return this.iconSubtypeToTypeMap?.[alias] || alias;
  },
  tradeTypeCacheKeyForItem(item) {
    return [
      this.iconMetadataCacheStamp,
      item?.ID,
      item?.INV_ID,
      item?.ITEM_CLASS,
      item?.itemClass,
      item?.classKey,
      item?.ITEM_GENRE,
      item?.genreKey,
      item?.IMG_CLASS,
      item?.iconClass,
    ]
      .map((entry) => String(entry ?? ""))
      .join("|");
  },
  itemTypeKeys(item) {
    if (!item || typeof item !== "object") {
      return ["MISC"];
    }
    const cacheKey = this.tradeTypeCacheKeyForItem(item);
    const cached = this.tradeItemTypeKeyCache.get(cacheKey);
    if (cached) {
      return cached;
    }
    const keys = new Set();
    const addTokens = (value) => {
      String(value || "")
        .split(/[;,|/]/)
        .map((entry) => this.normalizeTradeTypeToken(entry))
        .filter(Boolean)
        .forEach((entry) => keys.add(entry));
    };
    addTokens(item.ITEM_CLASS);
    addTokens(item.itemClass);
    addTokens(item.classKey);
    addTokens(item.ITEM_GENRE);
    addTokens(item.genreKey);
    const iconClass = String(
      this.legacyIconClassForItem(item) || "v0001",
    ).toLowerCase();
    const rawMetadata = this.iconMetadataMap?.[iconClass];
    if (rawMetadata) {
      const metadata = this.getIconMetadata(iconClass);
      (metadata.typeKeys || [])
        .map((entry) => String(entry || "").toUpperCase())
        .filter(Boolean)
        .forEach((entry) => keys.add(entry));
    }
    if (!keys.size) {
      keys.add("MISC");
    }
    const normalizedKeys = Array.from(keys);
    if (this.tradeItemTypeKeyCache.size > 3000) {
      this.tradeItemTypeKeyCache.clear();
    }
    this.tradeItemTypeKeyCache.set(cacheKey, normalizedKeys);
    return normalizedKeys;
  },
  itemClassKeys(item) {
    if (!item || typeof item !== "object") {
      return ["MISC"];
    }
    const classes = new Set();
    [item.ITEM_CLASS, item.itemClass, item.classKey].forEach((value) => {
      String(value || "")
        .split(/[;,|/]/)
        .map((entry) =>
          String(entry || "")
            .trim()
            .toUpperCase(),
        )
        .filter(Boolean)
        .forEach((entry) => classes.add(entry));
    });
    return classes.size ? Array.from(classes) : ["MISC"];
  },
  itemTypeSearchText(item) {
    const keys = this.itemTypeKeys(item);
    const labels = keys.flatMap((key) => [
      this.labelForType(key, "pl"),
      this.labelForSubtype(key, "pl"),
    ]);
    return [
      item?.ITEM_CLASS,
      item?.itemClass,
      item?.classKey,
      item?.ITEM_GENRE,
      item?.genreKey,
      ...keys,
      ...labels,
    ]
      .map((entry) => String(entry || "").trim())
      .filter(Boolean)
      .join(" ")
      .toLowerCase();
  },
  emptyTradeTypeModel() {
    return {
      options: [
        {
          value: "all",
          label: "Wszystkie",
          iconClass: "v0001",
          count: 0,
        },
      ],
      filteredItems: [],
    };
  },
  buildTradeTypeFilterOptions(items = []) {
    return this.buildTradeTypeModel(items, "all").options;
  },
});
