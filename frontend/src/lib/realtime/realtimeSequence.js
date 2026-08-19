const keyFor = (campaignId) =>
  `blatyrpg.realtime.sequence.${Number(campaignId)}`;

const defaultStorage = () =>
  typeof window === "undefined" ? null : window.sessionStorage;

export const createRealtimeSequence = (options = {}) => {
  const storage = options.storage ?? defaultStorage();
  let campaignId = null;
  let value = 0;

  const select = (id) => {
    campaignId = Number(id) || null;
    try {
      value = campaignId
        ? Math.max(0, Number(storage?.getItem(keyFor(campaignId))) || 0)
        : 0;
    } catch (_error) {
      value = 0;
    }
    return value;
  };

  const set = (next) => {
    value = Math.max(0, Number(next) || 0);
    try {
      if (campaignId) storage?.setItem(keyFor(campaignId), String(value));
    } catch (_error) {
      // In-memory sequence still protects this tab from duplicates.
    }
    return value;
  };

  return { get: () => value, select, set };
};
