export const createContentComputedPart2 = (runtime) => ({
  viewSettingsIconSizeModel: {
    get() {
      return this.normalizeIconSizeValue(this.viewSettingsIconSizeDraft);
    },
    set(value) {
      this.viewSettingsIconSizeDraft = this.normalizeIconSizeValue(value);
    },
  },
  itemDetailNicknameModel: {
    get() {
      return this.itemDetailNickname;
    },
    set(value) {
      this.$emit("update:itemDetailNickname", String(value ?? ""));
    },
  },
  suggestionDetailVariantModel: {
    get() {
      if (this.suggestionDetailVariantId) {
        return this.suggestionDetailVariantId;
      }
      const first = this.suggestionDetailVariantOptions(
        this.suggestionDetailEntry,
      )[0];
      return String(first?.variantId || "");
    },
    set(value) {
      this.$emit("update:suggestionDetailVariantId", String(value ?? ""));
    },
  },
  iconCatalogClasses() {
    const removed = new Set(
      (this.iconCollectionChanges?.removed || []).map((iconClass) =>
        String(iconClass || "").toLowerCase(),
      ),
    );
    const defaults = Array.isArray(runtime.inventoryIconClasses)
      ? runtime.inventoryIconClasses
      : [];
    const added = Object.keys(this.iconCollectionChanges?.added || {});
    const propOptions = (this.imgClassOptions || []).map((iconClass) =>
      String(iconClass || "").toLowerCase(),
    );
    return Array.from(new Set([...defaults, ...propOptions, ...added]))
      .filter((iconClass) => runtime.ICON_CLASS_PATTERN.test(iconClass))
      .filter((iconClass) => !removed.has(iconClass))
      .sort(
        (left, right) =>
          this.parseImgClassNumber(left) - this.parseImgClassNumber(right),
      );
  },
  normalizedImgClassOptions() {
    return (this.iconCatalogClasses || [])
      .map((iconClass) => String(iconClass || "").toLowerCase())
      .filter((iconClass) => runtime.ICON_CLASS_PATTERN.test(iconClass))
      .sort(
        (left, right) =>
          this.parseImgClassNumber(left) - this.parseImgClassNumber(right),
      );
  },
  selectedIconPreviewClass() {
    return this.selectedImgClass;
  },
  selectedIconClassModel: {
    get() {
      return this.selectedIconClassDraft || this.selectedImgClass;
    },
    set(value) {
      this.selectedIconClassDraft = String(value || "").toLowerCase();
      this.iconCatalogValidationError = "";
    },
  },
  canDeleteSelectedIcon() {
    return this.isIconClassVisible(this.selectedImgClass);
  },
  imgClassTypeOptions() {
    return runtime.ICON_THEME_CATALOG.map((entry) => ({
      value: String(entry.key || "").toUpperCase(),
      label: `${entry.label?.pl || entry.key} (${entry.key})`,
    }));
  },
  selectedIconMetadata() {
    const iconClass = String(this.selectedImgClass || "").toLowerCase();
    if (!iconClass || !runtime.ICON_CLASS_PATTERN.test(iconClass)) {
      return this.defaultIconMetadata("v0001");
    }
    const source =
      this.selectedIconMetadataDraft || this.getIconMetadata(iconClass);
    return this.normalizeIconMetadata(
      this.metadataIconClassForDisplay(iconClass),
      source,
    );
  },
  selectedIconSubtypeOptions() {
    const typeKeys = this.selectedIconMetadata.typeKeys || [];
    const subtypeKeys = new Set();
    if (!typeKeys.length) {
      this.allSubtypeKeys().forEach((key) => subtypeKeys.add(key));
    } else {
      typeKeys.forEach((typeKey) => {
        this.subtypeEntriesForType(typeKey).forEach((entry) =>
          subtypeKeys.add(String(entry.key || "").toUpperCase()),
        );
      });
    }
    return Array.from(subtypeKeys)
      .sort()
      .map((value) => ({
        value,
        label: `${this.labelForSubtype(value, "pl")} (${value})`,
      }));
  },
  selectedIconTypeKeys() {
    return this.selectedIconMetadata.typeKeys || [];
  },
  selectedIconSubtypeKeys() {
    return this.selectedIconMetadata.subtypeKeys || [];
  },
  hasSelectedIconMetadataChanges() {
    const iconClass = String(this.selectedImgClass || "").toLowerCase();
    if (!iconClass || !runtime.ICON_CLASS_PATTERN.test(iconClass)) {
      return false;
    }
    const nextIconClass = this.normalizeIconClassDraft(
      this.selectedIconClassDraft,
    );
    if (nextIconClass && nextIconClass !== iconClass) {
      return true;
    }
    const current = this.getIconMetadata(iconClass);
    const metadataIconClass = this.metadataIconClassForDisplay(iconClass);
    const draft = this.normalizeIconMetadata(
      metadataIconClass,
      this.selectedIconMetadata,
    );
    return JSON.stringify(current) !== JSON.stringify(draft);
  },
  selectedIconNameModel: {
    get() {
      return this.selectedIconMetadata.name;
    },
    set(value) {
      this.updateSelectedIconMetadataDraft({
        name: value,
      });
    },
  },
  selectedIconDescriptionModel: {
    get() {
      return this.selectedIconMetadata.description;
    },
    set(value) {
      this.updateSelectedIconMetadataDraft({
        description: value,
      });
    },
  },
  selectedIconSpecialMarksModel: {
    get() {
      return this.selectedIconMetadata.specialMarks;
    },
    set(value) {
      this.updateSelectedIconMetadataDraft({
        specialMarks: value,
      });
    },
  },
  selectedIconTagsModel() {
    return this.buildAutoTags(this.selectedIconMetadata);
  },
  imgClassSubtypeFilterOptions() {
    const sourceTypes =
      this.imgClassTypeFilter === "all"
        ? runtime.ICON_THEME_CATALOG
        : runtime.ICON_THEME_CATALOG.filter(
            (entry) =>
              String(entry.key || "").toUpperCase() ===
              String(this.imgClassTypeFilter || "").toUpperCase(),
          );
    const subtypeKeys = new Set();
    sourceTypes.forEach((entry) => {
      (entry.subtypes || []).forEach((subtype) =>
        subtypeKeys.add(String(subtype.key || "").toUpperCase()),
      );
    });
    return Array.from(subtypeKeys)
      .sort()
      .map((value) => ({
        value,
        label: `${this.labelForSubtype(value, "pl")} (${value})`,
      }));
  },
  filteredImgClassOptions() {
    const search = String(this.imgClassSearch || "")
      .trim()
      .toLowerCase();
    const filtered = this.normalizedImgClassOptions.filter((iconClass) => {
      const metadata = this.getIconMetadata(iconClass);
      if (
        this.imgClassTypeFilter !== "all" &&
        !metadata.typeKeys.includes(this.imgClassTypeFilter)
      ) {
        return false;
      }
      if (
        this.imgClassSubtypeFilter !== "all" &&
        !metadata.subtypeKeys.includes(this.imgClassSubtypeFilter)
      ) {
        return false;
      }
      if (!search) {
        return true;
      }
      const numeric = this.parseImgClassNumber(iconClass);
      const fulltext = [
        iconClass,
        metadata.name,
        metadata.description,
        metadata.specialMarks,
        metadata.tags,
        ...(metadata.typeKeys || []),
        ...(metadata.subtypeKeys || []),
        ...(metadata.typeTranslations?.pl || []).filter(Boolean),
        ...(metadata.subtypeTranslations?.pl || []).filter(Boolean),
        String(numeric).padStart(4, "0"),
      ]
        .join(" ")
        .toLowerCase();
      return fulltext.includes(search) || iconClass.includes(search);
    });
    if (this.imgClassSortMode === "desc") {
      return [...filtered].reverse();
    }
    return filtered;
  },
  iconSubtypeToTypeMap() {
    const mapping = {};
    runtime.ICON_THEME_CATALOG.forEach((typeEntry) => {
      const typeKey = String(typeEntry?.key || "").toUpperCase();
      (typeEntry?.subtypes || []).forEach((subtype) => {
        const subtypeKey = String(subtype?.key || "").toUpperCase();
        if (subtypeKey) {
          mapping[subtypeKey] = typeKey;
        }
      });
    });
    return mapping;
  },
  buyTradeTypeModel() {
    if (this.loadingBuy || this.errorBuy) {
      return this.emptyTradeTypeModel();
    }
    return this.buildTradeTypeModel(this.buyItems, this.buyTypeFilter);
  },
  sellTradeTypeModel() {
    if (this.loadingSell || this.errorSell) {
      return this.emptyTradeTypeModel();
    }
    return this.buildTradeTypeModel(this.sellItems, this.sellTypeFilter);
  },
});
