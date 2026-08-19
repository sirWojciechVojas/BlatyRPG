export const createCoreComputedPart5 = (runtime) => {
  return {
    itemDetailMetaSections() {
      return runtime.buildItemDetailMetaSections(this, runtime);
    },
    apiStatusLabel() {
      if (this.apiStatus === "loading") {
        return runtime.t("shop.tradeModal.apiLoading");
      }
      if (this.apiStatus === "error") {
        return runtime.t("shop.tradeModal.apiError");
      }
      return "";
    },
    buyActionLabel() {
      if (this.isTrashMode) {
        return runtime.t("actions.restore");
      }
      if (
        this.showBuyForm &&
        this.isGM &&
        this.gmMode !== runtime.GM_MODES.TEMPLATES
      ) {
        return runtime.t("actions.save");
      }
      return this.buyButtonLabel;
    },
    buyActionEnabled() {
      if (this.buyTransactionPending || this.sellTransactionPending) {
        return false;
      }
      if (!this.isGM && !this.hasPlayerVisibleShops) {
        return false;
      }
      if (this.isTrashMode) {
        return !!this.selectedTrash;
      }
      return this.showBuyForm || this.buyHasSelection;
    },
    sellActionLabel() {
      if (
        this.showSellAddForm &&
        this.isGM &&
        this.gmMode === runtime.GM_MODES.TEMPLATES
      ) {
        return runtime.t("actions.add");
      }
      return this.sellButtonLabel;
    },
    sellActionEnabled() {
      if (this.buyTransactionPending || this.sellTransactionPending) {
        return false;
      }
      return this.showSellAddForm || this.sellHasSelection;
    },
    selectedSellEditItem() {
      if (this.gmMode === runtime.GM_MODES.INVENTORY) {
        return this.selectedInventory;
      }
      return null;
    },
    playerVisibleShops() {
      if (this.isGM) {
        return this.shops || [];
      }
      return (this.shops || []).filter((shop) => shop?.isActive !== false);
    },
    hasPlayerVisibleShops() {
      return this.playerVisibleShops.length > 0;
    },
    gmActiveModuleName() {
      if (!this.isGM) {
        return "";
      }
      switch (this.gmMode) {
        case runtime.GM_MODES.TEMPLATES:
          return runtime.t("shop.modules.templates");
        case runtime.GM_MODES.INVENTORY:
          return runtime.t("shop.modules.defaultStack");
        case runtime.GM_MODES.TRASH:
          return runtime.t("shop.modules.trash");
        case runtime.GM_MODES.SHOP_ADD_EDIT:
        case runtime.GM_MODES.SHOP_ARTICLE_EDITOR:
          return runtime.t("shop.modules.shopEditor");
        case runtime.GM_MODES.ASSORTMENT:
          return runtime.t("shop.modules.assortment");
        case runtime.GM_MODES.ASSORTMENT_TOOLS:
          return runtime.t("shop.modules.quickTransfer");
        default:
          return "";
      }
    },
    playerVisibleShopName() {
      if (this.isGM) {
        return this.gmActiveModuleName || this.shopName;
      }
      if (!this.hasPlayerVisibleShops) {
        return runtime.t("shop.tradeModal.noActiveShopsTitle");
      }
      return this.shopName;
    },
    playerVisibleBuyItems() {
      if (this.isGM) {
        return this.buyItems;
      }
      const activeVisible = this.playerVisibleShops.some(
        (shop) => Number(shop.id) === Number(this.activeShopId),
      );
      return activeVisible ? this.buyItems : [];
    },
  };
};
