import { pendingTokenPositionResolved } from "./tokenMotion";

export const tokenLayerMotionMethods = {
  holdTokenPosition(token, position) {
    const tokenId = Number(token.id);
    window.clearTimeout(this.pendingTimers.get(tokenId));
    this.pendingPositions = {
      ...this.pendingPositions,
      [tokenId]: {
        x: Number(position.x),
        y: Number(position.y),
        revision: Number(token.revision),
      },
    };
    this.pendingTimers.set(
      tokenId,
      window.setTimeout(() => this.releasePendingPosition(tokenId), 3000),
    );
  },
  syncPendingPositions(tokens) {
    Object.entries(this.pendingPositions).forEach(([id, pending]) => {
      const token = tokens.find((item) => item.id === Number(id));
      if (pendingTokenPositionResolved(pending, token)) {
        this.releasePendingPosition(id);
      }
    });
  },
  releasePendingPosition(tokenId) {
    window.clearTimeout(this.pendingTimers.get(Number(tokenId)));
    this.pendingTimers.delete(Number(tokenId));
    const pendingPositions = { ...this.pendingPositions };
    delete pendingPositions[tokenId];
    this.pendingPositions = pendingPositions;
  },
  startMotion(event, tokenId) {
    if (
      event.target !== event.currentTarget ||
      event.propertyName !== "transform"
    ) {
      return;
    }
    this.movingTokenIds = { ...this.movingTokenIds, [tokenId]: true };
  },
  finishMotion(event, tokenId) {
    if (
      event.target !== event.currentTarget ||
      event.propertyName !== "transform"
    ) {
      return;
    }
    const movingTokenIds = { ...this.movingTokenIds };
    delete movingTokenIds[tokenId];
    this.movingTokenIds = movingTokenIds;
  },
};
