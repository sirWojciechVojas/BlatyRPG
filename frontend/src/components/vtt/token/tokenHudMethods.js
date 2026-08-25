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
  toggleTokenStatus(token, code) {
    this.$emit("update", {
      token,
      changes: { statuses: toggleTokenStatus(token.statuses, code) },
    });
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
