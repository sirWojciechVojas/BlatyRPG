import { tokenAnglePreview } from "@/lib/vtt/tokenRotation";
import { tokenFacingChanges } from "@/lib/vtt/tokenFacing";

const angleChanges = (token, change) =>
  change.field === "facing"
    ? tokenFacingChanges(token, change.value)
    : { [change.field]: change.value };

export const tokenRotationMethods = {
  displayTokenAngles(token) {
    return tokenAnglePreview(token, this.anglePreview[token.id]);
  },
  previewTokenAngle(token, change) {
    const changes = angleChanges(this.displayTokenAngles(token), change);
    this.anglePreview = {
      ...this.anglePreview,
      [token.id]: {
        ...(this.anglePreview[token.id] || {}),
        ...changes,
      },
    };
    this.$emit("vision-angle-preview", {
      tokenId: token.id,
      changes: this.anglePreview[token.id],
    });
  },
  commitTokenAngle(token, change) {
    const preview = this.anglePreview[token.id] || {};
    const changes = angleChanges(token, change);
    if (
      change.field === "facing" &&
      token.rotationFollowsFacing === true &&
      Object.hasOwn(preview, "rotation")
    ) {
      changes.rotation = preview.rotation;
    }
    this.clearTokenAnglePreview(token.id);
    this.$emit("update", { token, changes });
  },
  clearTokenAnglePreview(tokenId) {
    const wheelTimer = this.facingWheelTimers?.get(tokenId);
    if (wheelTimer) window.clearTimeout(wheelTimer);
    this.facingWheelTimers?.delete(tokenId);
    const next = { ...this.anglePreview };
    delete next[tokenId];
    this.anglePreview = next;
    this.$emit("vision-angle-preview", { tokenId, changes: null });
  },
};
