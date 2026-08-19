export const createContainersMethodsPart6 = (runtime) => {
  return {
    handleGmMove() {
      const option = this.gmMoveItemOptions.find(
        (opt) => opt.optionKey === this.gmMoveItemKey,
      );
      if (!option) {
        this.showWalletAlert(runtime.t("shop.alerts.selectItem"), {
          zone: "buy",
          type: "warning",
        });
        return;
      }
      if (!this.gmMoveTargetContainerId) {
        this.showWalletAlert(runtime.t("shop.alerts.selectTargetContainer"), {
          zone: "buy",
          type: "warning",
        });
        return;
      }
      if (Number(option.containerId) === Number(this.gmMoveTargetContainerId)) {
        this.showWalletAlert(runtime.t("shop.alerts.itemAlreadyInContainer"), {
          zone: "buy",
          type: "info",
        });
        return;
      }
      const movementStart = this.containerState.itemMovements.length;
      const actions = [];
      if (option.type === "template") {
        const action = this.buildTemplateUndoAction(
          option.templateId,
          option.containerId,
          this.gmMoveTargetContainerId,
        );
        const item = this.containerItemsFor(option.containerId).find(
          (entry) => entry.key === option.itemKey,
        );
        const quantity = this.normalizeGmMoveQuantity(item);
        if (quantity === 0 && quantity !== null) {
          return;
        }
        actions.push(action);
        runtime.moveTemplateStack(
          this.containerState,
          option.templateId,
          option.containerId,
          this.gmMoveTargetContainerId,
          quantity,
          "gmMove",
        );
      } else {
        const action = this.buildInstanceUndoAction(option.instanceId);
        actions.push(action);
        runtime.moveInstance(
          this.containerState,
          option.instanceId,
          this.gmMoveTargetContainerId,
          "gmMove",
        );
      }
      const movementCount =
        this.containerState.itemMovements.length - movementStart;
      if (actions.length) {
        this.containerUndoStack.push({
          kind: "group",
          actions,
          movementCount,
        });
      }
      this.gmMoveQuantity = 1;
      this.syncContainerStateToStore();
      runtime.notifyContainerInfo({
        zone: "buy",
        type: "success",
        title: "Transfer zakończony",
        message: `Przeniesiono: ${runtime.itemLabel(option)}`,
      });
    },
    handleShopBuy() {
      if (!this.shopBuyContainerId || !this.shopBuyTargetContainerId) {
        this.showWalletAlert(runtime.t("shop.alerts.selectShopAndReceiver"), {
          zone: "sell",
          type: "warning",
        });
        return;
      }
      const selected = this.shopBuySelectedItem;
      if (!selected) {
        this.showWalletAlert(runtime.t("shop.alerts.selectGoods"), {
          zone: "sell",
          type: "warning",
        });
        return;
      }
      const movementStart = this.containerState.itemMovements.length;
      const actions = [];
      if (selected.type === "instance") {
        const action = this.buildInstanceUndoAction(selected.instanceId);
        actions.push(action);
        runtime.moveInstance(
          this.containerState,
          selected.instanceId,
          this.shopBuyTargetContainerId,
          "buyFromShop",
        );
      } else {
        const template = this.getTemplateById(selected.templateId);
        const quantity = this.normalizeShopBuyQuantity(selected);
        if (quantity <= 0) {
          this.showWalletAlert(runtime.t("shop.alerts.noAvailableQuantity"), {
            zone: "sell",
            type: "error",
          });
          return;
        }
        const shopPrev = this.snapshotTemplateStack(
          this.shopBuyContainerId,
          selected.templateId,
        );
        const playerPrev = this.snapshotTemplateStack(
          this.shopBuyTargetContainerId,
          selected.templateId,
        );
        const beforeIds = new Set(
          this.containerState.itemInstances.map((entry) => Number(entry.id)),
        );
        runtime.buyFromShop(
          this.containerState,
          this.shopBuyTargetContainerId,
          this.shopBuyContainerId,
          {
            templateId: selected.templateId,
            quantity,
          },
        );
        const createdInstanceIds = this.containerState.itemInstances
          .filter((entry) => !beforeIds.has(Number(entry.id)))
          .map((entry) => entry.id);
        if (template?.isStackable) {
          actions.push({
            kind: "template",
            templateId: selected.templateId,
            fromContainerId: this.shopBuyContainerId,
            toContainerId: this.shopBuyTargetContainerId,
            fromState: shopPrev,
            toState: playerPrev,
          });
        } else {
          actions.push({
            kind: "instance-create",
            templateId: selected.templateId,
            shopContainerId: this.shopBuyContainerId,
            shopPrevState: shopPrev,
            createdInstanceIds,
          });
        }
      }
      const movementCount =
        this.containerState.itemMovements.length - movementStart;
      if (actions.length) {
        this.containerUndoStack.push({
          kind: "group",
          actions,
          movementCount,
        });
      }
      this.shopBuyQuantity = 1;
      this.shopBuyItemKey = "";
      this.syncContainerStateToStore();
      runtime.notifyContainerInfo({
        zone: "sell",
        type: "success",
        title: "Transfer zakończony",
        message: `Kupiono: ${runtime.itemLabel(selected)}`,
      });
    },
    undoContainerAction() {
      const entry = this.containerUndoStack.pop();
      if (!entry) {
        return;
      }
      if (entry.movementCount) {
        this.containerState.itemMovements.splice(-entry.movementCount);
      }
      this.applyUndoEntry(entry);
      this.syncContainerStateToStore();
    },
    applyUndoEntry(entry) {
      const actions = entry.kind === "group" ? entry.actions : [entry];
      actions.forEach((action) => {
        if (action.kind === "template") {
          this.restoreTemplateStack(
            action.fromContainerId,
            action.templateId,
            action.fromState,
          );
          this.restoreTemplateStack(
            action.toContainerId,
            action.templateId,
            action.toState,
          );
          return;
        }
        if (action.kind === "instance") {
          this.restoreInstanceEntry(action.instanceId, action.prevEntry);
          return;
        }
        if (action.kind === "instance-create") {
          this.removeInstances(action.createdInstanceIds || []);
          this.restoreTemplateStack(
            action.shopContainerId,
            action.templateId,
            action.shopPrevState,
          );
        }
      });
    },
    snapshotTemplateStack(containerId, templateId) {
      if (!containerId) {
        return null;
      }
      const entry = this.getTemplateStackEntry(containerId, templateId);
      if (!entry) {
        return null;
      }
      return {
        quantity: entry.quantity,
        priceOverride: entry.priceOverride ?? null,
      };
    },
    restoreTemplateStack(containerId, templateId, snapshot) {
      if (!containerId) {
        return;
      }
      const list = this.containerState.containerTemplateItems;
      const index = list.findIndex(
        (row) =>
          Number(row.containerId) === Number(containerId) &&
          Number(row.templateId) === Number(templateId),
      );
      if (!snapshot) {
        if (index >= 0) {
          list.splice(index, 1);
        }
        return;
      }
      if (index >= 0) {
        list[index].quantity = snapshot.quantity;
        list[index].priceOverride = snapshot.priceOverride ?? null;
        return;
      }
      list.push({
        containerId,
        templateId,
        quantity: snapshot.quantity,
        priceOverride: snapshot.priceOverride ?? null,
      });
    },
  };
};
