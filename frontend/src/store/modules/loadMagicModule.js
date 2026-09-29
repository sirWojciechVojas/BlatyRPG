let magicModulePromise;

export const ensureMagicStoreModule = async (store) => {
  if (store.hasModule("magic")) return store.state.magic;
  if (!magicModulePromise) {
    magicModulePromise = import(
      /* webpackChunkName: "magic-store" */ "./magic"
    ).then((module) => module.default);
  }
  const magicModule = await magicModulePromise;
  if (!store.hasModule("magic")) store.registerModule("magic", magicModule);
  return store.state.magic;
};
