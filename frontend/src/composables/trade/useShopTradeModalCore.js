import { bindTradeOptions } from "./tradeOptionsRuntime";
import { coreOptions } from "./options/core";

export const useShopTradeModalCore = (ctx, deps = {}) =>
  bindTradeOptions(ctx, deps, coreOptions);

export default useShopTradeModalCore;
