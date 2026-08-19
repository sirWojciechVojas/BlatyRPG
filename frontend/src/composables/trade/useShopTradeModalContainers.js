import { bindTradeOptions } from "./tradeOptionsRuntime";
import { containersOptions } from "./options/containers";

export const useShopTradeModalContainers = (ctx, deps = {}) =>
  bindTradeOptions(ctx, deps, containersOptions);

export default useShopTradeModalContainers;
