import { tokenTravelDuration } from "./tokenMotion";

export const tokenLayerWatchers = {
  tokens: {
    deep: true,
    handler(tokens, previousTokens = []) {
      const durations = { ...this.motionDurations };
      tokens.forEach((token) => {
        const previous = previousTokens.find((item) => item.id === token.id);
        if (previous && (previous.x !== token.x || previous.y !== token.y)) {
          durations[token.id] = tokenTravelDuration(previous, token);
        }
      });
      Object.keys(durations).forEach((id) => {
        if (!tokens.some((token) => token.id === Number(id))) {
          delete durations[id];
        }
      });
      this.motionDurations = durations;
      this.syncPendingPositions(tokens);
    },
  },
  effectiveSelectedIds(ids) {
    const includes = (tokenId) =>
      ids.some((id) => String(id) === String(tokenId));
    if (this.hudTokenId && (ids.length > 1 || !includes(this.hudTokenId))) {
      this.hudTokenId = null;
    }
    if (
      this.settingsTokenId &&
      (ids.length > 1 || !includes(this.settingsTokenId))
    ) {
      this.settingsTokenId = null;
    }
    if (
      this.expandedTokenId !== null &&
      (ids.length !== 1 || !includes(this.expandedTokenId))
    ) {
      this.expandedTokenId = null;
    }
    if (
      this.presentationClickTokenId !== null &&
      (ids.length !== 1 || !includes(this.presentationClickTokenId))
    ) {
      this.clearPresentationClick();
    }
  },
};
