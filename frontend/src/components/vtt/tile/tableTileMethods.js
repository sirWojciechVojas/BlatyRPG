export const tableTileMethods = {
  selectTile(tileId) {
    this.$store.commit(
      "vtt/SELECT_TILE",
      tileId === null ? null : Number(tileId),
    );
  },
  async createTile(draft) {
    if (!this.selectedScene || !this.canManageTiles) return;
    const sent = await this.$store.dispatch("realtime/changeTile", {
      operation: "create",
      changes: draft,
    });
    if (!sent) this.$store.dispatch("vtt/createTile", draft).catch(() => {});
  },
  async updateTile({ tile, changes }) {
    const sent = await this.$store.dispatch("realtime/changeTile", {
      operation: "update",
      tile,
      changes,
    });
    if (sent) return;
    this.$store.dispatch("vtt/updateTile", { tile, changes }).catch((error) => {
      if (error?.status === 409) {
        this.$store.dispatch("vtt/loadTiles").catch(() => {});
      }
    });
  },
  async deleteTile(tile) {
    if (!window.confirm(this.$t("vtt.tile.deleteConfirm"))) return;
    const sent = await this.$store.dispatch("realtime/changeTile", {
      operation: "delete",
      tile,
    });
    if (!sent) this.$store.dispatch("vtt/deleteTile", tile).catch(() => {});
  },
};
