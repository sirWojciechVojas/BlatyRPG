export const createActionsMethodsPart1 = (runtime) => {
  return {
    handleBuyItemClick(item) {
      this.resetClassEdit();
      if (this.isGM) {
        if (this.gmMode === runtime.GM_MODES.TRASH) {
          if (this.selectedInventoryId === item.ID) {
            this.setSelectedInventoryId(null);
            return;
          }
          this.setSelectedInventoryId(item.ID);
          return;
        }
        if (this.selectedTemplateId === item.ID) {
          this.setSelectedTemplateId(null);
          return;
        }
        this.setSelectedTemplateId(item.ID);
        return;
      }
      this.toggleBuySelection(item.ID);
    },
    handleSellItemClick(item) {
      this.resetClassEdit();
      if (this.isGM) {
        if (this.gmMode === runtime.GM_MODES.TRASH) {
          if (this.selectedTrashId === item.ID) {
            this.setSelectedTrashId(null);
          } else {
            this.setSelectedTrashId(item.ID);
          }
        } else if (this.gmMode === runtime.GM_MODES.INVENTORY) {
          if (this.selectedInventoryId === item.ID) {
            this.setSelectedInventoryId(null);
          } else {
            this.setSelectedInventoryId(item.ID);
          }
        }
        return;
      }
      this.toggleSellSelection(item.ID);
    },
    resolveTradeApiAlert(error) {
      const code = String(error?.code || "").toLowerCase();
      const status = Number(error?.status || 0);
      if (code === "insufficient_funds") {
        return runtime.t("shop.alerts.walletInsufficient");
      }
      if (code === "payment_quote_stale") {
        return runtime.t("shop.alerts.paymentQuoteStale");
      }
      if (code === "payment_conversion_required") {
        return runtime.t("shop.alerts.paymentConversionRequired");
      }
      if (code === "insufficient_stock") {
        return runtime.t("shop.alerts.noAvailableQuantity");
      }
      if (code === "shop_inactive") {
        return runtime.t("shop.alerts.shopInactive");
      }
      if (code === "invalid_quantity") {
        return runtime.t("shop.alerts.noAvailableQuantity");
      }
      if (code === "invalid_payload") {
        return runtime.t("shop.alerts.tradeInvalidPayload");
      }
      if (code === "missing_exchange_rate") {
        return runtime.t("shop.alerts.missingExchangeRate");
      }
      if (code === "encumbrance_exceeded") {
        return runtime.t("shop.alerts.encumbranceExceeded");
      }
      if (
        status === 401 ||
        status === 403 ||
        code.includes("token") ||
        code.includes("forbidden") ||
        code.includes("unauthorized") ||
        code.includes("brak tokena")
      ) {
        return runtime.t("shop.alerts.tradeUnauthorized");
      }
      return runtime.t("shop.alerts.tradeFailed");
    },
    createTrashItemFromTemplate(template) {
      const templateId = Number(template?.ID);
      const iconClass =
        template?.IMG_CLASS ||
        (typeof this.defaultIconClass === "function"
          ? this.defaultIconClass()
          : "v0001");
      const ownerCode = runtime.OWNER_CODES.TRASH;
      if (!this.canAllocateTrashSlots(ownerCode, 1)) {
        this.showWalletAlert(runtime.t("shop.alerts.trashGeneralFull"));
        return null;
      }
      return {
        ...template,
        ID: this.nextTrashId(),
        INV_ID: Number.isFinite(templateId) ? templateId : 0,
        ITEM_PLACE: "STOS",
        SLOT: "STOS",
        PERSONAL_PSEU: "Usuniety szablon",
        PERSONAL_DESC: template?.DESCRIPTION || "",
        PERSONAL_COST: Number(template?.PRIZE || 0),
        QUANTITY: 1,
        OWNER_OPT: runtime.OWNER_CODES.TRASH,
        OWNER: ownerCode,
        IMG_CLASS: iconClass,
        PRIZE: Number(template?.PRIZE || 0),
        CHARGE: Number(template?.CHARGE || 0),
        TRASH_KIND: runtime.TRASH_KINDS.TEMPLATE,
        TRASH_SOURCE_ID: Number.isFinite(templateId) ? templateId : null,
      };
    },
    createTrashItemFromInventory(item) {
      const inventoryId = Number(item?.ID);
      const templateId = Number(item?.INV_ID ?? item?.ID);
      const quantity = Number(item?.QUANTITY);
      const ownerCode = this.resolveTrashOwnerForInventoryItem(item);
      if (!this.canAllocateTrashSlots(ownerCode, 1)) {
        const used = this.trashItemsForOwner(ownerCode).length;
        const rawCapacity = this.trashCapacityForOwner(ownerCode);
        const capacity = Number(rawCapacity);
        this.showWalletAlert(
          runtime.t("shop.alerts.trashOwnerFull", {
            ownerCode,
            ownerLabel: this.trashOwnerLabel(ownerCode),
            used,
            limit:
              rawCapacity === null ||
              rawCapacity === undefined ||
              !Number.isFinite(capacity)
                ? "∞"
                : capacity,
          }),
        );
        return null;
      }
      return {
        ...item,
        ID: this.nextTrashId(),
        INV_ID: Number.isFinite(templateId) ? templateId : 0,
        QUANTITY: Number.isFinite(quantity) ? Math.max(1, quantity) : 1,
        OWNER_OPT: runtime.OWNER_CODES.TRASH,
        OWNER: ownerCode,
        TRASH_KIND: runtime.TRASH_KINDS.ITEM,
        TRASH_SOURCE_ID: Number.isFinite(inventoryId) ? inventoryId : null,
      };
    },
    restoreTemplateFromTrash(item) {
      const baseTemplateId = Number(item?.INV_ID ?? item?.TRASH_SOURCE_ID);
      const nextId = this.templateIdExists(baseTemplateId)
        ? this.nextTemplateId()
        : baseTemplateId;
      const restored = {
        ID: Number.isFinite(nextId) ? nextId : this.nextTemplateId(),
        NAME: item?.NAME || "Przywrocony szablon",
        DESCRIPTION: item?.DESCRIPTION || item?.PERSONAL_DESC || "",
        DETAILS: item?.DETAILS || "",
        ITEM_CLASS: item?.ITEM_CLASS || "TOOL",
        ITEM_ID: String(item?.ITEM_ID || ""),
        ITEM_GENRE: item?.ITEM_GENRE || "",
        IMG_CLASS:
          item?.IMG_CLASS ||
          (typeof this.defaultIconClass === "function"
            ? this.defaultIconClass()
            : "v0001"),
        PRIZE: Number(item?.PRIZE || item?.PERSONAL_COST || 0),
        CHARGE: Number(item?.CHARGE || 0),
      };
      this.addTemplateItem(restored);
    },
    restoreInventoryItemFromTrash(item) {
      const nextId = this.nextInventoryId();
      this.addInventoryStackItem({
        ID: Number.isFinite(Number(nextId)) ? Number(nextId) : nextId,
        INV_ID: Number(item?.INV_ID ?? item?.ID ?? 0),
        ITEM_PLACE: "DEFAULT",
        SLOT: "DEFAULT",
        PERSONAL_PSEU: item?.PERSONAL_PSEU || "Przywrocony",
        PERSONAL_DESC: item?.PERSONAL_DESC || item?.DESCRIPTION || "",
        PERSONAL_COST: Number(item?.PERSONAL_COST || item?.PRIZE || 0),
        QUANTITY: Number.isFinite(Number(item?.QUANTITY))
          ? Math.max(1, Number(item.QUANTITY))
          : 1,
        OWNER_OPT: runtime.OWNER_CODES.DEFAULT,
        NAME: item?.NAME || "Przywrocony przedmiot",
        DESCRIPTION: item?.DESCRIPTION || item?.PERSONAL_DESC || "",
        IMG_CLASS:
          item?.IMG_CLASS ||
          (typeof this.defaultIconClass === "function"
            ? this.defaultIconClass()
            : "v0001"),
        PRIZE: Number(item?.PRIZE || item?.PERSONAL_COST || 0),
        CHARGE: Number(item?.CHARGE || 0),
      });
    },
    handleRestoreFromTrash() {
      if (
        !this.isGM ||
        this.gmMode !== runtime.GM_MODES.TRASH ||
        !this.selectedTrash
      ) {
        return;
      }
      const selected = {
        ...this.selectedTrash,
      };
      const selectedTrashId = Number(selected.ID);
      const isTemplateTrash =
        String(selected.TRASH_KIND || "").toUpperCase() ===
        runtime.TRASH_KINDS.TEMPLATE;
      if (isTemplateTrash) {
        this.restoreTemplateFromTrash(selected);
      } else {
        this.restoreInventoryItemFromTrash(selected);
      }
      this.removeTrashItem(selectedTrashId);
      this.clearSelections();
      this.resetEditState();
      this.persistTradingData();
      this.showWalletAlert(runtime.t("shop.alerts.restoredFromTrash"));
    },
    handleDeleteTemplateToTrash() {
      if (
        !this.isGM ||
        this.gmMode !== runtime.GM_MODES.TEMPLATES ||
        !this.selectedTemplate
      ) {
        return;
      }
      const selectedTemplateId = Number(this.selectedTemplate.ID);
      const trashItem = this.createTrashItemFromTemplate(this.selectedTemplate);
      if (!trashItem) {
        return;
      }
      this.addTrashItem(trashItem);
      this.removeTemplateItem(selectedTemplateId);
      this.clearSelections();
      this.resetEditState();
      this.persistTradingData();
      this.showWalletAlert(runtime.t("shop.alerts.templateMovedToTrash"));
    },
  };
};
