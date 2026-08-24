export const tableWallMethods = {
  selectWall(wallId) {
    this.$store.commit(
      "vtt/SELECT_WALL",
      wallId === null ? null : Number(wallId),
    );
  },
  createWall(draft) {
    if (!this.selectedScene || !this.canManageWalls) return;
    this.$store.dispatch("vtt/createWall", draft).catch(() => {});
  },
  updateWall({ wall, changes }) {
    this.$store.dispatch("vtt/updateWall", { wall, changes }).catch((error) => {
      if (error?.status === 409) {
        this.$store.dispatch("vtt/loadWalls").catch(() => {});
      }
    });
  },
  deleteWall(wall) {
    if (!window.confirm(this.$t("vtt.wall.deleteConfirm"))) return;
    this.$store.dispatch("vtt/deleteWall", wall).catch(() => {});
  },
};
