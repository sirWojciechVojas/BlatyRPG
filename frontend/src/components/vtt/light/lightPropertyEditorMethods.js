import { focusTableWindow } from "@/components/vtt/table/tableWindowLayers";

export const lightPropertyEditorMethods = {
  openProperties() {
    this.propertiesPreview = null;
    this.propertySaveStatus = "idle";
    this.propertySaveError = "";
    this.propertiesWindow.minimized = false;
    this.propertiesOpen = true;
    this.$nextTick(() => focusTableWindow(this.propertiesWindow.id));
  },
  closeProperties() {
    this.propertiesOpen = false;
    this.propertiesPreview = null;
    this.propertySaveStatus = "idle";
  },
  saveProperties(changes) {
    this.creationTemplate = { ...this.creationTemplate, ...changes };
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
