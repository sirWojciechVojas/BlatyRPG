import runtime from "./runtime";
import { createApiClientPart1 } from "./clientPart1";
import { createApiClientPart2 } from "./clientPart2";

export const ShopApiClientError = runtime.ShopApiClientError;
export const isShopApiEnabled = runtime.isShopApiEnabled;
export const isShopApiFallbackEnabled = runtime.isShopApiFallbackEnabled;
export const isShopDemoMode = runtime.isShopDemoMode;
export const normalizeShopApiError = runtime.normalizeShopApiError;
export const isRecoverableShopApiError = runtime.isRecoverableShopApiError;
export const createShopApiConfig = runtime.createShopApiConfig;
export const shopApiClient = Object.assign(
  {},
  createApiClientPart1(runtime),
  createApiClientPart2(runtime),
);

export default shopApiClient;
