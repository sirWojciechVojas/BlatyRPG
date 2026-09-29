const writeError = (resource, payload = {}) => {
  const fallback = `${resource}_write_failed`;
  const error = new Error(String(payload.code || fallback));
  error.code = String(payload.code || fallback);
  error.status = Number(payload.status) || 500;
  error.details = payload.errors || {};
  return error;
};

export const createSceneElementRequestTracker = (resource, options = {}) => {
  const pending = new Map();
  const setTimer = options.setTimeout || setTimeout;
  const clearTimer = options.clearTimeout || clearTimeout;
  const timeoutMs = Number(options.timeoutMs) || 6000;

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
            finish(requestId, (request) =>
              request.reject(
                writeError(resource, {
                  code: `${resource}_write_timeout`,
                  status: 504,
                }),
              ),
            ),
          timeoutMs,
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
      if (event.type === `${resource}.ack`) {
        return finish(requestId, (request) => request.resolve(true));
      }
      if (event.type === `${resource}.error`) {
        return finish(requestId, (request) =>
          request.reject(writeError(resource, event.payload)),
        );
      }
      return false;
    },
    size: () => pending.size,
  };
};

export const tokenRequestTracker = createSceneElementRequestTracker("token");
export const tokenSyncRequestTracker =
  createSceneElementRequestTracker("token.sync");
export const wallRequestTracker = createSceneElementRequestTracker("wall");
