export const createDetailsRuntimePart1 = (runtime) => {
  const t = (key, values = {}) => runtime.i18n.global.t(key, values);
  Object.assign(runtime, {
    t,
  });
  return {
    t,
  };
};
