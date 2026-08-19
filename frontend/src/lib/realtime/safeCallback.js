const noop = () => {};

export const safeCallback = (callback, payload) => {
  try {
    const result = callback(payload);
    Promise.resolve(result).catch(noop);
  } catch (_error) {
    // Consumer failures are isolated from the transport.
  }
};
