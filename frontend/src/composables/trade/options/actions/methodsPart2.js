export const createActionsMethodsPart2 = (runtime) => {
  return {
    async handleBuyAction() {
      if (this.buyTransactionPending || this.sellTransactionPending) {
        return;
      }
      if (this.isGM && this.gmMode === runtime.GM_MODES.TRASH) {
        this.handleRestoreFromTrash();
        return;
      }
      if (
        this.showBuyForm &&
        this.isGM &&
        this.gmMode !== runtime.GM_MODES.TEMPLATES
      ) {
        const normalized = this.normalizeInventoryForm(this.inventoryForm);
        if (!normalized.valid) {
          if (typeof this.setFormErrors === "function") {
            this.setFormErrors("inventoryEdit", normalized.errors || {});
          }
          this.showWalletAlert(normalized.message, {
            zone: "buy",
            type: "error",
            title: "Błąd walidacji",
          });
          return;
        }
        if (typeof this.setFormErrors === "function") {
          this.setFormErrors("inventoryEdit", {});
        }
        if (this.gmMode === runtime.GM_MODES.TRASH) {
          this.updateTrashItem(normalized.item);
        } else if (this.gmMode === runtime.GM_MODES.INVENTORY) {
          this.updateInventoryItem(normalized.item);
        }
        this.clearSelections();
        this.resetEditState();
        this.persistTradingData();
        return;
      }
      if (!this.buyHasSelection) {
        return;
      }
      if (!this.isGM) {
        const totalCost = this.buyTotalBrass;
        const selectedItems = [...this.selectedBuyIds]
          .map((id) => {
            const item = this.buyItems.find(
              (entry) => Number(entry.ID) === Number(id),
            );
            if (!item) {
              return null;
            }
            const max = Math.max(1, Number(item.QUANTITY || 1));
            const requested = Number(this.selectedBuyQuantities?.[id]);
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
          runtime.clearShopNotificationZone("buy");
          runtime.notifyTradeProblem({
            zone: "buy",
            title: "Brak towaru",
            message: "Wybrany przedmiot zniknął z lady.",
          });
          return;
        }
        const selectedCharge = selectedItems.reduce(
          (total, { item, quantity }) =>
            total + this.resolveItemCharge(item) * quantity,
          0,
        );
        const projectedCharge = this.bgEncumbranceCurrent + selectedCharge;
        const blockingProblems = [];
        if (projectedCharge > this.bgEncumbranceLimit) {
          blockingProblems.push({
            zone: "buy",
            type: "warning",
            title: "Kupiec odmawia",
            message: "Przekroczono udźwig",
            details: runtime.t("shop.alerts.overweight", {
              current: projectedCharge,
              limit: this.bgEncumbranceLimit,
              unit: this.bgEncumbranceUnitShort,
            }),
          });
        }
        if (blockingProblems.length) {
          runtime.clearShopNotificationZone("buy");
          blockingProblems.forEach((problem) =>
            runtime.notifyTradeProblem(problem),
          );
          return;
        }
        if (typeof this.playerBuyFromShop === "function") {
          const selections = selectedItems.map(({ item, quantity }) => ({
            templateId: Number(item.INV_ID ?? item.ID),
            instanceId:
              Array.isArray(item.AGGREGATED_ITEM_IDS) &&
              (item.AGGREGATED_ITEM_IDS.length > 1 ||
                !item.IS_ITEM_INSTANCE ||
                quantity > 1)
                ? 0
                : Number(item.ID),
            quantity,
            clientId: Number(item.ID),
          }));
          if (totalCost > this.bgWalletBrass) {
            await this.openPaymentConversionDialog(
              selectedItems,
              selections,
              totalCost,
            );
          } else {
            await this.submitPlayerBuy(selectedItems, selections, totalCost);
          }
          return;
        }
        runtime.clearShopNotificationZone("buy");
        runtime.applyBuyToUi(this, selectedItems, totalCost);
        runtime.notifyTradeSuccess({
          zone: "buy",
          action: "Kupiono",
          entries: selectedItems,
          total: totalCost,
          currencyCode: this.activeSettlementCurrencyCode,
        });
        this.persistTradingData();
        this.walletAlert = "";
        return;
      }
      if (!this.selectedTemplate) {
        return;
      }
      if (this.gmMode === runtime.GM_MODES.TEMPLATES) {
        const item = {
          ...this.selectedTemplate,
        };
        this.addInventoryStackItem({
          ...item,
          ID: this.nextInventoryId(),
          INV_ID: item.ID,
          ITEM_PLACE: "STOS",
          SLOT: "STOS",
          PERSONAL_PSEU: "Sklonowany",
          PERSONAL_DESC: item.DESCRIPTION,
          PERSONAL_COST: 0,
          QUANTITY: 1,
          OWNER_OPT: runtime.OWNER_CODES.DEFAULT,
        });
      } else if (this.gmMode === runtime.GM_MODES.TRASH) {
        const item = {
          ...this.selectedTemplate,
        };
        const trashItem = this.createTrashItemFromTemplate(item);
        if (trashItem) {
          this.addTrashItem(trashItem);
        }
      } else if (this.gmMode === runtime.GM_MODES.INVENTORY) {
        const item = {
          ...this.selectedTemplate,
        };
        this.addInventoryStackItem({
          ...item,
          ID: this.nextInventoryId(),
          INV_ID: item.ID,
          ITEM_PLACE: "STOS",
          SLOT: "STOS",
          PERSONAL_PSEU: "Spersonalizowany",
          PERSONAL_DESC: item.DESCRIPTION,
          PERSONAL_COST: 0,
          QUANTITY: 1,
          OWNER_OPT: runtime.OWNER_CODES.DEFAULT,
        });
      }
      this.clearSelections();
      this.resetEditState();
      this.persistTradingData();
    },
    async openPaymentConversionDialog(
      selectedItems,
      selections,
      totalCost,
      selectedCurrencyCodes,
    ) {
      if (typeof this.playerQuoteBuyPayment !== "function") {
        return;
      }
      this.paymentQuotePending = true;
      this.pendingPaymentPurchase = {
        ownerCode: this.activeBgOwner,
        shopId: this.activeShopId,
        selectedItems,
        selections,
        totalCost,
      };
      try {
        const result = await this.playerQuoteBuyPayment({
          ownerCode: this.activeBgOwner,
          shopId: this.activeShopId,
          selections,
          ...(selectedCurrencyCodes !== undefined
            ? { selectedCurrencyCodes }
            : {}),
        });
        if (!result?.ok) {
          this.closePaymentConversionDialog();
          runtime.notifyTradeProblem({
            zone: "buy",
            title: "Płatność odrzucona",
            message: this.resolveTradeApiAlert(result?.error),
          });
          return;
        }
        this.paymentQuote = result.data?.paymentQuote || {};
        this.showPaymentConversionDialog = true;
      } finally {
        this.paymentQuotePending = false;
      }
    },
    async togglePaymentCurrency(currencyCode) {
      const pending = this.pendingPaymentPurchase;
      if (!pending || this.paymentQuotePending) {
        return;
      }
      const selected = new Set(this.paymentQuote?.selectedCurrencyCodes || []);
      if (selected.has(currencyCode)) {
        selected.delete(currencyCode);
      } else {
        selected.add(currencyCode);
      }
      await this.openPaymentConversionDialog(
        pending.selectedItems,
        pending.selections,
        pending.totalCost,
        [...selected],
      );
    },
    closePaymentConversionDialog() {
      this.showPaymentConversionDialog = false;
      this.paymentQuote = {};
      this.pendingPaymentPurchase = null;
    },
    async confirmPaymentConversion() {
      const pending = this.pendingPaymentPurchase;
      const quote = this.paymentQuote;
      if (!pending || !quote?.canPay || this.paymentQuotePending) {
        return;
      }
      if (
        pending.ownerCode !== this.activeBgOwner ||
        Number(pending.shopId) !== Number(this.activeShopId)
      ) {
        this.closePaymentConversionDialog();
        return;
      }
      await this.submitPlayerBuy(
        pending.selectedItems,
        pending.selections,
        pending.totalCost,
        {
          quoteFingerprint: quote.quoteFingerprint,
          selectedCurrencyCodes: quote.selectedCurrencyCodes || [],
        },
      );
    },
    async submitPlayerBuy(
      selectedItems,
      selections,
      totalCost,
      payment = null,
    ) {
      this.buyTransactionPending = true;
      runtime.clearShopNotificationZone("buy");
      try {
        const apiResult = await this.playerBuyFromShop({
          ownerCode: this.activeBgOwner,
          shopId: this.activeShopId,
          selections,
          ...(payment ? { payment } : {}),
          encumbrance: {
            current: this.bgEncumbranceCurrent,
            limit: this.bgEncumbranceLimit,
            unit: this.bgEncumbranceUnitShort,
          },
        });
        if (apiResult?.handled && !apiResult.ok) {
          const freshQuote = apiResult.error?.payload?.paymentQuote;
          if (freshQuote) {
            this.pendingPaymentPurchase = {
              ownerCode: this.activeBgOwner,
              shopId: this.activeShopId,
              selectedItems,
              selections,
              totalCost,
            };
            this.paymentQuote = freshQuote;
            this.showPaymentConversionDialog = true;
          }
          runtime.notifyTradeProblem({
            zone: "buy",
            title: "Transakcja odrzucona",
            message: this.resolveTradeApiAlert(apiResult.error),
          });
          return;
        }
        if (!apiResult?.handled) {
          runtime.applyBuyToUi(this, selectedItems, totalCost);
        }
        this.closePaymentConversionDialog();
        runtime.notifyTradeSuccess({
          zone: "buy",
          action: "Kupiono",
          entries: selectedItems,
          total: totalCost,
          currencyCode: this.activeSettlementCurrencyCode,
        });
        this.persistTradingData();
        this.walletAlert = "";
      } catch (error) {
        runtime.notifyTradeProblem({
          zone: "buy",
          title: "Transakcja odrzucona",
          message: this.resolveTradeApiAlert(error),
        });
      } finally {
        this.buyTransactionPending = false;
      }
    },
  };
};
