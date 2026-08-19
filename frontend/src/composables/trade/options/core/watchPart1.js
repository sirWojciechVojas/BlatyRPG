export const createCoreWatchPart1 = (runtime) => {
  return {
    selectedTemplate: {
      immediate: true,
      handler(item) {
        this.templateForm = item
          ? {
              ...item,
            }
          : {};
        this.templateFormErrors = {};
        if (!item) {
          this.resetNewTemplateForm();
        }
      },
    },
    selectedSellEditItem: {
      immediate: true,
      handler(item) {
        this.inventoryForm = item
          ? {
              ...item,
            }
          : {};
        this.inventoryFormErrors = {};
      },
    },
    isGM(value) {
      if (!value) {
        this.ensurePlayerActiveShop();
      }
    },
    gmMode() {
      this.resetEditState();
      this.closeShopActivationDialog();
      this.assortmentRollPreview = [];
      this.assortmentRollPreviewMeta = null;
      if (
        this.gmMode === runtime.GM_MODES.ASSORTMENT ||
        this.gmMode === runtime.GM_MODES.ASSORTMENT_TOOLS
      ) {
        this.initAssortmentState();
      }
      if (
        this.gmMode === runtime.GM_MODES.SHOP_ADD_EDIT ||
        this.gmMode === runtime.GM_MODES.SHOP_ARTICLE_EDITOR
      ) {
        this.initShopAddEditState();
      }
      if (this.gmMode === runtime.GM_MODES.TRASH) {
        const available = new Set(
          (this.trashZoneOptions || []).map((entry) => entry.value),
        );
        if (!available.has(this.normalizedTrashZoneOwnerCode)) {
          this.trashZoneOwnerCode = runtime.TRASH_OWNER_GENERAL;
        } else {
          this.trashZoneOwnerCode = this.normalizedTrashZoneOwnerCode;
        }
      }
    },
    trashZoneOptions(options) {
      const available = new Set((options || []).map((entry) => entry.value));
      const normalized = this.normalizedTrashZoneOwnerCode;
      if (!available.has(normalized)) {
        this.trashZoneOwnerCode = runtime.TRASH_OWNER_GENERAL;
      }
    },
    trashZoneOwnerCode() {
      if (!this.isTrashMode) {
        return;
      }
      const visibleIds = new Set(
        (this.visibleSellItems || []).map((item) => Number(item?.ID)),
      );
      if (!visibleIds.has(Number(this.selectedTrashId))) {
        this.setSelectedTrashId(null);
      }
    },
    inventoryOwnerFilterOptions(options) {
      const available = new Set((options || []).map((entry) => entry.value));
      const active = String(this.inventoryOwnerCodeFilter || "all");
      if (!available.has(active)) {
        this.inventoryOwnerCodeFilter = "all";
      }
    },
    inventoryOwnerCodeFilter() {
      if (!this.isGM || this.gmMode !== runtime.GM_MODES.INVENTORY) {
        return;
      }
      const visibleIds = new Set(
        (this.visibleSellItems || []).map((item) => Number(item?.ID)),
      );
      if (!visibleIds.has(Number(this.selectedInventoryId))) {
        this.setSelectedInventoryId(null);
      }
    },
    iconSize(value) {
      const normalized = runtime.clampTradeIconSize(value);
      if (Number(value) !== normalized) {
        this.iconSize = normalized;
      }
    },
  };
};
