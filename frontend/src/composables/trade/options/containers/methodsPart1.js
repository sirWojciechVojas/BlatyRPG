export const createContainersMethodsPart1 = (runtime) => {
  return {
    handleToggleContainerSelection(payload) {
      if (!payload) {
        return;
      }
      this.toggleContainerSelection(payload.itemKey, payload.side);
    },
    handleOpenClassEdit(payload) {
      if (!payload) {
        return;
      }
      this.openClassEdit(payload.field, payload.target);
    },
    handleOpenItemDetailDialog(payload) {
      if (!payload) {
        return;
      }
      this.openItemDetailDialog(payload.item, payload.source);
    },
    toggleGM() {
      const next = !this.isGM;
      this.setIsGM(next);
      this.setGMMode("templates");
      this.clearSelections();
      this.resetEditState();
    },
    handleLeftFlankAction(button) {
      if (button.disabled) {
        return;
      }
      if (button.shopId) {
        if (this.activeShopId === button.shopId) {
          return;
        }
        this.setActiveShop(button.shopId);
        this.clearSelections();
        this.resetEditState();
        return;
      }
      if (!button.mode) {
        return;
      }
      if (this.gmMode === button.mode) {
        return;
      }
      this.setGMMode(button.mode);
      this.clearSelections();
      this.resetEditState();
    },
    handleRightFlankAction(button) {
      if (button.disabled) {
        return;
      }
      if (
        button.action === "shopAddEdit" ||
        button.action === "shopArticleEditor"
      ) {
        if (this.gmMode !== "shopAddEdit") {
          this.setGMMode("shopAddEdit");
        }
        this.initShopAddEditState();
        return;
      }
      if (
        button.action === "assortment" ||
        button.action === "assortmentTools"
      ) {
        if (this.gmMode !== button.action) {
          this.setGMMode(button.action);
        }
        this.initAssortmentState();
        return;
      }
    },
    initShopAddEditState() {
      const profile = this.activeShopProfile;
      const fallbackWorld = this.worldProfiles?.[0]?.id || "standard";
      this.setShopEditorState({
        typeId: profile?.typeId || this.shopEditorState?.typeId || "",
        ownerCode:
          profile?.ownerCode || this.shopEditorState?.ownerCode || "BG1",
        ownerName: profile?.ownerName || this.shopEditorState?.ownerName || "",
        signboardName:
          profile?.signboardName ||
          this.shopName ||
          this.shopEditorState?.signboardName ||
          "",
        signboardAltNamesText: (profile?.signboardAltNames || []).join(", "),
        categoryTagsText: (profile?.categoryTags || []).join(", "),
        worldProfileId: profile?.worldProfileId || fallbackWorld,
        locationType: profile?.locationType || "miasto",
        legalStatus: profile?.legalStatus || "legal",
        wealthTier: profile?.wealthTier || "standard",
        reputation: profile?.reputation || "neutralna",
        seasonality: profile?.seasonality || "caloroczny",
        counterfeitRisk: Number(profile?.counterfeitRisk || 10),
        selectedSuggestionIds: [],
      });
    },
    initAssortmentState() {
      this.buildContainerStateFromStore();
      const containers = this.containerState?.containers || [];
      if (!containers.length) {
        this.showWalletAlert(runtime.t("shop.alerts.noContainersToEdit"));
        return;
      }
      this.resetClassEdit();
      this.closeAssortmentMergeDialog();
      const containerIds = containers.map((container) => container.id);
      const shopContainerIds = containers
        .filter((container) => container.type === "SHOP")
        .map((container) => container.id);
      const sourceContainerIds = containers
        .filter(
          (container) =>
            container.type === "SYSTEM" &&
            (container.systemKey === "DEFAULT" ||
              container.systemKey === "TRASH"),
        )
        .map((container) => container.id);
      const defaultId = runtime.getSystemContainerId(
        this.containerState,
        "DEFAULT",
      );
      const trashId = runtime.getSystemContainerId(
        this.containerState,
        "TRASH",
      );
      const fallbackLeft = sourceContainerIds.includes(
        this.assortmentLeftContainerId,
      )
        ? this.assortmentLeftContainerId
        : (defaultId ?? trashId ?? sourceContainerIds[0] ?? containerIds[0]);
      const fallbackRight = shopContainerIds.includes(
        this.assortmentRightContainerId,
      )
        ? this.assortmentRightContainerId
        : (shopContainerIds[0] ?? null);
      this.assortmentLeftContainerId = fallbackLeft;
      this.assortmentRightContainerId = fallbackRight;
      this.assortmentLeftSelectedKeys = [];
      this.assortmentRightSelectedKeys = [];
      this.assortmentSearch = "";
      this.assortmentLeftTab = "transfer";
      this.assortmentRightTab =
        this.assortmentRightTab === "suggestions" ? "suggestions" : "transfer";
      if (!shopContainerIds.length) {
        this.showWalletAlert(runtime.t("shop.alerts.noShopContainersToEdit"));
      }
      if (!containerIds.includes(this.gmMoveTargetContainerId)) {
        this.gmMoveTargetContainerId =
          this.assortmentRightContainerId ?? containerIds[0];
      }
      if (!this.gmMoveItemKey) {
        this.gmMoveItemKey = "";
      }
      this.gmMoveQuantity = 1;
      const shopContainer = containers.find(
        (container) => container.type === "SHOP",
      );
      const characterContainer = containers.find(
        (container) => container.type === "CHARACTER",
      );
      if (!containerIds.includes(this.shopBuyContainerId)) {
        this.shopBuyContainerId = shopContainer?.id ?? null;
      }
      if (!containerIds.includes(this.shopBuyTargetContainerId)) {
        this.shopBuyTargetContainerId = characterContainer?.id ?? null;
      }
      if (!this.shopBuyItemKey) {
        this.shopBuyItemKey = "";
      }
      this.shopBuyQuantity = 1;
    },
  };
};
