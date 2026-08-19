export const createContainersMethodsPart2 = (runtime) => ({
  buildContainerStateFromStore() {
    const input = {
      templates: this.templateItems || [],
      shops: this.shops || [],
      inventoryItems: this.inventoryItems || [],
      trashItems: this.trashItems || [],
      serverState: this.serverContainerState,
    };
    if (runtime.buildServerContainerState(this, runtime, input)) return;
    runtime.buildDemoContainerState(this, runtime, input);
  },
});
