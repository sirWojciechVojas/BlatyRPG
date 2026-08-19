export const createContentMethodsPart4 = (runtime) => ({
  addIconToCatalog() {
    const iconClass = this.normalizeIconClassDraft(this.newIconClassDraft);
    if (!iconClass) {
      this.iconCatalogValidationError = this.$t(
        "modals.iconClass.errors.invalidClass",
      );
      return;
    }
    if (this.isIconClassVisible(iconClass)) {
      this.iconCatalogValidationError = this.$t(
        "modals.iconClass.errors.classVisible",
        {
          iconClass: iconClass.toUpperCase(),
        },
      );
      return;
    }
    const removed = new Set(this.iconCollectionChanges?.removed || []);
    removed.delete(iconClass);
    const added = {
      ...(this.iconCollectionChanges?.added || {}),
    };
    if (!this.isDefaultIconClass(iconClass)) {
      added[iconClass] = true;
    }
    this.updateIconCollectionChanges({
      added,
      removed: Array.from(removed),
    });
    this.newIconClassDraft = "";
    this.iconCatalogValidationError = "";
    this.selectImgClass(iconClass);
  },
  deleteSelectedIconFromCatalog() {
    const iconClass = this.normalizeIconClassDraft(this.selectedImgClass);
    if (!iconClass) {
      this.iconCatalogValidationError = this.$t(
        "modals.iconClass.errors.chooseIconFirst",
      );
      return;
    }
    const removed = new Set(this.iconCollectionChanges?.removed || []);
    removed.add(iconClass);
    const added = {
      ...(this.iconCollectionChanges?.added || {}),
    };
    delete added[iconClass];
    const remap = {
      ...(this.iconCollectionChanges?.remap || {}),
    };
    delete remap[iconClass];
    this.updateIconCollectionChanges({
      added,
      removed: Array.from(removed),
      remap,
    });
    const nextIconClass =
      this.normalizedImgClassOptions.find((entry) => entry !== iconClass) || "";
    if (nextIconClass) {
      this.selectImgClass(nextIconClass);
    } else {
      this.selectedImgClass = "";
      this.selectedIconClassDraft = "";
    }
    this.iconCatalogValidationError = "";
  },
  startIconClassDrag(iconClass, event = null) {
    const normalized = this.normalizeIconClassDraft(iconClass);
    this.draggedIconClass = normalized;
    this.dragOverIconClass = "";
    if (event?.dataTransfer && normalized) {
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", normalized);
    }
  },
  enterIconClassDropTarget(iconClass) {
    const normalized = this.normalizeIconClassDraft(iconClass);
    if (!normalized || normalized === this.draggedIconClass) {
      this.dragOverIconClass = "";
      return;
    }
    this.dragOverIconClass = normalized;
  },
  clearIconClassDragState() {
    this.draggedIconClass = "";
    this.dragOverIconClass = "";
  },
  dropIconClassOn(iconClass) {
    const sourceIconClass = this.normalizeIconClassDraft(this.draggedIconClass);
    const targetIconClass = this.normalizeIconClassDraft(iconClass);
    if (!sourceIconClass || !targetIconClass) {
      this.clearIconClassDragState();
      return;
    }
    if (sourceIconClass === targetIconClass) {
      this.clearIconClassDragState();
      return;
    }
    const sourceImageClass = this.sourceIconClassForDisplay(sourceIconClass);
    const targetImageClass = this.sourceIconClassForDisplay(targetIconClass);
    const remap = {
      ...(this.iconCollectionChanges?.remap || {}),
    };
    if (targetImageClass && targetImageClass !== sourceIconClass) {
      remap[sourceIconClass] = targetImageClass;
    } else {
      delete remap[sourceIconClass];
    }
    if (sourceImageClass && sourceImageClass !== targetIconClass) {
      remap[targetIconClass] = sourceImageClass;
    } else {
      delete remap[targetIconClass];
    }
    this.updateIconCollectionChanges({
      remap,
    });
    this.selectImgClass(targetIconClass);
    this.clearIconClassDragState();
  },
  loadIconCollectionChanges() {
    if (typeof window === "undefined") {
      return;
    }
    try {
      const raw = window.localStorage.getItem(
        runtime.ICON_COLLECTION_STORAGE_KEY,
      );
      if (!raw) {
        return;
      }
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") {
        return;
      }
      this.iconCollectionChanges = this.sanitizeIconCollectionChanges(parsed);
    } catch (error) {
      this.iconCollectionChanges = this.sanitizeIconCollectionChanges({});
    }
  },
  persistIconCollectionChanges() {
    if (typeof window === "undefined") {
      return;
    }
    try {
      window.localStorage.setItem(
        runtime.ICON_COLLECTION_STORAGE_KEY,
        JSON.stringify(this.iconCollectionChanges || {}),
      );
    } catch (error) {
      // ignore storage errors
    }
  },
  loadIconMetadataMap() {
    if (typeof window === "undefined") {
      return;
    }
    try {
      const raw = window.localStorage.getItem(
        runtime.ICON_METADATA_STORAGE_KEY,
      );
      if (!raw) {
        return;
      }
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") {
        return;
      }
      const sanitized = {};
      Object.keys(parsed).forEach((iconClass) => {
        const normalizedKey = String(iconClass || "").toLowerCase();
        if (!/^v\d{4}$/.test(normalizedKey)) {
          return;
        }
        sanitized[normalizedKey] = this.normalizeIconMetadata(
          normalizedKey,
          parsed[iconClass],
        );
      });
      this.iconMetadataMap = sanitized;
    } catch (error) {
      this.iconMetadataMap = {};
    }
  },
  persistIconMetadataMap() {
    if (typeof window === "undefined") {
      return;
    }
    try {
      window.localStorage.setItem(
        runtime.ICON_METADATA_STORAGE_KEY,
        JSON.stringify(this.iconMetadataMap || {}),
      );
    } catch (error) {
      // ignore storage errors
    }
  },
  pickerIconStyle(imgClass, size) {
    const parsed = Number(size);
    const safeSize = Number.isFinite(parsed) ? Math.max(1, parsed) : 42;
    const sourceIconClass = this.sourceIconClassForDisplay(imgClass);
    return {
      ...this.iconStyle(sourceIconClass || imgClass),
      "--trade-icon-size": `${safeSize}px`,
    };
  },
});
