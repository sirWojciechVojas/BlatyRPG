import { enqueueLightWrite } from "./lightWriteQueue";

let activeWrites = 0;

const latestLight = (component, light) =>
  (
    component.$store.state.vtt?.lightsByScene?.[String(light.sceneId)] || []
  ).find((item) => item.id === light.id) || light;

const beginWrite = (component) => {
  activeWrites += 1;
  component.$store.commit("vtt/SET_LIGHT_PHASE", "saving");
};

const endWrite = (component) => {
  activeWrites = Math.max(0, activeWrites - 1);
  if (!activeWrites && component.$store.state.vtt?.lightPhase !== "error") {
    component.$store.commit("vtt/SET_LIGHT_PHASE", "ready");
  }
};

const reportError = (component, error) => {
  component.$store.commit("vtt/LIGHT_FAILED", {
    code: String(error?.code || "light_write_failed"),
    status: Number(error?.status) || 0,
    details: error?.details || null,
  });
};

const realtimeUpdate = async (component, light, changes, allowRetry = true) => {
  const current = latestLight(component, light);
  try {
    const sent = await component.$store.dispatch("realtime/changeLight", {
      operation: "update",
      light: current,
      changes,
    });
    if (sent) return true;
  } catch (error) {
    if (error?.status !== 409 || !allowRetry) throw error;
    await component.$store.dispatch("vtt/loadLights");
    return realtimeUpdate(component, light, changes, false);
  }
  await component.$store.dispatch("vtt/updateLight", {
    light: current,
    changes,
  });
  return true;
};

const perform = async (component, key, operation, callbacks = {}) => {
  beginWrite(component);
  try {
    const result = await enqueueLightWrite(key, operation);
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

export const tableLightMethods = {
  selectLight(lightId) {
    this.$store.commit(
      "vtt/SELECT_LIGHT",
      lightId === null ? null : Number(lightId),
    );
  },
  createLight(draft) {
    if (!this.selectedScene || !this.canManageLights) return false;
    const key = `${this.selectedScene.id}:create`;
    return perform(this, key, async () => {
      const sent = await this.$store.dispatch("realtime/changeLight", {
        operation: "create",
        changes: draft,
      });
      if (sent) return true;
      await this.$store.dispatch("vtt/createLight", draft);
      return true;
    });
  },
  updateLight({ light, changes, onSuccess, onError }) {
    const key = `${light.sceneId}:${light.id}`;
    return perform(this, key, () => realtimeUpdate(this, light, changes), {
      onSuccess,
      onError,
    });
  },
  updateGlobalLight(level) {
    if (!this.selectedScene || !this.canManageLights) return false;
    const value = Math.min(1, Math.max(0, Number(level) || 0));
    const key = `${this.selectedScene.id}:global`;
    return perform(this, key, () =>
      this.$store.dispatch("vtt/updateSelectedScene", {
        globalLightLevel: value,
      }),
    );
  },
  deleteLight(light) {
    if (!window.confirm(this.$t("vtt.light.deleteConfirm"))) return false;
    const key = `${light.sceneId}:${light.id}`;
    return perform(this, key, async () => {
      const current = latestLight(this, light);
      const sent = await this.$store.dispatch("realtime/changeLight", {
        operation: "delete",
        light: current,
      });
      if (sent) return true;
      await this.$store.dispatch("vtt/deleteLight", current);
      return true;
    });
  },
};
