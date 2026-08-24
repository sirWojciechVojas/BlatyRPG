export const tableLightMethods = {
  selectLight(lightId) {
    this.$store.commit(
      "vtt/SELECT_LIGHT",
      lightId === null ? null : Number(lightId),
    );
  },
  createLight(draft) {
    if (!this.selectedScene || !this.canManageLights) return;
    this.$store.dispatch("vtt/createLight", draft).catch(() => {});
  },
  updateLight({ light, changes }) {
    this.$store
      .dispatch("vtt/updateLight", { light, changes })
      .catch((error) => {
        if (error?.status === 409) {
          this.$store.dispatch("vtt/loadLights").catch(() => {});
        }
      });
  },
  deleteLight(light) {
    if (!window.confirm(this.$t("vtt.light.deleteConfirm"))) return;
    this.$store.dispatch("vtt/deleteLight", light).catch(() => {});
  },
};
