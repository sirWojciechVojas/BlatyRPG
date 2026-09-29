import { toggleTokenStatus } from "@/lib/vtt/tokenStatuses";

const anchorRect = (event) => {
  const rect = event.currentTarget
    ?.closest(".scene-token-wrap")
    ?.getBoundingClientRect();
  return rect
    ? { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom }
    : {};
};

export const tokenHudMethods = {
  tokenInfoVisible(token) {
    if (
      this.hasMultiSelection === true &&
      this.tokenStates?.[token.id]?.selected === true
    ) {
      return false;
    }
    return (
      token.showInfoUnselected === true ||
      this.tokenStates?.[token.id]?.selected === true
    );
  },
  resourceEditable(token) {
    if (this.busy) return false;
    if (token.capabilities.canManage) return true;
    return (
      !token.locked &&
      (token.capabilities.canControl || token.capabilities.canEdit)
    );
  },
  openTokenActor(token) {
    if (
      token.characterId &&
      (token.capabilities.canObserve ||
        token.capabilities.canControl ||
        token.capabilities.canManage)
    ) {
      this.$emit("open-actor", token.characterId);
    }
  },
  toggleTokenStatus(token, code) {
    this.$emit("update", {
      token,
      changes: { statuses: toggleTokenStatus(token.statuses, code) },
    });
  },
  updateTokenResources(token, resources) {
    this.$emit("update", { token, changes: { resources } });
  },
  toggleTokenVisibility(token) {
    this.$emit("update", { token, changes: { hidden: !token.hidden } });
  },
  toggleTokenLock(token) {
    this.$emit("update", { token, changes: { locked: !token.locked } });
  },
  openTokenSettings(event, token) {
    this.settingsTokenId = token.id;
    this.settingsAnchor = anchorRect(event);
  },
  saveTokenSettings(token, changes) {
    this.$emit("update", { token, changes });
    this.settingsTokenId = null;
  },
};
