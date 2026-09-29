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
    if (this.hudTokenId && (ids.length > 1 || !ids.includes(this.hudTokenId))) {
      this.hudTokenId = null;
    }
    if (
      this.settingsTokenId &&
      (ids.length > 1 || !ids.includes(this.settingsTokenId))
    ) {
      this.settingsTokenId = null;
    }
  },
};
