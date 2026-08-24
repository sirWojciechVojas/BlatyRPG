export const tableLightMethods = {
  selectLight(lightId) {
    this.$store.commit(
      "vtt/SELECT_LIGHT",
      lightId === null ? null : Number(lightId),
    );
  },
  async createLight(draft) {
    if (!this.selectedScene || !this.canManageLights) return;
    const sent = await this.$store.dispatch("realtime/changeLight", {
      operation: "create",
      changes: draft,
    });
    if (!sent) this.$store.dispatch("vtt/createLight", draft).catch(() => {});
  },
  async updateLight({ light, changes }) {
    const sent = await this.$store.dispatch("realtime/changeLight", {
      operation: "update",
      light,
      changes,
    });
    if (sent) return;
    this.$store
      .dispatch("vtt/updateLight", { light, changes })
      .catch((error) => {
        if (error?.status === 409)
          this.$store.dispatch("vtt/loadLights").catch(() => {});
      });
  },
  async deleteLight(light) {
    if (!window.confirm(this.$t("vtt.light.deleteConfirm"))) return;
    const sent = await this.$store.dispatch("realtime/changeLight", {
      operation: "delete",
      light,
    });
    if (!sent) this.$store.dispatch("vtt/deleteLight", light).catch(() => {});
  },
};
