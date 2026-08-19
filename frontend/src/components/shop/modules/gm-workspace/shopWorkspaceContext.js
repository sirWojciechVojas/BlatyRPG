import { inject } from "vue";

export const shopWorkspaceKey = Symbol("shop-gm-workspace");

export const useShopWorkspaceContext = () => {
  const workspace = inject(shopWorkspaceKey);
  if (!workspace) {
    throw new Error("Shop GM workspace context is unavailable");
  }
  return workspace;
};
