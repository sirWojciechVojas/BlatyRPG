export const createRuntimePart3Segment3 = (runtime) => {
  const priceTierForItem = (item) => {
    const price = Number(item?.PRIZE || 0);
    if (price <= 80) {
      return "cheap";
    }
    if (price <= 500) {
      return "mid";
    }
    if (price <= 2000) {
      return "high";
    }
    return "luxury";
  };
  Object.assign(runtime, {
    priceTierForItem,
  });
  return { priceTierForItem };
};
