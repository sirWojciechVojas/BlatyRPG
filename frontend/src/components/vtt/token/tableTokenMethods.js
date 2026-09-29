import { snapTokenPosition } from "@/lib/vtt/grid";
import { tokenTemplateApiClient } from "@/lib/vtt/tokenTemplateApiClient";
import { characterApiClient } from "@/lib/character/characterApiClient";

const REALTIME_CHANGE_FIELDS = new Set([
  "characterId",
  "name",
  "imageUrl",
  "width",
  "height",
  "rotation",
  "facing",
  "rotationHandleEnabled",
  "facingHandleEnabled",
  "rotationFollowsFacing",
  "showInfoUnselected",
  "resourceBarPosition",
  "movementRange",
  "movementSpent",
  "movementResetMode",
  "elevation",
  "disposition",
  "hidden",
  "locked",
  "visibleTo",
  "controlledBy",
  "editableBy",
  "observerBy",
  "statuses",
  "resources",
  "vision",
]);

const isRealtimeTokenChange = (changes) => {
  const fields = Object.keys(changes || {});
  return (
    fields.length > 0 &&
    fields.every((field) => REALTIME_CHANGE_FIELDS.has(field))
  );
};

const latestToken = (component, token) =>
  (
    component.$store.state?.vtt?.tokensByScene?.[String(token.sceneId)] || []
  ).find((item) => Number(item.id) === Number(token.id)) || token;

const realtimeTokenUpdate = async (
  component,
  token,
  changes,
  allowRetry = true,
) => {
  const current = latestToken(component, token);
  try {
    const sent = await component.$store.dispatch("realtime/changeToken", {
      token: current,
      changes,
    });
    if (sent) {
      component.$store.commit?.("vtt/PATCH_TOKEN", {
        id: current.id,
        sceneId: current.sceneId,
        ...changes,
      });
      return latestToken(component, { ...current, ...changes });
    }
    return await component.$store.dispatch("vtt/updateToken", {
      token: current,
      changes,
    });
  } catch (error) {
    if (error?.status !== 409 || !allowRetry) throw error;
    await component.$store.dispatch("vtt/loadTokens", { silent: true });
    return realtimeTokenUpdate(component, token, changes, false);
  }
};

