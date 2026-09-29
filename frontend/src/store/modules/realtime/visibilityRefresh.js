let timer = null;

export const scheduleVisibilityRefresh = (context, sceneId) => {
  const vtt = context.rootState.vtt;
  if (!vtt || Number(sceneId) !== Number(vtt.selectedSceneId)) return;
  const scene = vtt.scenes.find((item) => Number(item.id) === Number(sceneId));
  if (
    scene?.fogEnabled !== true ||
    context.rootGetters?.["vtt/canManage"] === true
  )
    return;
  clearTimeout(timer);
  timer = setTimeout(
    () =>
      context
        .dispatch("vtt/loadTokens", { silent: true }, { root: true })
        .catch(() => {}),
    80,
  );
};
