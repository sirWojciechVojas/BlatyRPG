export const createContentMethodsPart6 = () => ({
  recommendationBadgeClass(entry) {
    const code = String(entry?.recommendationCode || "").toLowerCase();
    if (code === "add") {
      return "bg-success";
    }
    if (code === "consider") {
      return "bg-warning text-dark";
    }
    if (code === "skip") {
      return "bg-secondary";
    }
    return "bg-dark";
  },
  resolveShopSuggestionReason(entry) {
    if (typeof this.shopSuggestionReasonText !== "function") {
      return "";
    }
    return this.shopSuggestionReasonText(entry);
  },
  suggestionDetailReasonLines(entry) {
    if (typeof this.shopSuggestionReasonDetailsText === "function") {
      const lines = this.shopSuggestionReasonDetailsText(entry);
      if (Array.isArray(lines) && lines.length) {
        return lines;
      }
    }
    const fallback = this.resolveShopSuggestionReason(entry);
    return fallback ? [fallback] : [this.$t("shop.suggestions.noReason")];
  },
  suggestionDetailVariantOptions(entry) {
    const variants = Array.isArray(entry?.personalizedVariants)
      ? entry.personalizedVariants
      : [];
    if (variants.length) {
      return variants;
    }
    const title = this.suggestionDisplayName(entry);
    const desc = this.suggestionDescription(entry);
    return [
      {
        variantId: "default",
        personalPseu: title,
        personalDesc: desc,
        personalCost: 0,
        quantity: 1,
      },
    ];
  },
  openSuggestionDetailDialog(entry) {
    this.$emit("open-suggestion-detail-dialog", entry);
  },
  closeSuggestionDetailDialog() {
    this.$emit("close-suggestion-detail-dialog");
  },
  confirmSuggestionDetailAction(action) {
    this.$emit("confirm-suggestion-detail-action", {
      action,
      suggestionId: this.suggestionDetailEntry?.suggestionId,
      variantId: this.suggestionDetailVariantModel,
    });
  },
  handleLeftFlankAction(button) {
    this.$emit("left-flank-action", button);
  },
  handleRightFlankAction(button) {
    this.$emit("right-flank-action", button);
  },
  undoContainerAction() {
    this.$emit("undo-container-action");
  },
  toggleContainerSelection(itemKey, side) {
    this.$emit("toggle-container-selection", {
      itemKey,
      side,
    });
  },
  moveContainerSelection(direction) {
    this.$emit("move-container-selection", direction);
  },
  handleGmMove() {
    this.$emit("gm-move");
  },
  handleShopBuy() {
    this.$emit("shop-buy");
  },
  handleBuyItemClick(item) {
    this.$emit("buy-item-click", item);
  },
  handleBuyItemQuantityStep(payload) {
    this.$emit("buy-item-quantity-step", payload);
  },
  handleBuyItemQuantityInput(payload) {
    this.$emit("buy-item-quantity-set", payload);
  },
  handleSellItemClick(item) {
    this.$emit("sell-item-click", item);
  },
  handleSellItemQuantityStep(payload) {
    this.$emit("sell-item-quantity-step", payload);
  },
  handleSellItemQuantityInput(payload) {
    this.$emit("sell-item-quantity-set", payload);
  },
  handleBuyAction() {
    this.$emit("buy-action");
  },
  handleSellAction() {
    this.$emit("sell-action");
  },
  handleDeleteTemplate() {
    this.$emit("delete-template");
  },
  openClassEdit(field, target) {
    this.$emit("open-class-edit", {
      field,
      target,
    });
  },
  applyClassEdit(option) {
    this.$emit("apply-class-edit", option);
  },
  confirmClassEdit() {
    this.$emit("confirm-class-edit");
  },
  closeFieldEditDialog() {
    this.$emit("close-field-edit-dialog");
  },
  updateClassEditDraftValue(value) {
    this.$emit("update-class-edit-draft-value", value);
  },
  updateClassEditSearch(value) {
    this.$emit("update-class-edit-search", value);
  },
  applyClassEditSuggestion(value) {
    this.$emit("apply-class-edit-suggestion", value);
  },
  startTemplateCreate() {
    this.$emit("start-template-create");
  },
  resetNewTemplateForm() {
    this.$emit("reset-new-template-form");
  },
  closeClassEditDialog() {
    this.clearIconClassDragState();
    this.$emit("close-class-edit-dialog");
  },
  closeWeaponStatsDialog() {
    this.$emit("close-weapon-stats-dialog");
  },
  createWeaponStats() {
    this.$emit("create-weapon-stats");
  },
  removeWeaponStats() {
    this.$emit("remove-weapon-stats");
  },
  selectWeaponStatsItem(value) {
    this.$emit("select-weapon-stats-item", value);
  },
  updateWeaponStatsDraft(payload) {
    this.$emit("update-weapon-stats-draft", payload);
  },
  confirmWeaponStats() {
    this.$emit("confirm-weapon-stats");
  },
  selectImgClass(imgClass) {
    this.$emit("select-img-class", imgClass);
  },
  confirmImgClass() {
    this.$emit("confirm-img-class");
  },
  closeOwnerOptDialog() {
    this.$emit("close-owner-opt-dialog");
  },
  selectOwnerOpt(opt) {
    this.$emit("select-owner-opt", opt);
  },
  confirmOwnerOpt() {
    this.$emit("confirm-owner-opt");
  },
  openItemDetailDialog(item, source) {
    this.$emit("open-item-detail-dialog", {
      item,
      source,
    });
  },
  closeItemDetailDialog() {
    this.$emit("close-item-detail-dialog");
  },
  applyItemDetailNickname() {
    this.$emit("apply-item-detail-nickname");
  },
  toggleItemDetailNicknameMode() {
    this.$emit("toggle-item-detail-nickname-mode");
  },
});
