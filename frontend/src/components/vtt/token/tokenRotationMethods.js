import { tokenAnglePreview } from "@/lib/vtt/tokenRotation";

export const tokenRotationMethods = {
  displayTokenAngles(token) {
    return tokenAnglePreview(token, this.anglePreview[token.id]);
  },
  previewTokenAngle(token, change) {
    this.anglePreview = {
      ...this.anglePreview,
      [token.id]: {
        ...(this.anglePreview[token.id] || {}),
        [change.field]: change.value,
      },
    };
    this.$emit("vision-angle-preview", {
      tokenId: token.id,
      changes: this.anglePreview[token.id],
    });
  },
  commitTokenAngle(token, change) {
    this.clearTokenAnglePreview(token.id);
    this.$emit("update", { token, changes: { [change.field]: change.value } });
  },
  clearTokenAnglePreview(tokenId) {
    const next = { ...this.anglePreview };
    delete next[tokenId];
    this.anglePreview = next;
    this.$emit("vision-angle-preview", { tokenId, changes: null });
  },
};
