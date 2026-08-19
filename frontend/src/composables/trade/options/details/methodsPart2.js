export const createDetailsMethodsPart2 = (runtime) => {
  return {
    openSuggestionDetailDialog(entry) {
      if (!entry || typeof entry !== "object") {
        return;
      }
      this.suggestionDetailEntry = {
        ...entry,
      };
      const variants = Array.isArray(entry.personalizedVariants)
        ? entry.personalizedVariants
        : [];
      this.suggestionDetailVariantId = String(
        variants[0]?.variantId || entry?.variantId || "",
      );
      this.showSuggestionDetailDialog = true;
    },
    closeSuggestionDetailDialog() {
      this.showSuggestionDetailDialog = false;
      this.suggestionDetailEntry = null;
      this.suggestionDetailVariantId = "";
    },
    async confirmSuggestionDetailAction(payload = {}) {
      const action = String(payload?.action || "").trim();
      if (!action || !this.suggestionDetailEntry) {
        return null;
      }
      if (typeof this.materializeShopSuggestion !== "function") {
        return null;
      }
      const suggestionId = String(
        this.suggestionDetailEntry?.suggestionId || "",
      );
      if (!suggestionId) {
        return null;
      }
      const variantId = String(
        payload?.variantId || this.suggestionDetailVariantId || "",
      );
      let mode = "";
      if (action === "template_only") {
        mode = "template_only";
      } else if (action === "template_plus_item") {
        mode = "template_plus_item";
      } else if (action === "item_only") {
        mode = "item_only";
      } else {
        return null;
      }
      const result = await this.materializeShopSuggestion({
        shopId: this.activeShopId,
        suggestionId,
        mode,
        variantId,
      });
      this.persistTradingData();
      if (mode === "template_only") {
        const created = Number(result?.created || 0);
        if (!created) {
          this.showWalletAlert("Nie utworzono szablonu dla tej sugestii.");
        } else {
          this.showWalletAlert(`Utworzono ${created} szablon.`);
        }
      } else {
        const applied = Number(result?.applied || 0);
        if (!applied) {
          this.showWalletAlert("Nie dodano przedmiotu ze wskazanej sugestii.");
        } else {
          this.showWalletAlert("Dodano przedmiot ze wskazanej sugestii.");
        }
      }
      this.closeSuggestionDetailDialog();
      return result;
    },
    showTempHidden(className) {
      return className === "template" || className === "trashTemp";
    },
    nextTemplateId() {
      return runtime.nextIdFromItems(this.$store.state.shop.templateItems, 0);
    },
    nextInventoryId() {
      return runtime.nextIdFromItems(
        this.$store.state.shop.inventoryItems,
        100,
      );
    },
    nextTrashId() {
      return runtime.nextIdFromItems(this.$store.state.shop.trashItems, 200);
    },
    initModalScrollLock() {
      // Legacy cleanup: scroll lock is managed by Bootstrap via body.modal-open.
      document.body.classList.remove("trade-modal-open");
    },
    teardownModalScrollLock() {
      document.body.classList.remove("trade-modal-open");
      if (this.walletAlertTimeout) {
        clearTimeout(this.walletAlertTimeout);
        this.walletAlertTimeout = null;
      }
      runtime.clearAllShopNotifications();
    },
    showWalletAlert(message, options = {}) {
      const normalized =
        options && typeof options === "object" && !Array.isArray(options)
          ? options
          : {};
      this.walletAlertKey += 1;
      this.walletAlert = message;
      if (this.walletAlertTimeout) {
        clearTimeout(this.walletAlertTimeout);
      }
      this.walletAlertTimeout = setTimeout(() => {
        this.walletAlert = "";
        this.walletAlertTimeout = null;
      }, 3500);
      runtime.notifyShop({
        zone: normalized.zone || "all",
        type: normalized.type || "info",
        title: normalized.title,
        message,
        details: normalized.details,
        icon: normalized.icon,
        duration: normalized.duration,
      });
    },
  };
};
