const requestError = (payload = {}) => {
  const error = new Error(String(payload.code || "light_write_failed"));
  error.code = String(payload.code || "light_write_failed");
  error.status = Number(payload.status) || 500;
  error.details = payload.errors || {};
  return error;
};

export const createLightRequestTracker = (options = {}) => {
  const pending = new Map();
  const setTimer = options.setTimeout || setTimeout;
  const clearTimer = options.clearTimeout || clearTimeout;

  const finish = (requestId, callback) => {
    const request = pending.get(requestId);
    if (!request) return false;
    pending.delete(requestId);
    clearTimer(request.timer);
    callback(request);
    return true;
  };

  return {
    send(requestId, operation) {
      return new Promise((resolve, reject) => {
        const timer = setTimer(
          () =>
            finish(requestId, (request) => {
              const error = requestError({
                code: "light_write_timeout",
                status: 504,
              });
              request.reject(error);
            }),
          6000,
        );
        pending.set(requestId, { resolve, reject, timer });
        try {
          if (operation()) return;
          finish(requestId, (request) => request.resolve(false));
        } catch (error) {
          finish(requestId, (request) => request.reject(error));
        }
      });
    },
    settle(event) {
      const requestId = String(event?.payload?.requestId || "");
      if (!requestId) return false;
      if (event.type === "light.ack") {
        return finish(requestId, (request) => request.resolve(true));
      }
      if (event.type === "light.error") {
        return finish(requestId, (request) =>
          request.reject(requestError(event.payload)),
        );
      }
      return false;
    },
    size: () => pending.size,
  };
};

export const lightRequestTracker = createLightRequestTracker();
