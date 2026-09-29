const queues = new Map();

const enqueue = (key, operation) => {
  const previous = queues.get(key) || Promise.resolve();
  const current = previous.catch(() => {}).then(operation);
  queues.set(key, current);
  current.finally(() => {
    if (queues.get(key) === current) queues.delete(key);
  });
  return current;
};

export const tableRegionMethods = {
  selectRegion(regionId) {
    this.$store.commit(
      "vtt/SELECT_REGION",
      regionId === null ? null : Number(regionId),
    );
  },
  createRegion(draft) {
    if (!this.selectedScene || !this.canManageRegions) return false;
    return enqueue(`${this.selectedScene.id}:region:create`, async () => {
      const sent = await this.$store.dispatch("realtime/changeRegion", {
        operation: "create",
        changes: draft,
      });
      if (!sent) await this.$store.dispatch("vtt/createRegion", draft);
      return true;
    }).catch((error) => {
      this.$store.commit("vtt/REGION_FAILED", {
        code: String(error?.code || "region_write_failed"),
        status: Number(error?.status) || 0,
      });
      return false;
    });
  },
  updateRegion({ region, changes }) {
    if (!region || !this.canManageRegions) return false;
    return enqueue(`${region.sceneId}:region:${region.id}`, async () => {
      const sent = await this.$store.dispatch("realtime/changeRegion", {
        operation: "update",
        region,
        changes,
      });
      if (!sent) {
        await this.$store.dispatch("vtt/updateRegion", { region, changes });
      }
      return true;
    });
  },
  deleteRegion(region) {
    if (!region || !window.confirm(this.$t("vtt.region.deleteConfirm"))) {
      return false;
    }
    return enqueue(`${region.sceneId}:region:${region.id}`, async () => {
      const sent = await this.$store.dispatch("realtime/changeRegion", {
        operation: "delete",
        region,
      });
      if (!sent) await this.$store.dispatch("vtt/deleteRegion", region);
      return true;
    });
  },
};
