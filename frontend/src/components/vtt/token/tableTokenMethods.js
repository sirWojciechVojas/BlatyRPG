import { snapTokenPosition } from "@/lib/vtt/grid";

export const tableTokenMethods = {
  selectToken(selection) {
    const tokenId =
      selection && typeof selection === "object"
        ? selection.tokenId
        : selection;
    if (tokenId === null || tokenId === undefined) {
      this.$store.commit("vtt/SELECT_TOKEN", { tokenId: null });
      return;
    }
    this.$store.commit("vtt/SELECT_TOKEN", {
      tokenId: Number(tokenId),
      additive: selection?.additive === true,
    });
  },
  toggleTokenTarget(tokenId) {
    this.$store.commit("vtt/TOGGLE_TOKEN_TARGET", Number(tokenId));
  },
  async createToken({ actor, x, y }) {
    if (!this.selectedScene || !this.canCreateToken) return;
    const size = Math.max(1, Number(this.selectedScene.gridSize) || 100);
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
  async moveToken({ token, x, y, waypoints = [] }) {
    let sent = false;
    try {
      sent = await this.$store.dispatch("realtime/moveToken", {
        token,
        x,
        y,
        waypoints,
      });
    } catch (_error) {
      sent = false;
    }
    if (!sent) this.updateToken({ token, changes: { x, y, waypoints } });
  },
  async updateToken({ token, changes }) {
    try {
      const updated = await this.$store.dispatch("vtt/updateToken", {
        token,
        changes,
      });
      if (changes.resources) {
        this.$store.dispatch("campaignContext/refresh").catch(() => {});
      }
      return updated;
    } catch (error) {
      if (error?.status === 409) {
        this.$store.dispatch("vtt/loadTokens").catch(() => {});
      }
      return null;
    }
  },
  deleteToken(token) {
    const message = this.$t("vtt.token.deleteConfirm", { name: token.name });
    if (!window.confirm(message)) return;
    this.$store.dispatch("vtt/deleteToken", token).catch(() => {});
  },
  openActor(characterId) {
    this.focusedCharacterId = Number(characterId) || null;
    this.openUtilityWindow("characters");
  },
};
