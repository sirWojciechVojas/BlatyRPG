export const createCoreMethodsPart2 = (runtime) => {
  return {
    closeShopActivationDialog() {
      this.showShopActivationDialog = false;
    },
    async handleToggleShopActivation(shopId) {
      const targetId = Number(shopId);
      if (!Number.isFinite(targetId)) {
        return;
      }
      const shop = (this.shops || []).find(
        (entry) => Number(entry.id) === targetId,
      );
      if (!shop) {
        return;
      }
      const nextState = !this.isShopActiveEntry(shop);
      const result = await this.updateShopActivation({
        shopId: targetId,
        isActive: nextState,
      });
      if (!result?.ok) {
        this.showWalletAlert(runtime.t("shop.alerts.shopActivationFailed"));
        return;
      }
      if (!result.changed) {
        return;
      }
      const alertKey = nextState
        ? "shop.alerts.shopActivated"
        : "shop.alerts.shopDeactivated";
      this.showWalletAlert(
        runtime.t(alertKey, {
          name:
            shop.name ||
            runtime.t("shop.defaults.shopName.withId", {
              id: targetId,
            }),
        }),
      );
      this.ensurePlayerActiveShop();
      if (this.isShopAddEditMode) {
        this.initShopAddEditState();
      }
    },
    selectedBuyQuantityForItem(item) {
      const id = Number(item?.ID);
      const available = Math.max(1, Number(item?.QUANTITY || 1));
      const raw = Number(this.selectedBuyQuantities?.[id]);
      if (!Number.isFinite(raw)) {
        return 1;
      }
      return Math.max(1, Math.min(available, Math.round(raw)));
    },
    selectedSellQuantityForItem(item) {
      const id = Number(item?.ID);
      const available = Math.max(1, Number(item?.QUANTITY || 1));
      const raw = Number(this.selectedSellQuantities?.[id]);
      if (!Number.isFinite(raw)) {
        return 1;
      }
      return Math.max(1, Math.min(available, Math.round(raw)));
    },
    canAdjustBuySelectionQuantity(item) {
      if (this.isGM) {
        return false;
      }
      const id = Number(item?.ID);
      const quantity = Number(item?.QUANTITY);
      return (
        Number.isFinite(quantity) &&
        quantity > 1 &&
        this.selectedBuyIds.includes(id)
      );
    },
    canAdjustSellSelectionQuantity(item) {
      if (this.isGM) {
        return false;
      }
      const id = Number(item?.ID);
      const quantity = Number(item?.QUANTITY);
      return (
        Number.isFinite(quantity) &&
        quantity > 1 &&
        this.selectedSellIds.includes(id)
      );
    },
    updateBuyItemSelectionQuantity(payload) {
      const item = payload?.item;
      if (!item) {
        return;
      }
      const max = Math.max(1, Number(item.QUANTITY || 1));
      this.setBuySelectionQuantity({
        id: Number(item.ID),
        quantity: Number(payload.quantity),
        max,
      });
    },
    stepBuyItemSelectionQuantity(payload) {
      const item = payload?.item;
      if (!item) {
        return;
      }
      const delta = Number(payload?.delta);
      const current = this.selectedBuyQuantityForItem(item);
      const next = Number.isFinite(delta) ? current + delta : current;
      this.updateBuyItemSelectionQuantity({
        item,
        quantity: next,
      });
    },
    updateSellItemSelectionQuantity(payload) {
      const item = payload?.item;
      if (!item) {
        return;
      }
      const max = Math.max(1, Number(item.QUANTITY || 1));
      this.setSellSelectionQuantity({
        id: Number(item.ID),
        quantity: Number(payload.quantity),
        max,
      });
    },
    stepSellItemSelectionQuantity(payload) {
      const item = payload?.item;
      if (!item) {
        return;
      }
      const delta = Number(payload?.delta);
      const current = this.selectedSellQuantityForItem(item);
      const next = Number.isFinite(delta) ? current + delta : current;
      this.updateSellItemSelectionQuantity({
        item,
        quantity: next,
      });
    },
    shopSuggestionReasonText(suggestion) {
      if (!suggestion || typeof suggestion !== "object") {
        return runtime.t("shop.suggestions.noReason");
      }
      const intro =
        suggestion?.action === "create_draft"
          ? runtime.t("shop.suggestions.reasonDraftIntro")
          : runtime.t("shop.suggestions.reasonProfileIntro");
      const classLine = suggestion?.classKey
        ? runtime.t("shop.suggestions.reasonClass", {
            value: String(suggestion.classKey).toUpperCase(),
          })
        : "";
      const genreLine = suggestion?.genreKey
        ? runtime.t("shop.suggestions.reasonGenre", {
            value: String(suggestion.genreKey).toUpperCase(),
          })
        : "";
      const shopContext = this.shopEditorForm || {};
      const contextParts = [
        shopContext.locationType
          ? runtime.t("shop.suggestions.reasonLocation", {
              value: shopContext.locationType,
            })
          : "",
        shopContext.legalStatus
          ? runtime.t("shop.suggestions.reasonLegalStatus", {
              value: shopContext.legalStatus,
            })
          : "",
        shopContext.wealthTier
          ? runtime.t("shop.suggestions.reasonWealthTier", {
              value: shopContext.wealthTier,
            })
          : "",
      ].filter(Boolean);
      const contextLine = contextParts.length
        ? runtime.t("shop.suggestions.reasonProfile", {
            value: contextParts.join(", "),
          })
        : "";
      return [intro, classLine, genreLine, contextLine]
        .filter(Boolean)
        .join(" ");
    },
    shopSuggestionReasonDetailsText(suggestion) {
      const details = Array.isArray(suggestion?.reasonDetails)
        ? suggestion.reasonDetails
        : [];
      if (!details.length) {
        return [];
      }
      return details.map((entry) => {
        const text = String(entry?.textPl || "").trim();
        const refKey = String(entry?.refKey || "").trim();
        const refValue = String(entry?.refValue || "").trim();
        if (!refKey && !refValue) {
          return text;
        }
        if (refKey && refValue) {
          return `${text} (${refKey}: ${refValue})`;
        }
        return `${text} (${refKey || refValue})`;
      });
    },
    handleShopEditorShopChange(shopId) {
      const nextId = Number(shopId);
      if (!Number.isFinite(nextId)) {
        return;
      }
      if (Number(this.activeShopId) !== nextId) {
        this.setActiveShop(nextId);
      }
      this.initShopAddEditState();
    },
    async handleCreateShop() {
      const createdId = await this.createShop({
        name: this.shopEditorForm.signboardName || "",
        ownerCode: this.shopEditorForm.ownerCode || "BG1",
        ownerName: this.shopEditorForm.ownerName || "",
      });
      if (!Number.isFinite(Number(createdId))) {
        this.showWalletAlert(runtime.t("shop.alerts.shopCreateFailed"));
        return;
      }
      this.setShopSuggestions([]);
      this.initShopAddEditState();
      this.showWalletAlert(runtime.t("shop.alerts.shopCreated"));
    },
    async handleDeleteActiveShop() {
      if (!this.canDeleteActiveShop) {
        this.showWalletAlert(runtime.t("shop.alerts.shopMinOne"));
        return;
      }
      const deleted = await this.deleteShop({
        shopId: this.activeShopId,
      });
      if (!deleted) {
        this.showWalletAlert(runtime.t("shop.alerts.shopDeleteFailed"));
        return;
      }
      this.setShopSuggestions([]);
      this.initShopAddEditState();
      this.showWalletAlert(runtime.t("shop.alerts.shopDeleted"));
    },
  };
};
