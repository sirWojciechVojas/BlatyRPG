import { snapTokenPosition } from "@/lib/vtt/grid";

const isRealtimeAngleChange = (changes) => {
  const fields = Object.keys(changes || {});
  return (
    fields.length > 0 &&
    fields.every((field) => ["rotation", "facing"].includes(field))
  );
};

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
  async requestTokenMovement({ token, position, waypoints = [] }) {
    const sent = await this.$store.dispatch("realtime/requestTokenMovement", {
      token,
      x: position.x,
      y: position.y,
      waypoints,
    });
    if (sent) this.selectUtility("notifications");
  },
  blockDepletedTokenMovement() {
    this.$store.commit("vtt/SHOW_NOTICE", {
      code: "movement_points_depleted",
      status: 422,
      network: false,
      details: null,
    });
  },
  dismissSceneNotice() {
    this.$store.commit("vtt/CLEAR_ERROR");
  },
  resolveTokenMovement({ requestId, decision }) {
    this.$store.dispatch("realtime/resolveTokenMovement", {
      movementRequestId: requestId,
      decision,
    });
  },
  async updateToken({ token, changes }) {
    if (isRealtimeAngleChange(changes)) {
      try {
        const sent = await this.$store.dispatch("realtime/changeToken", {
          token,
          changes,
        });
        if (sent) return token;
      } catch (_error) {
        // Preserve the existing REST path when realtime is unavailable.
      }
    }
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
