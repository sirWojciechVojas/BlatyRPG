import { bindTradeOptions } from "./tradeOptionsRuntime";
import { actionsOptions } from "./options/actions";

export const useShopTradeModalActions = (ctx, deps = {}) =>
  bindTradeOptions(ctx, deps, actionsOptions);

export default useShopTradeModalActions;
