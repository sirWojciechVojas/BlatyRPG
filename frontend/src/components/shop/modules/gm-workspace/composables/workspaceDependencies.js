export const exposeMutable = (target, descriptors) => {
  for (const [name, descriptor] of Object.entries(descriptors)) {
    Object.defineProperty(target, name, {
      configurable: true,
      enumerable: true,
      get: descriptor.get,
      set: descriptor.set,
    });
  }
};
