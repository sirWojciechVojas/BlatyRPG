export const tableWallMethods = {
  selectWall(wallId) {
    this.$store.commit(
      "vtt/SELECT_WALL",
      wallId === null ? null : Number(wallId),
    );
  },
  async createWall(draft) {
    if (!this.selectedScene || !this.canManageWalls) return;
    const sent = await this.$store.dispatch("realtime/changeWall", {
      operation: "create",
      changes: draft,
    });
    if (!sent) this.$store.dispatch("vtt/createWall", draft).catch(() => {});
  },
  async updateWall({ wall, changes }) {
    const sent = await this.$store.dispatch("realtime/changeWall", {
      operation: "update",
      wall,
      changes,
    });
    if (sent) return;
    this.$store.dispatch("vtt/updateWall", { wall, changes }).catch((error) => {
      if (error?.status === 409) {
        this.$store.dispatch("vtt/loadWalls").catch(() => {});
      }
    });
  },
  async deleteWall(wall) {
    if (!window.confirm(this.$t("vtt.wall.deleteConfirm"))) return;
    const sent = await this.$store.dispatch("realtime/changeWall", {
      operation: "delete",
      wall,
    });
    if (!sent) this.$store.dispatch("vtt/deleteWall", wall).catch(() => {});
  },
};
