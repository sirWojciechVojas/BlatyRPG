export const tableTileMethods = {
  selectTile(tileId) {
    this.$store.commit(
      "vtt/SELECT_TILE",
      tileId === null ? null : Number(tileId),
    );
  },
  createTile(draft) {
    if (!this.selectedScene || !this.canManageTiles) return;
    this.$store.dispatch("vtt/createTile", draft).catch(() => {});
  },
  updateTile({ tile, changes }) {
    this.$store.dispatch("vtt/updateTile", { tile, changes }).catch((error) => {
      if (error?.status === 409) {
        this.$store.dispatch("vtt/loadTiles").catch(() => {});
      }
    });
  },
  deleteTile(tile) {
    if (!window.confirm(this.$t("vtt.tile.deleteConfirm"))) return;
    this.$store.dispatch("vtt/deleteTile", tile).catch(() => {});
  },
};
