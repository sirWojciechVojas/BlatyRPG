export const createContentMethodsPart5 = () => ({
  pickerPreviewIconStyle(imgClass) {
    const style = this.pickerIconStyle(imgClass, 144);
    if (!/^v\d{4}$/i.test(String(imgClass || ""))) {
      return style;
    }
    return {
      ...style,
      backgroundImage: "var(--trade-inventory-sprite-detail)",
      backgroundSize: "calc(20 * var(--trade-icon-size)) auto",
      backgroundRepeat: "no-repeat",
    };
  },
  iconTileTitle(imgClass) {
    const iconClass = this.normalizeIconClassDraft(imgClass);
    const metadata = this.getIconMetadata(iconClass);
    const sourceIconClass = this.sourceIconClassForDisplay(iconClass);
    const sourceSuffix =
      sourceIconClass && sourceIconClass !== iconClass
        ? ` / ${sourceIconClass.toUpperCase()}`
        : "";
    return `${String(iconClass || "").toUpperCase()}${sourceSuffix} - ${metadata.name}`;
  },
  normalizeIconSizeValue(value) {
    const parsed = Number(value);
    const min = Number(this.iconSizeMin);
    const max = Number(this.iconSizeMax);
    const fallback = Number(this.iconSizeDefault) || 34;
    if (!Number.isFinite(parsed)) {
      return fallback;
    }
    const safeMin = Number.isFinite(min) ? min : 24;
    const safeMax = Number.isFinite(max) ? max : 64;
    return Math.max(safeMin, Math.min(safeMax, Math.round(parsed)));
  },
  syncViewSettingsIconSizeDraft() {
    this.viewSettingsIconSizeDraft = this.normalizeIconSizeValue(
      this.iconSizeModel,
    );
  },
  setViewSettingsIconSize(value) {
    this.viewSettingsIconSizeDraft = this.normalizeIconSizeValue(value);
  },
  closeViewSettingsDialog() {
    this.$emit("close-view-settings-dialog");
  },
  saveViewSettingsDialog() {
    this.setIconSize(this.viewSettingsIconSizeDraft);
    this.closeViewSettingsDialog();
  },
  setIconSize(value) {
    this.$emit("update:iconSize", this.normalizeIconSizeValue(value));
  },
  setAssortmentLeftTab(value) {
    this.$emit("update:assortmentLeftTab", value);
  },
  setAssortmentRightTab(value) {
    this.$emit("update:assortmentRightTab", value);
  },
  handleShopEditorFieldUpdate(field, value) {
    if (typeof this.updateShopEditorField !== "function") {
      return;
    }
    if (field && typeof field === "object" && !Array.isArray(field)) {
      this.updateShopEditorField(field);
      return;
    }
    this.updateShopEditorField({
      field,
      value,
    });
  },
  handleRollShopSignboard() {
    if (typeof this.rollShopSignboard !== "function") {
      return;
    }
    this.rollShopSignboard();
  },
  handleShopEditorShopChange(value) {
    if (typeof this.changeShopForEditor !== "function") {
      return;
    }
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
      return;
    }
    this.changeShopForEditor(parsed);
  },
  handleCreateShopForEditor() {
    if (typeof this.createShopForEditor !== "function") {
      return;
    }
    this.createShopForEditor();
  },
  handleDeleteActiveShopForEditor() {
    if (typeof this.deleteActiveShopForEditor !== "function") {
      return;
    }
    this.deleteActiveShopForEditor();
  },
  handleToggleShopSuggestion(suggestionId) {
    if (typeof this.toggleShopSuggestion !== "function") {
      return;
    }
    this.toggleShopSuggestion({
      suggestionId,
    });
  },
  handleSetAssortmentRollTarget(value) {
    if (typeof this.setAssortmentRollTarget !== "function") {
      return;
    }
    this.setAssortmentRollTarget(value);
  },
  handleSetGmMoveQuantity(value) {
    if (typeof this.setGmMoveQuantity !== "function") {
      return;
    }
    this.setGmMoveQuantity(value);
  },
  handleRollShopStarterAssortment() {
    if (typeof this.rollShopStarterAssortment !== "function") {
      return;
    }
    this.rollShopStarterAssortment();
  },
  handlePreviewShopStarterAssortment() {
    if (typeof this.previewShopStarterAssortment !== "function") {
      return;
    }
    this.previewShopStarterAssortment();
  },
  handleApplyAssortmentRollPreview() {
    if (typeof this.applyAssortmentRollPreview !== "function") {
      return;
    }
    this.applyAssortmentRollPreview();
  },
  handleCreateDraftTemplatesFromSelected() {
    if (typeof this.createDraftTemplatesFromSelected !== "function") {
      return;
    }
    this.createDraftTemplatesFromSelected();
  },
  suggestionDisplayName(entry) {
    if (!entry || typeof entry !== "object") {
      return this.$t("shop.suggestions.fallbackItemName");
    }
    return (
      entry.displayName ||
      entry.templateName ||
      entry.label ||
      entry.draftTemplate?.NAME ||
      this.$t("shop.suggestions.templateFallback", {
        id: entry.templateId || "?",
      })
    );
  },
  suggestionDescription(entry) {
    const direct = String(
      entry?.description || entry?.draftTemplate?.DESCRIPTION || "",
    ).trim();
    if (direct) {
      return direct;
    }
    const itemClass = String(entry?.classKey || "").toUpperCase();
    const itemGenre = String(entry?.genreKey || "").toUpperCase();
    if (itemClass || itemGenre) {
      return `${this.$t("shop.common.classPrefix")} ${itemClass || "-"} / ${this.$t("shop.common.genrePrefix")} ${itemGenre || "-"}`;
    }
    return this.$t("shop.suggestions.fallbackProfileDescription");
  },
  suggestionExamplesText(entry) {
    const examples = Array.isArray(entry?.examples) ? entry.examples : [];
    if (!examples.length) {
      return "";
    }
    return examples.join(", ");
  },
  suggestionTotalUnits() {
    return (this.shopSuggestions || []).reduce(
      (sum, entry) => sum + Math.max(1, Number(entry?.quantity || 1)),
      0,
    );
  },
  suggestionIconClass(entry) {
    const payload = {
      IMG_CLASS:
        entry?.imgClass ||
        entry?.IMG_CLASS ||
        entry?.draftTemplate?.IMG_CLASS ||
        "v0001",
    };
    return this.legacyIconClassForItem(payload);
  },
  recommendationLabel(entry) {
    const code = String(entry?.recommendationCode || "").toLowerCase();
    if (code === "add") {
      return this.$t("shop.suggestions.recommendationAdd");
    }
    if (code === "consider") {
      return this.$t("shop.suggestions.recommendationConsider");
    }
    if (code === "skip") {
      return this.$t("shop.suggestions.recommendationSkip");
    }
    return String(
      entry?.recommendationLabelPl ||
        this.$t("shop.suggestions.recommendationRating"),
    ).trim();
  },
});
