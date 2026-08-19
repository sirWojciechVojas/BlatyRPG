export const createContainersMethodsPart4 = (runtime) => {
  return {
    setAssortmentMergeChoice(payload) {
      const fieldKey = String(payload?.fieldKey || "");
      const source = String(payload?.source || "").toLowerCase();
      if (!runtime.ASSORTMENT_MERGE_FIELDS.includes(fieldKey)) {
        return;
      }
      if (source !== "left" && source !== "right") {
        return;
      }
      this.assortmentMergeChoices = {
        ...(this.assortmentMergeChoices || {}),
        [fieldKey]: source,
      };
    },
    openAssortmentMergeDialog() {
      if (!this.canMergeAssortmentSelection) {
        this.showWalletAlert(runtime.t("shop.alerts.selectTwoSimilarForMerge"));
        return;
      }
      const [leftItem, rightItem] = this.assortmentMergeSelectedItems;
      const leftData = this.resolveAssortmentMergeItemData(leftItem);
      const rightData = this.resolveAssortmentMergeItemData(rightItem);
      if (!leftData || !rightData) {
        this.showWalletAlert(runtime.t("shop.alerts.mergePrepareFailed"));
        return;
      }
      const initialChoices = {};
      runtime.ASSORTMENT_MERGE_FIELDS.forEach((fieldKey) => {
        const leftValue = JSON.stringify(leftData[fieldKey]);
        const rightValue = JSON.stringify(rightData[fieldKey]);
        initialChoices[fieldKey] = leftValue === rightValue ? "left" : "left";
      });
      this.assortmentMergeContainerId = Number(this.assortmentRightContainerId);
      this.assortmentMergeLeftInstanceId = Number(leftData.instanceId);
      this.assortmentMergeRightInstanceId = Number(rightData.instanceId);
      this.assortmentMergeLeftItem = leftData;
      this.assortmentMergeRightItem = rightData;
      this.assortmentMergeChoices = initialChoices;
      this.showAssortmentMergeDialog = true;
    },
    closeAssortmentMergeDialog() {
      this.showAssortmentMergeDialog = false;
      this.assortmentMergeContainerId = null;
      this.assortmentMergeLeftInstanceId = null;
      this.assortmentMergeRightInstanceId = null;
      this.assortmentMergeLeftItem = null;
      this.assortmentMergeRightItem = null;
      this.assortmentMergeChoices = {};
    },
    confirmAssortmentMergeDialog() {
      if (!this.showAssortmentMergeDialog) {
        return;
      }
      const leftInstanceId = Number(this.assortmentMergeLeftInstanceId);
      const rightInstanceId = Number(this.assortmentMergeRightInstanceId);
      if (
        !Number.isFinite(leftInstanceId) ||
        !Number.isFinite(rightInstanceId)
      ) {
        this.closeAssortmentMergeDialog();
        return;
      }
      const leftInstance = this.getInstanceById(leftInstanceId);
      const rightInstance = this.getInstanceById(rightInstanceId);
      if (!leftInstance || !rightInstance) {
        this.showWalletAlert(runtime.t("shop.alerts.mergeItemsMissing"));
        this.closeAssortmentMergeDialog();
        return;
      }
      const leftMeta = this.cloneStoreItem(
        this.containerInstanceMeta[leftInstanceId] || {},
      );
      const rightMeta = this.cloneStoreItem(
        this.containerInstanceMeta[rightInstanceId] || {},
      );
      const leftData = this.resolveAssortmentMergeItemData({
        type: "instance",
        instanceId: leftInstanceId,
      });
      const rightData = this.resolveAssortmentMergeItemData({
        type: "instance",
        instanceId: rightInstanceId,
      });
      if (!leftData || !rightData) {
        this.showWalletAlert(runtime.t("shop.alerts.mergeReadFailed"));
        this.closeAssortmentMergeDialog();
        return;
      }
      const resolved = {};
      runtime.ASSORTMENT_MERGE_FIELDS.forEach((fieldKey) => {
        const source = this.assortmentMergeChoiceFor(fieldKey);
        resolved[fieldKey] =
          source === "right" ? rightData[fieldKey] : leftData[fieldKey];
      });
      const mergedItemPlace = String(
        runtime.normalizeMergeValue(
          resolved.ITEM_PLACE ?? resolved.SLOT,
          leftData.ITEM_PLACE ?? leftData.SLOT,
        ),
      );
      const totalQuantity =
        Math.max(1, Number(leftData.QUANTITY || 1)) +
        Math.max(1, Number(rightData.QUANTITY || 1));
      this.containerInstanceMeta[leftInstanceId] = this.cloneStoreItem({
        ...leftMeta,
        ...rightMeta,
        INV_ID: leftData.templateId,
        OWNER_OPT: "DEFAULT",
        OWNER: "BG1",
        ...resolved,
        ITEM_PLACE: mergedItemPlace,
        SLOT: mergedItemPlace,
        QUANTITY: totalQuantity,
      });
      leftInstance.nameOverride = String(
        runtime.normalizeMergeValue(resolved.NAME, leftData.NAME),
      );
      leftInstance.note = String(
        runtime.normalizeMergeValue(
          resolved.PERSONAL_DESC,
          leftData.PERSONAL_DESC,
        ),
      );
      leftInstance.templateId = Number(leftData.templateId);
      this.removeInstances([rightInstanceId]);
      if (this.containerInstanceMeta[rightInstanceId]) {
        delete this.containerInstanceMeta[rightInstanceId];
      }
      this.assortmentRightSelectedKeys = [`i:${leftInstanceId}`];
      this.closeAssortmentMergeDialog();
      this.syncContainerStateToStore();
      this.showWalletAlert(runtime.t("shop.alerts.mergeSuccess"));
    },
    containerDisplayName(container) {
      if (!container) {
        return runtime.t("shop.containers.unknownContainer");
      }
      if (container.type === "SYSTEM") {
        if (container.systemKey === runtime.SYSTEM_CONTAINER_KEYS.DEFAULT) {
          return runtime.t(runtime.TRADE_DEFAULT_STACK_UI_LABEL);
        }
        if (container.systemKey === runtime.SYSTEM_CONTAINER_KEYS.TRASH) {
          return runtime.t("shop.containers.generalTrash");
        }
        return container.systemKey
          ? `${container.systemKey} (${container.name})`
          : container.name;
      }
      if (container.type === "TRASH") {
        const actor = this.containerActorsMap[Number(container.actorId)];
        if (actor) {
          return runtime.t("shop.containers.playerTrash", {
            ownerCode: actor.code,
            ownerLabel: actor.name || actor.code,
            suffix: runtime.t("shop.trashView.discardZoneSuffix"),
          });
        }
      }
      if (container.type === "CHARACTER") {
        const actor = this.containerActorsMap[Number(container.actorId)];
        if (actor) {
          return `${actor.code} - ${actor.name}`;
        }
      }
      if (container.type === "SHOP") {
        const shop = this.containerShopsMap[Number(container.shopId)];
        if (shop) {
          return `${shop.code} - ${shop.name}`;
        }
      }
      return (
        container.name ||
        runtime.t("shop.containers.containerFallback", {
          id: container.id,
        })
      );
    },
    containerLabelById(containerId) {
      const entry = this.containerSelectOptions.find(
        (opt) => Number(opt.id) === Number(containerId),
      );
      return entry?.label || runtime.t("shop.containers.genericContainer");
    },
    containerById(containerId) {
      return (this.containerState?.containers || []).find(
        (container) => Number(container.id) === Number(containerId),
      );
    },
  };
};
