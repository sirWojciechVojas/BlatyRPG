export const lightPropertyEditorMethods = {
  openProperties() {
    this.propertiesPreview = null;
    this.propertySaveStatus = "idle";
    this.propertySaveError = "";
    this.propertiesOpen = true;
  },
  closeProperties() {
    this.propertiesOpen = false;
    this.propertiesPreview = null;
    this.propertySaveStatus = "idle";
  },
  saveProperties(changes) {
    this.propertySaveStatus = "saving";
    this.propertySaveError = "";
    this.$emit("update", {
      light: this.selectedLight,
      changes,
      onSuccess: () => {
        this.propertySaveStatus = "saved";
        this.propertiesPreview = null;
        this.$nextTick(() => this.$refs.properties?.reset());
      },
      onError: (error) => {
        this.propertySaveStatus = "error";
        this.propertySaveError = this.$t("vtt.light.saveErrorCode", {
          code: error?.code || "light_write_failed",
        });
        this.propertiesPreview = null;
      },
    });
  },
};
