import { snapTokenPosition } from "@/lib/vtt/grid";

export const tableTokenMethods = {
  selectToken(selection) {
    const tokenId = selection?.tokenId ?? selection;
    this.$store.commit("vtt/SELECT_TOKEN", {
      tokenId: Number(tokenId),
      additive: selection?.additive === true,
    });
  },
  async createToken({ actor, x, y }) {
    if (!this.selectedScene || !this.canCreateToken) return;
    const size = Math.max(8, Number(this.selectedScene.gridSize) || 100);
    const position = snapTokenPosition(
      this.selectedScene,
      { x: x - size / 2, y: y - size / 2 },
      { width: size, height: size },
    );
    await this.$store
      .dispatch("vtt/createToken", {
        characterId: actor.id,
        name: actor.name,
        imageUrl: actor.imageUrl,
        x: position.x,
        y: position.y,
        width: size,
        height: size,
      })
      .catch(() => {});
  },
  async moveToken({ token, x, y }) {
    let sent = false;
    try {
      sent = await this.$store.dispatch("realtime/moveToken", { token, x, y });
    } catch (_error) {
      sent = false;
    }
    if (!sent) this.updateToken({ token, changes: { x, y } });
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