export const tableTokenMethods = {
  selectToken(selection) {
    if (Array.isArray(selection?.tokenIds)) {
      this.$store.commit("vtt/SELECT_TOKENS", {
        tokenIds: selection.tokenIds,
        additive: selection.additive === true,
      });
      return;
    }
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
  async placeTokenTemplate(payload = {}) {
    const template = payload.template || payload;
    if (!template?.id || !this.selectedScene || !this.canCreateToken) return;
    const centerX = Number.isFinite(Number(payload.x))
      ? Number(payload.x)
      : Number(this.sceneViewCenter?.x ?? this.selectedScene.width / 2);
    const centerY = Number.isFinite(Number(payload.y))
      ? Number(payload.y)
      : Number(this.sceneViewCenter?.y ?? this.selectedScene.height / 2);
    this.tokenTemplateBusyId = template.id;
    this.$store.commit?.("vtt/SET_TOKEN_PHASE", "saving");
    try {
      const token = await tokenTemplateApiClient.instantiate(
        this.currentCampaignId,
        this.selectedScene.id,
        template.id,
        centerX,
        centerY,
      );
      if (token) {
        this.$store.commit("vtt/UPSERT_TOKEN", token);
        this.$store.commit("vtt/SELECT_TOKEN", token.id);
      }
      this.$store.commit?.("vtt/SET_TOKEN_PHASE", "ready");
      return token;
    } catch (error) {
      this.$store.commit?.("vtt/TOKEN_FAILED", {
        code: String(error?.code || "token_template_write_failed"),
        status: Number(error?.status) || 0,
        details: error?.payload?.errors || null,
      });
      return null;
    } finally {
      this.tokenTemplateBusyId = null;
    }
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
  async moveTokenGroup({ moves = [] }) {
    let sent = false;
    try {
      sent = await this.$store.dispatch("realtime/moveTokenGroup", { moves });
    } catch (_error) {
      sent = false;
    }
    if (!sent) {
      this.$store.commit("vtt/SHOW_NOTICE", {
        code: "token_group_realtime_required",
        status: 0,
        network: true,
      });
    }
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
  blockDepletedTokenMovement(payload) {
    const group = payload?.group === true;
    const names = group
      ? (payload.tokens || [])
          .map(({ name }) => name)
          .filter(Boolean)
          .join(", ")
      : "";
    this.$store.commit("vtt/SHOW_NOTICE", {
      code: group ? "movement_group_blocked" : "movement_points_depleted",
      status: 422,
      network: false,
      details: group ? { names } : null,
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
  async updateToken({ token, changes, onSuccess, onError }) {
    this.$store.commit?.("vtt/SET_TOKEN_PHASE", "saving");
    try {
      const updated = isRealtimeTokenChange(changes)
        ? await realtimeTokenUpdate(this, token, changes)
        : await this.$store.dispatch("vtt/updateToken", { token, changes });
      this.$store.commit?.("vtt/SET_TOKEN_PHASE", "ready");
      onSuccess?.(updated);
      return updated;
    } catch (error) {
      this.$store.commit?.("vtt/TOKEN_FAILED", {
        code: String(error?.code || "token_write_failed"),
        status: Number(error?.status) || 0,
        details: error?.details || error?.payload?.errors || null,
      });
      onError?.(error);
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
  openTokenCharacterAssignment(token) {
    if (!token?.capabilities?.canManage) return;
    this.tokenCharacterAssignmentToken = token;
    this.tokenCharacterAssignmentError = "";
    this.tokenCharacterAssignmentCreatedId = null;
  },
  closeTokenCharacterAssignment() {
    if (this.tokenCharacterAssignmentBusy) return;
    this.tokenCharacterAssignmentToken = null;
    this.tokenCharacterAssignmentError = "";
    this.tokenCharacterAssignmentCreatedId = null;
  },
  async assignTokenCharacter(characterId) {
    const token = this.tokenCharacterAssignmentToken;
    if (!token || this.tokenCharacterAssignmentBusy) return null;
    this.tokenCharacterAssignmentBusy = true;
    this.tokenCharacterAssignmentError = "";
    const updated = await this.updateToken({
      token,
      changes: { characterId: characterId ? Number(characterId) : null },
    });
    this.tokenCharacterAssignmentBusy = false;
    if (!updated) {
      this.tokenCharacterAssignmentError = this.$t(
        "vtt.token.assignment.linkError",
      );
      return null;
    }
    this.tokenCharacterAssignmentToken = null;
    this.tokenCharacterAssignmentCreatedId = null;
    await this.$store.dispatch("vtt/loadTokenSync").catch(() => {});
    return updated;
  },
  async createAndAssignTokenCharacter(name) {
    const token = this.tokenCharacterAssignmentToken;
    if (!token || this.tokenCharacterAssignmentBusy) return null;
    const systemId = Number(this.campaign?.systemId) || null;
    const universeId = Number(this.campaign?.universeId) || null;
    if (!systemId || !universeId) {
      this.tokenCharacterAssignmentError = this.$t(
        "characters.errors.campaign_game",
      );
      return null;
    }
    this.tokenCharacterAssignmentBusy = true;
    this.tokenCharacterAssignmentError = "";
    try {
      const character = await characterApiClient.create(
        this.currentCampaignId,
        {
          systemId,
          universeId,
          name: String(name || token.name).trim(),
          data: {},
          avatarUrl: token.imageUrl || "",
        },
      );
      this.tokenCharacterAssignmentCreatedId = character.id;
      await this.$store.dispatch("campaignContext/refresh").catch(() => {});
      this.tokenCharacterAssignmentBusy = false;
      return this.assignTokenCharacter(character.id);
    } catch (_error) {
      this.tokenCharacterAssignmentError = this.$t(
        "vtt.token.assignment.createError",
      );
      this.tokenCharacterAssignmentBusy = false;
      return null;
    }
  },
};
