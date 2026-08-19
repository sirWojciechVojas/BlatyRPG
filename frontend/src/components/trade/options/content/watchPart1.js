export const createContentWatchPart1 = () => ({
  selectedBuyIds(next = [], previous = []) {
    if (next.length > previous.length) {
      this.mobileTradeTab = "buy";
    }
  },
  selectedSellIds(next = [], previous = []) {
    if (next.length > previous.length) {
      this.mobileTradeTab = "sell";
    }
  },
  inventoryForm: {
    immediate: true,
    deep: true,
    handler(value) {
      this.syncLocalForm("localInventoryForm", value);
    },
  },
  templateForm: {
    immediate: true,
    deep: true,
    handler(value) {
      this.syncLocalForm("localTemplateForm", value);
    },
  },
  newTemplateForm: {
    immediate: true,
    deep: true,
    handler(value) {
      this.syncLocalForm("localNewTemplateForm", value);
    },
  },
  localInventoryForm: {
    deep: true,
    handler(value) {
      if (this.syncingForms) {
        return;
      }
      this.$emit("update:inventoryForm", this.cloneForm(value));
    },
  },
  localTemplateForm: {
    deep: true,
    handler(value) {
      if (this.syncingForms) {
        return;
      }
      this.$emit("update:templateForm", this.cloneForm(value));
    },
  },
  localNewTemplateForm: {
    deep: true,
    handler(value) {
      if (this.syncingForms) {
        return;
      }
      this.$emit("update:newTemplateForm", this.cloneForm(value));
    },
  },
  showClassEditDialog(value) {
    if (value) {
      this.imgClassSearch = "";
      this.imgClassTypeFilter = "all";
      this.imgClassSubtypeFilter = "all";
      this.imgClassSortMode = "asc";
      this.newIconClassDraft = "";
      this.iconCatalogValidationError = "";
      this.loadSelectedIconMetadataDraft();
    }
  },
  selectedImgClass() {
    this.loadSelectedIconMetadataDraft();
  },
  showViewSettingsDialog(value) {
    if (!value) {
      return;
    }
    this.syncViewSettingsIconSizeDraft();
  },
  iconSize(value) {
    if (!this.showViewSettingsDialog) {
      return;
    }
    this.viewSettingsIconSizeDraft = this.normalizeIconSizeValue(value);
  },
  imgClassTypeFilter(value) {
    if (value === "all") {
      this.imgClassSubtypeFilter = "all";
      return;
    }
    const subtypeValues = this.imgClassSubtypeFilterOptions.map(
      (option) => option.value,
    );
    if (!subtypeValues.includes(this.imgClassSubtypeFilter)) {
      this.imgClassSubtypeFilter = "all";
    }
  },
  buyTypeFilterOptions(options) {
    const available = (options || []).map((entry) => entry.value);
    if (!available.includes(this.buyTypeFilter)) {
      this.buyTypeFilter = "all";
    }
  },
  sellTypeFilterOptions(options) {
    const available = (options || []).map((entry) => entry.value);
    if (!available.includes(this.sellTypeFilter)) {
      this.sellTypeFilter = "all";
    }
  },
  iconMetadataMap() {
    this.iconMetadataCacheStamp += 1;
    this.tradeItemTypeKeyCache.clear();
  },
  iconCollectionChanges: {
    deep: true,
    handler() {
      this.iconMetadataCacheStamp += 1;
      this.tradeItemTypeKeyCache.clear();
    },
  },
});
