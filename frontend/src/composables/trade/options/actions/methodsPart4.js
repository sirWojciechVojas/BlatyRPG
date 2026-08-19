export const createActionsMethodsPart4 = (runtime) => {
  return {
    resetEditState() {
      this.templateForm = this.selectedTemplate
        ? {
            ...this.selectedTemplate,
          }
        : {};
      this.inventoryForm = this.selectedSellEditItem
        ? {
            ...this.selectedSellEditItem,
          }
        : {};
      this.resetNewTemplateForm();
      if (typeof this.clearFormErrors === "function") {
        this.clearFormErrors();
      }
      this.resetClassEdit();
    },
    startTemplateCreate() {
      if (!this.isGM || this.gmMode !== runtime.GM_MODES.TEMPLATES) {
        return;
      }
      this.setSelectedTemplateId(null);
      this.resetClassEdit();
      this.resetNewTemplateForm();
    },
  };
};
