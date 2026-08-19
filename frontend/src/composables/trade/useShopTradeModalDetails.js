import { bindTradeOptions } from "./tradeOptionsRuntime";
import { detailsOptions } from "./options/details";

export const useShopTradeModalDetails = (ctx, deps = {}) =>
  bindTradeOptions(ctx, deps, detailsOptions);

export default useShopTradeModalDetails;
