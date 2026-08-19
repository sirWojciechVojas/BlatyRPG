export const createActionsMethodsPart3 = (runtime) => {
  return {
    async handleSellAction() {
      if (this.buyTransactionPending || this.sellTransactionPending) {
        return;
      }
      if (
        this.showSellAddForm &&
        this.isGM &&
        this.gmMode === runtime.GM_MODES.TEMPLATES
      ) {
        const normalized = this.normalizeTemplateForm(this.newTemplateForm);
        if (!normalized.valid) {
          if (typeof this.setFormErrors === "function") {
            this.setFormErrors("templateCreate", normalized.errors || {});
          }
          this.showWalletAlert(normalized.message, {
            zone: "sell",
            type: "error",
            title: "Błąd walidacji",
          });
          return;
        }
        if (typeof this.setFormErrors === "function") {
          this.setFormErrors("templateCreate", {});
        }
        if (this.templateIdExists(normalized.item.ID)) {
          this.showWalletAlert(runtime.t("shop.alerts.templateIdExists"), {
            zone: "sell",
            type: "error",
            title: "Błąd walidacji",
          });
          return;
        }
        const saved =
          typeof this.createTemplateRecord === "function"
            ? await this.createTemplateRecord(normalized.item)
            : null;
        if (!saved) {
          this.showWalletAlert(runtime.t("shop.alerts.templateCreateFailed"), {
            zone: "sell",
            type: "error",
            title: "Błąd zapisu",
          });
          return;
        }
        const savedId = Number(saved.ID ?? saved.id ?? normalized.item.ID);
        if (Number.isFinite(savedId)) {
          this.setSelectedTemplateId(savedId);
        }
        this.resetClassEdit();
        this.resetNewTemplateForm();
        this.showWalletAlert(runtime.t("shop.alerts.templateCreated"), {
          zone: "sell",
          type: "success",
          title: "Zapisano",
        });
        return;
      }
      if (!this.sellHasSelection) {
        return;
      }
      if (!this.isGM) {
        const total = this.sellTotalBrass;
        const selectedItems = [...this.selectedSellIds]
          .map((id) => {
            const item = this.sellItems.find(
              (entry) => Number(entry.ID) === Number(id),
            );
            if (!item) {
              return null;
            }
            const max = Math.max(1, Number(item.QUANTITY || 1));
            const requested = Number(this.selectedSellQuantities?.[id]);
            const quantity = Number.isFinite(requested)
              ? Math.max(1, Math.min(max, Math.round(requested)))
              : 1;
            return {
              id: Number(id),
              item,
              quantity,
            };
          })
          .filter(Boolean);
        if (!selectedItems.length) {
          runtime.clearShopNotificationZone("sell");
          runtime.notifyTradeProblem({
            zone: "sell",
            title: "Brak towaru",
            message: "Wybrany przedmiot nie jest już w ekwipunku.",
          });
          return;
        }
        if (typeof this.playerSellToShop === "function") {
          const selections = selectedItems.map(({ id, item, quantity }) => ({
            templateId: Number(item.INV_ID ?? item.ID),
            instanceId:
              Array.isArray(item.AGGREGATED_ITEM_IDS) &&
              (item.AGGREGATED_ITEM_IDS.length > 1 ||
                !item.IS_ITEM_INSTANCE ||
                quantity > 1)
                ? 0
                : Number(item.ID ?? id),
            quantity,
            clientId: Number(item.ID ?? id),
          }));
          this.sellTransactionPending = true;
          runtime.clearShopNotificationZone("sell");
          try {
            const apiResult = await this.playerSellToShop({
              ownerCode: this.activeBgOwner,
              shopId: this.activeShopId,
              selections,
            });
            if (apiResult?.handled && !apiResult.ok) {
              runtime.clearShopNotificationZone("sell");
              runtime.notifyTradeProblem({
                zone: "sell",
                title: "Transakcja odrzucona",
                message: this.resolveTradeApiAlert(apiResult.error),
              });
              return;
            }
            if (!apiResult?.handled) {
              runtime.applySellToUi(this, selectedItems, total);
            }
            runtime.notifyTradeSuccess({
              zone: "sell",
              action: "Sprzedano",
              entries: selectedItems,
              total,
              currencyCode: this.activeSettlementCurrencyCode,
            });
            this.persistTradingData();
            this.walletAlert = "";
          } catch (error) {
            runtime.clearShopNotificationZone("sell");
            runtime.notifyTradeProblem({
              zone: "sell",
              title: "Transakcja odrzucona",
              message: this.resolveTradeApiAlert(error),
            });
          } finally {
            this.sellTransactionPending = false;
          }
          return;
        }
        runtime.clearShopNotificationZone("sell");
        runtime.applySellToUi(this, selectedItems, total);
        runtime.notifyTradeSuccess({
          zone: "sell",
          action: "Sprzedano",
          entries: selectedItems,
          total,
          currencyCode: this.activeSettlementCurrencyCode,
        });
        this.persistTradingData();
        this.walletAlert = "";
        return;
      }
      if (this.gmMode === runtime.GM_MODES.TEMPLATES) {
        const normalized = this.normalizeTemplateForm(this.templateForm);
        if (!normalized.valid) {
          if (typeof this.setFormErrors === "function") {
            this.setFormErrors("templateEdit", normalized.errors || {});
          }
          this.showWalletAlert(normalized.message, {
            zone: "sell",
            type: "error",
            title: "Błąd walidacji",
          });
          return;
        }
        if (typeof this.setFormErrors === "function") {
          this.setFormErrors("templateEdit", {});
        }
        const saved =
          typeof this.saveTemplateRecord === "function"
            ? await this.saveTemplateRecord(normalized.item)
            : null;
        if (!saved) {
          this.showWalletAlert(runtime.t("shop.alerts.templateSaveFailed"), {
            zone: "sell",
            type: "error",
            title: "Błąd zapisu",
          });
          return;
        }
        this.clearSelections();
        this.resetEditState();
        this.showWalletAlert(runtime.t("shop.alerts.templateSaved"), {
          zone: "sell",
          type: "success",
          title: "Zapisano",
        });
        return;
      } else if (this.gmMode === runtime.GM_MODES.TRASH) {
        if (this.selectedTrashId) {
          this.removeTrashItem(this.selectedTrashId);
          this.showWalletAlert(runtime.t("shop.alerts.trashDeletedPermanent"));
        }
      } else if (this.gmMode === runtime.GM_MODES.INVENTORY) {
        if (this.selectedInventory) {
          const item = {
            ...this.selectedInventory,
          };
          const trashItem = this.createTrashItemFromInventory(item);
          if (!trashItem) {
            return;
          }
          this.addTrashItem(trashItem);
          this.removeInventoryItems([item.ID]);
          this.showWalletAlert(runtime.t("shop.alerts.inventoryMovedToTrash"));
        }
      }
      this.clearSelections();
      this.resetEditState();
      this.persistTradingData();
    },
  };
};
