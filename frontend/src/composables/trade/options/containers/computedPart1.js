export const createContainersComputedPart1 = (runtime) => {
  return {
    isAssortmentMode() {
      return this.isGM && this.gmMode === "assortment";
    },
    isAssortmentToolsMode() {
      return this.isGM && this.gmMode === "assortmentTools";
    },
    containerActorsMap() {
      return (this.containerState?.actors || []).reduce((acc, actor) => {
        acc[Number(actor.id)] = actor;
        return acc;
      }, {});
    },
    containerShopsMap() {
      return (this.containerState?.shops || []).reduce((acc, shop) => {
        acc[Number(shop.id)] = shop;
        return acc;
      }, {});
    },
    containerSelectOptions() {
      const containers = this.containerState?.containers || [];
      const typeOrder = {
        SYSTEM: 0,
        TRASH: 1,
        CHARACTER: 2,
        SHOP: 3,
      };
      return [...containers]
        .sort((a, b) => {
          const typeDiff = (typeOrder[a.type] ?? 9) - (typeOrder[b.type] ?? 9);
          if (typeDiff !== 0) {
            return typeDiff;
          }
          return Number(a.id) - Number(b.id);
        })
        .map((container) => ({
          id: container.id,
          type: container.type,
          label: this.containerDisplayName(container),
          container,
        }));
    },
    shopContainerOptions() {
      return this.containerSelectOptions.filter(
        (entry) => entry.type === "SHOP",
      );
    },
    characterContainerOptions() {
      return this.containerSelectOptions.filter(
        (entry) => entry.type === "CHARACTER",
      );
    },
    containerOverview() {
      return this.containerSelectOptions.map((entry) => ({
        ...entry,
        items: this.containerItemsFor(entry.id),
      }));
    },
    assortmentLeftItems() {
      return this.containerItemsFor(this.assortmentLeftContainerId);
    },
    assortmentRightItems() {
      return this.containerItemsFor(this.assortmentRightContainerId);
    },
    assortmentMergeSelectedItems() {
      const items = this.containerItemsFor(this.assortmentRightContainerId);
      return (this.assortmentRightSelectedKeys || [])
        .map((key) => items.find((item) => item.key === key))
        .filter(Boolean);
    },
    canMergeAssortmentSelection() {
      if (!this.isAssortmentMode) {
        return false;
      }
      const container = this.containerById(this.assortmentRightContainerId);
      if (container?.type !== "SHOP") {
        return false;
      }
      if (this.assortmentMergeSelectedItems.length !== 2) {
        return false;
      }
      const [left, right] = this.assortmentMergeSelectedItems;
      if (left?.type !== "instance" || right?.type !== "instance") {
        return false;
      }
      return (
        String(left.stackCandidateKey || "") &&
        String(left.stackCandidateKey || "") ===
          String(right.stackCandidateKey || "")
      );
    },
    assortmentMergeFieldDefinitions() {
      return runtime.ASSORTMENT_MERGE_FIELDS;
    },
    gmMoveItemOptions() {
      return this.containerOverview.flatMap((entry) =>
        entry.items.map((item) => ({
          optionKey: `${entry.id}:${item.key}`,
          containerId: entry.id,
          itemKey: item.key,
          type: item.type,
          templateId: item.templateId,
          instanceId: item.instanceId,
          quantity: item.quantity,
          label: `${item.name} (${entry.label})`,
        })),
      );
    },
    gmMoveSelectedItem() {
      return (
        this.gmMoveItemOptions.find(
          (entry) => entry.optionKey === this.gmMoveItemKey,
        ) || null
      );
    },
    gmMoveQuantityEnabled() {
      return this.gmMoveSelectedItem?.type === "template";
    },
    gmMoveQuantityMax() {
      const selected = this.gmMoveSelectedItem;
      if (!selected || selected.type !== "template") {
        return 1;
      }
      return Math.max(1, Number(selected.quantity || 1));
    },
    shopBuyItemOptions() {
      return this.containerItemsFor(this.shopBuyContainerId).map((item) => ({
        key: item.key,
        type: item.type,
        templateId: item.templateId,
        instanceId: item.instanceId,
        label: item.quantityLabel
          ? `${item.name} (${item.quantityLabel})`
          : item.name,
        quantity: item.quantity,
      }));
    },
    shopBuySelectedItem() {
      return (
        this.shopBuyItemOptions.find(
          (item) => item.key === this.shopBuyItemKey,
        ) || null
      );
    },
    shopBuyQuantityEnabled() {
      return this.shopBuySelectedItem?.type === "template";
    },
  };
};
