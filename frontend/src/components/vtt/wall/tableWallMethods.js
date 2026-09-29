import { playDoorSound } from "@/lib/vtt/doorAudio";

const queues = new Map();
let activeWrites = 0;

const enqueue = (key, operation) => {
  const previous = queues.get(key) || Promise.resolve();
  const current = previous.catch(() => {}).then(operation);
  queues.set(key, current);
  const cleanup = () => {
    if (queues.get(key) === current) queues.delete(key);
  };
  current.then(cleanup, cleanup);
  return current;
};

const latestWall = (component, wall) =>
  (component.$store.state.vtt?.wallsByScene?.[String(wall.sceneId)] || []).find(
    (item) => item.id === wall.id,
  ) || wall;

const beginWrite = (component) => {
  activeWrites += 1;
  component.$store.commit("vtt/SET_WALL_PHASE", "saving");
};

const endWrite = (component) => {
  activeWrites = Math.max(0, activeWrites - 1);
  if (!activeWrites && component.$store.state.vtt?.wallPhase !== "error") {
    component.$store.commit("vtt/SET_WALL_PHASE", "ready");
  }
};

const reportError = (component, error) => {
  component.$store.commit("vtt/WALL_FAILED", {
    code: String(error?.code || "wall_write_failed"),
    status: Number(error?.status) || 0,
    details: error?.details || error?.payload?.errors || null,
  });
};

const perform = async (component, key, operation, callbacks = {}) => {
  beginWrite(component);
  try {
    const result = await enqueue(key, operation);
    callbacks.onSuccess?.(result);
    return result;
  } catch (error) {
    reportError(component, error);
    callbacks.onError?.(error);
    return false;
  } finally {
    endWrite(component);
  }
};

const realtimeUpdate = async (component, wall, changes, allowRetry = true) => {
  const current = latestWall(component, wall);
  try {
    const sent = await component.$store.dispatch("realtime/changeWall", {
      operation: "update",
      wall: current,
      changes,
    });
    if (sent) return true;
  } catch (error) {
    if (error?.status !== 409 || !allowRetry) throw error;
    await component.$store.dispatch("vtt/loadWalls");
    return realtimeUpdate(component, wall, changes, false);
  }
  await component.$store.dispatch("vtt/updateWall", {
    wall: current,
    changes,
  });
  return true;
};

export const tableWallMethods = {
  selectWall(wallId) {
    this.$store.commit(
      "vtt/SELECT_WALL",
      wallId === null ? null : Number(wallId),
    );
  },
  createWall(draft) {
    if (!this.selectedScene || !this.canManageWalls) return false;
    return perform(this, `${this.selectedScene.id}:create`, async () => {
      await this.$store.dispatch("vtt/createWall", draft);
      return true;
    });
  },
  createWalls(drafts) {
    if (
      !this.selectedScene ||
      !this.canManageWalls ||
      !Array.isArray(drafts) ||
      !drafts.length
    ) {
      return false;
    }
    const sceneId = this.selectedScene.id;
    return perform(this, `${sceneId}:create-many`, async () => {
      for (const draft of drafts.slice(0, 30)) {
        await this.$store.dispatch("vtt/createWall", draft);
      }
      return true;
    });
  },
  insertWallOpening({ wall, changes, drafts }) {
    if (
      !wall ||
      !this.canManageWalls ||
      !Array.isArray(drafts) ||
      drafts.length !== 2
    ) {
      return false;
    }
    return perform(this, `${wall.sceneId}:${wall.id}:opening`, async () => {
      await realtimeUpdate(this, wall, changes);
      for (const draft of drafts) {
        await this.$store.dispatch("vtt/createWall", draft);
      }
      return true;
    });
  },
  updateWall({ wall, changes, onSuccess, onError }) {
    if (!wall || !this.canManageWalls) return false;
    return perform(
      this,
      `${wall.sceneId}:${wall.id}`,
      () => realtimeUpdate(this, wall, changes),
      { onSuccess, onError },
    );
  },
  updateWalls(payload) {
    const updates = Array.isArray(payload) ? payload : payload?.updates;
    const callbacks = Array.isArray(payload) ? {} : payload || {};
    if (!Array.isArray(updates) || !updates.length || !this.canManageWalls) {
      return false;
    }
    const sceneId = updates[0].wall?.sceneId || this.selectedScene?.id;
    return perform(
      this,
      `${sceneId}:bulk`,
      async () => {
        for (const { wall, changes } of updates) {
          await enqueue(`${wall.sceneId}:${wall.id}`, () =>
            realtimeUpdate(this, wall, changes),
          );
        }
        return true;
      },
      callbacks,
    );
  },
  interactWall({ wall, doorState, actingTokenIds = [], silent = false }) {
    if (!wall || !["open", "closed", "locked"].includes(doorState)) {
      return false;
    }
    return perform(this, `${wall.sceneId}:${wall.id}:door`, async () => {
      const current = latestWall(this, wall);
      const sent = await this.$store.dispatch("realtime/changeWall", {
        operation: "interact",
        wall: current,
        changes: {
          doorState,
          actingTokenIds,
          ...(silent === true ? { silent: true } : {}),
        },
      });
      if (!sent) {
        try {
          const updated = await this.$store.dispatch("vtt/interactWall", {
            wall: current,
            doorState,
            actingTokenIds,
            silent,
          });
          if (updated && !silent) {
            const cue =
              doorState === "open"
                ? "open"
                : doorState === "locked"
                  ? "lock"
                  : "close";
            playDoorSound(updated, cue);
          }
          return Boolean(updated);
        } catch (error) {
          if (error?.code === "door_locked") {
            playDoorSound(current, "lockedAttempt");
          }
          throw error;
        }
      }
      return true;
    });
  },
  deleteWall(payload) {
    const wall = payload?.wall || payload;
    if (
      !payload?.confirmed &&
      !window.confirm(this.$t("vtt.wall.deleteConfirm"))
    )
      return false;
    return perform(this, `${wall.sceneId}:${wall.id}`, async () => {
      const current = latestWall(this, wall);
      const sent = await this.$store.dispatch("realtime/changeWall", {
        operation: "delete",
        wall: current,
      });
      if (sent) return true;
      await this.$store.dispatch("vtt/deleteWall", current);
      return true;
    });
  },
};
