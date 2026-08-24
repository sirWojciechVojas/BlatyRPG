export const tableTokenMethods = {
  selectToken(tokenId) {
    this.$store.commit("vtt/SELECT_TOKEN", Number(tokenId));
  },
  async createToken({ actor, x, y }) {
    if (!this.selectedScene || !this.canCreateToken) return;
    const size = Math.max(8, Number(this.selectedScene.gridSize) || 100);
    await this.$store
      .dispatch("vtt/createToken", {
        characterId: actor.id,
        name: actor.name,
        imageUrl: actor.imageUrl,
        x: Math.max(0, Math.round(x - size / 2)),
        y: Math.max(0, Math.round(y - size / 2)),
        width: size,
        height: size,
      })
      .catch(() => {});
  },
  moveToken({ token, x, y }) {
    this.updateToken({ token, changes: { x, y } });
  },
  updateToken({ token, changes }) {
    this.$store
      .dispatch("vtt/updateToken", { token, changes })
      .catch((error) => {
        if (error?.status === 409) {
          this.$store.dispatch("vtt/loadTokens").catch(() => {});
        }
      });
  },
  deleteToken(token) {
    const message = this.$t("vtt.token.deleteConfirm", { name: token.name });
    if (!window.confirm(message)) return;
    this.$store.dispatch("vtt/deleteToken", token).catch(() => {});
  },
  openActor() {
    this.openUtilityWindow("characters");
  },
};
