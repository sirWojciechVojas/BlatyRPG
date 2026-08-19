export const createContainersWatchPart1 = () => {
  return {
    assortmentLeftContainerId() {
      this.assortmentLeftSelectedKeys = [];
    },
    assortmentRightContainerId() {
      this.assortmentRightSelectedKeys = [];
      this.closeAssortmentMergeDialog();
      this.resetAssortmentSuggestionState?.();
    },
    shopBuyContainerId() {
      this.shopBuyItemKey = "";
      this.shopBuyQuantity = 1;
    },
    gmMoveItemKey() {
      this.gmMoveQuantity = 1;
    },
  };
};
