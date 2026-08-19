export const createRuntimePart2 = (runtime) => {
  const restoreFromTrash = (state, payload, toContainerId) => {
    const trashContainerId = runtime.getSystemContainerId(state, "TRASH");
    if (!trashContainerId) {
      return;
    }
    if (payload.instanceId) {
      runtime.moveInstance(
        state,
        payload.instanceId,
        toContainerId,
        "restoreFromTrash",
      );
      return;
    }
    if (!payload.templateId) {
      return;
    }
    const quantity = Math.max(1, Number(payload.quantity || 1));
    runtime.moveTemplateStack(
      state,
      payload.templateId,
      trashContainerId,
      toContainerId,
      quantity,
      "restoreFromTrash",
    );
  };
  Object.assign(runtime, {
    restoreFromTrash,
  });
  return {
    restoreFromTrash,
  };
};
