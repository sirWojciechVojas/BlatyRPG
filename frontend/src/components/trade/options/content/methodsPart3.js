export const createContentMethodsPart3 = (runtime) => ({
  defaultIconMetadata(iconClass) {
    const normalizedIconClass = String(iconClass || "").toLowerCase();
    const catalogMetadata =
      runtime.inventoryIconMetadataMap?.[normalizedIconClass];
    if (catalogMetadata) {
      return {
        ...catalogMetadata,
        iconClass: normalizedIconClass,
        typeTranslations: {
          pl: [],
          en: [],
        },
        subtypeTranslations: {
          pl: [],
          en: [],
        },
        tags: "",
      };
    }
    return {
      iconClass: normalizedIconClass,
      name: `Ikona ${String(iconClass || "").toUpperCase()}`,
      description: "",
      specialMarks: "",
      typeKeys: [],
      subtypeKeys: [],
      typeTranslations: {
        pl: [],
        en: [],
      },
      subtypeTranslations: {
        pl: [],
        en: [],
      },
      tags: "",
    };
  },
  normalizeIconMetadata(iconClass, metadata = {}) {
    const fallback = this.defaultIconMetadata(iconClass);
    const rawTypeKeys =
      metadata.typeKeys ??
      metadata.types ??
      (metadata.type ? [metadata.type] : fallback.typeKeys);
    const typeKeys = this.normalizeTypeKeys(rawTypeKeys);
    const rawSubtypeKeys =
      metadata.subtypeKeys ??
      metadata.subtypes ??
      (metadata.subtype ? [metadata.subtype] : fallback.subtypeKeys);
    const subtypeKeys = this.normalizeSubtypeKeys(rawSubtypeKeys, typeKeys);
    const normalized = {
      iconClass,
      name: String(metadata.name ?? fallback.name),
      description: String(metadata.description ?? fallback.description),
      specialMarks: String(metadata.specialMarks ?? fallback.specialMarks),
      typeKeys,
      subtypeKeys,
      types: typeKeys,
      subtypes: subtypeKeys,
      typeTranslations: this.buildTranslations(typeKeys, "type"),
      subtypeTranslations: this.buildTranslations(subtypeKeys, "subtype"),
      sourceName: String(metadata.sourceName ?? fallback.sourceName ?? ""),
      tags: "",
    };
    normalized.tags = this.buildAutoTags(normalized);
    return normalized;
  },
  getIconMetadata(iconClass) {
    if (!iconClass) {
      return this.defaultIconMetadata("v0001");
    }
    const displayIconClass = String(iconClass || "").toLowerCase();
    const metadataIconClass =
      this.metadataIconClassForDisplay(displayIconClass);
    return this.normalizeIconMetadata(metadataIconClass, {
      ...(runtime.inventoryIconMetadataMap?.[metadataIconClass] || {}),
      ...(this.iconMetadataMap?.[metadataIconClass] || {}),
    });
  },
  loadSelectedIconMetadataDraft() {
    const iconClass = String(this.selectedImgClass || "").toLowerCase();
    this.selectedIconClassDraft = iconClass;
    this.iconCatalogValidationError = "";
    if (!iconClass || !runtime.ICON_CLASS_PATTERN.test(iconClass)) {
      this.selectedIconMetadataDraft = null;
      return;
    }
    const metadata = this.getIconMetadata(iconClass);
    this.selectedIconMetadataDraft = this.cloneForm(metadata);
  },
  updateSelectedIconMetadataDraft(patch = {}) {
    const iconClass = String(this.selectedImgClass || "").toLowerCase();
    if (!iconClass || !runtime.ICON_CLASS_PATTERN.test(iconClass)) {
      return;
    }
    const source =
      this.selectedIconMetadataDraft || this.getIconMetadata(iconClass);
    const metadataIconClass = this.metadataIconClassForDisplay(iconClass);
    this.selectedIconMetadataDraft = this.normalizeIconMetadata(
      metadataIconClass,
      {
        ...source,
        ...patch,
      },
    );
  },
  toggleSelectedIconType(typeKey) {
    const normalizedType = String(typeKey || "").toUpperCase();
    const current = this.selectedIconMetadata;
    const nextTypes = new Set(current.typeKeys || []);
    if (nextTypes.has(normalizedType)) {
      nextTypes.delete(normalizedType);
    } else {
      nextTypes.add(normalizedType);
    }
    const nextTypeKeys = Array.from(nextTypes);
    const nextSubtypeKeys = this.normalizeSubtypeKeys(
      current.subtypeKeys || [],
      nextTypeKeys,
    );
    this.updateSelectedIconMetadataDraft({
      typeKeys: nextTypeKeys,
      subtypeKeys: nextSubtypeKeys,
    });
  },
  toggleSelectedIconSubtype(subtypeKey) {
    const normalizedSubtype = String(subtypeKey || "").toUpperCase();
    const current = this.selectedIconMetadata;
    const allowed = new Set(
      this.normalizeSubtypeKeys([normalizedSubtype], current.typeKeys || []),
    );
    if (!allowed.has(normalizedSubtype)) {
      return;
    }
    const nextSubtypes = new Set(current.subtypeKeys || []);
    if (nextSubtypes.has(normalizedSubtype)) {
      nextSubtypes.delete(normalizedSubtype);
    } else {
      nextSubtypes.add(normalizedSubtype);
    }
    this.updateSelectedIconMetadataDraft({
      subtypeKeys: Array.from(nextSubtypes),
    });
  },
  applySelectedIconMetadata(options = {}) {
    const iconClass = String(this.selectedImgClass || "").toLowerCase();
    if (!iconClass || !runtime.ICON_CLASS_PATTERN.test(iconClass)) {
      return;
    }
    const nextIconClass =
      this.normalizeIconClassDraft(this.selectedIconClassDraft) || iconClass;
    if (!nextIconClass) {
      this.iconCatalogValidationError = this.$t(
        "modals.iconClass.errors.invalidClass",
      );
      return;
    }
    const isClassMove = nextIconClass !== iconClass;
    if (
      isClassMove &&
      this.isIconClassVisible(nextIconClass) &&
      !options.allowReplace
    ) {
      this.iconCatalogValidationError = this.$t(
        "modals.iconClass.errors.classTaken",
        {
          iconClass: nextIconClass.toUpperCase(),
        },
      );
      return;
    }
    if (!this.hasSelectedIconMetadataChanges) {
      return;
    }
    const metadataIconClass = this.metadataIconClassForDisplay(iconClass);
    const normalized = this.normalizeIconMetadata(
      metadataIconClass,
      this.selectedIconMetadata,
    );
    const nextMetadataMap = {
      ...this.iconMetadataMap,
      [metadataIconClass]: normalized,
    };
    this.iconMetadataMap = nextMetadataMap;
    if (isClassMove) {
      const removed = new Set(this.iconCollectionChanges?.removed || []);
      removed.add(iconClass);
      removed.delete(nextIconClass);
      const added = {
        ...(this.iconCollectionChanges?.added || {}),
      };
      if (!this.isDefaultIconClass(nextIconClass)) {
        added[nextIconClass] = true;
      }
      if (!this.isDefaultIconClass(iconClass)) {
        delete added[iconClass];
      }
      const remap = {
        ...(this.iconCollectionChanges?.remap || {}),
      };
      remap[nextIconClass] = metadataIconClass;
      delete remap[iconClass];
      this.updateIconCollectionChanges({
        added,
        removed: Array.from(removed),
        remap,
      });
      this.selectImgClass(nextIconClass);
    }
    this.selectedIconMetadataDraft = this.cloneForm(
      this.getIconMetadata(nextIconClass),
    );
    this.selectedIconClassDraft = nextIconClass;
    this.iconCatalogValidationError = "";
    this.persistIconMetadataMap();
  },
});
