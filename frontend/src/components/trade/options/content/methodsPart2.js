export const createContentMethodsPart2 = (runtime) => ({
  buildTradeTypeModel(items = [], typeFilter = "all") {
    const list = Array.isArray(items) ? items : [];
    const byType = new Map();
    const normalizedFilter = String(typeFilter || "all").toUpperCase();
    const includeAll = normalizedFilter === "ALL";
    const filteredItems = [];
    list.forEach((item) => {
      const iconClass = String(
        this.legacyIconClassForItem(item) || "v0001",
      ).toLowerCase();
      const itemTypes = this.itemClassKeys(item);
      if (includeAll || itemTypes.includes(normalizedFilter)) {
        filteredItems.push(item);
      }
      const uniqueTypes = new Set(itemTypes);
      uniqueTypes.forEach((typeKey) => {
        const normalized = String(typeKey || "").toUpperCase();
        if (!normalized) {
          return;
        }
        if (!byType.has(normalized)) {
          const labelKey = `modals.fieldEdit.fields.itemClass.options.${normalized}.label`;
          const localizedLabel = this.$t(labelKey);
          const baseLabel =
            localizedLabel === labelKey ? normalized : localizedLabel;
          byType.set(normalized, {
            value: normalized,
            label:
              baseLabel === normalized
                ? normalized.replace(/_/g, " ")
                : baseLabel,
            iconClass,
            count: 0,
          });
        }
        const current = byType.get(normalized);
        current.count += 1;
        if (
          (!current.iconClass || current.iconClass === "v0001") &&
          iconClass
        ) {
          current.iconClass = iconClass;
        }
      });
    });
    const options = Array.from(byType.values()).sort((left, right) =>
      left.label.localeCompare(right.label, "pl"),
    );
    return {
      options: [
        {
          value: "all",
          label: "Wszystkie",
          iconClass: "v0001",
          count: list.length,
        },
        ...options,
      ],
      filteredItems,
    };
  },
  filterTradeItemsByType(items = [], typeFilter = "all") {
    return this.buildTradeTypeModel(items, typeFilter).filteredItems;
  },
  filterTradeItemsByTypeAndSearch(items = [], typeFilter = "all", search = "") {
    const filteredByType = this.filterTradeItemsByType(items, typeFilter);
    const tokens = String(search || "")
      .trim()
      .toLowerCase()
      .split(/\s+/)
      .filter(Boolean);
    if (!tokens.length) {
      return filteredByType;
    }
    return filteredByType.filter((item) => {
      const haystack = this.itemTypeSearchText(item);
      return tokens.every((token) => haystack.includes(token));
    });
  },
  setTradeTypeFilter(side, value) {
    const normalized = String(value || "all");
    if (side === "sell") {
      this.sellTypeFilter =
        this.sellTypeFilter === normalized ? "all" : normalized;
      return;
    }
    this.buyTypeFilter = this.buyTypeFilter === normalized ? "all" : normalized;
  },
  setTradeSearch(side, value) {
    if (side === "sell") {
      this.sellTypeSearch = String(value || "");
      return;
    }
    this.buyTypeSearch = String(value || "");
  },
  setTradeSort(side, value) {
    const normalized = ["name", "price", "availability"].includes(value)
      ? value
      : "name";
    if (side === "sell") {
      this.sellSortMode = normalized;
      return;
    }
    this.buySortMode = normalized;
  },
  sortTradeItems(items = [], mode = "name") {
    return [...items].sort((left, right) => {
      if (mode === "price") {
        return (
          Number(left?.ACTIVE_PRICE ?? left?.PRIZE ?? 0) -
          Number(right?.ACTIVE_PRICE ?? right?.PRIZE ?? 0)
        );
      }
      if (mode === "availability") {
        return (
          Number(right?.QUANTITY ?? Number.MAX_SAFE_INTEGER) -
          Number(left?.QUANTITY ?? Number.MAX_SAFE_INTEGER)
        );
      }
      return String(left?.NAME || left?.PERSONAL_PSEU || "").localeCompare(
        String(right?.NAME || right?.PERSONAL_PSEU || ""),
        this.$i18n?.locale || "pl",
      );
    });
  },
  subtypeEntriesForType(typeKey) {
    const normalizedType = String(typeKey || "").toUpperCase();
    const typeEntry = runtime.ICON_THEME_CATALOG.find(
      (entry) => String(entry.key || "").toUpperCase() === normalizedType,
    );
    return Array.isArray(typeEntry?.subtypes) ? typeEntry.subtypes : [];
  },
  allSubtypeKeys() {
    const keys = new Set();
    runtime.ICON_THEME_CATALOG.forEach((entry) => {
      (entry.subtypes || []).forEach((subtype) =>
        keys.add(String(subtype.key || "").toUpperCase()),
      );
    });
    return Array.from(keys).sort();
  },
  labelForType(typeKey, lang = "pl") {
    const normalizedType = String(typeKey || "").toUpperCase();
    const typeEntry = runtime.ICON_THEME_CATALOG.find(
      (entry) => String(entry.key || "").toUpperCase() === normalizedType,
    );
    if (!typeEntry) {
      return normalizedType;
    }
    return typeEntry.label?.[lang] || typeEntry.label?.en || normalizedType;
  },
  labelForSubtype(subtypeKey, lang = "pl") {
    const normalizedSubtype = String(subtypeKey || "").toUpperCase();
    for (const typeEntry of runtime.ICON_THEME_CATALOG) {
      const found = (typeEntry.subtypes || []).find(
        (entry) => String(entry.key || "").toUpperCase() === normalizedSubtype,
      );
      if (found) {
        return found.label?.[lang] || found.label?.en || normalizedSubtype;
      }
    }
    return normalizedSubtype;
  },
  normalizeTypeKeys(value) {
    const list = Array.isArray(value)
      ? value
      : String(value || "")
          .split(",")
          .map((entry) => entry.trim());
    const allowed = new Set(
      runtime.ICON_THEME_CATALOG.map((entry) =>
        String(entry.key || "").toUpperCase(),
      ),
    );
    return Array.from(
      new Set(
        list
          .map((entry) => String(entry || "").toUpperCase())
          .filter((entry) => allowed.has(entry)),
      ),
    );
  },
  normalizeSubtypeKeys(value, typeKeys = []) {
    const list = Array.isArray(value)
      ? value
      : String(value || "")
          .split(",")
          .map((entry) => entry.trim());
    let allowed = [];
    if (Array.isArray(typeKeys) && typeKeys.length) {
      const allowedSet = new Set();
      typeKeys.forEach((typeKey) => {
        this.subtypeEntriesForType(typeKey).forEach((entry) =>
          allowedSet.add(String(entry.key || "").toUpperCase()),
        );
      });
      allowed = Array.from(allowedSet);
    } else {
      allowed = this.allSubtypeKeys();
    }
    const allowedSet = new Set(allowed);
    return Array.from(
      new Set(
        list
          .map((entry) => String(entry || "").toUpperCase())
          .filter((entry) => allowedSet.has(entry)),
      ),
    );
  },
  buildAutoTags(metadata) {
    const tagSet = new Set();
    (metadata.typeKeys || []).forEach((key) => {
      tagSet.add(key);
      tagSet.add(this.labelForType(key, "pl"));
    });
    (metadata.subtypeKeys || []).forEach((key) => {
      tagSet.add(key);
      tagSet.add(this.labelForSubtype(key, "pl"));
    });
    String(metadata.specialMarks || "")
      .split(",")
      .map((entry) => entry.trim())
      .filter(Boolean)
      .forEach((entry) => tagSet.add(entry));
    return Array.from(tagSet).filter(Boolean).join(", ");
  },
  buildTranslations(keys, kind) {
    const isType = kind === "type";
    return {
      pl: keys.map((key) =>
        isType ? this.labelForType(key, "pl") : this.labelForSubtype(key, "pl"),
      ),
      en: keys.map((key) =>
        isType ? this.labelForType(key, "en") : this.labelForSubtype(key, "en"),
      ),
    };
  },
});
