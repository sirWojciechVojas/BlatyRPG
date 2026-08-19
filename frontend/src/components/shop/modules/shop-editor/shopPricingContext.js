import { inject } from "vue";

export const shopPricingContextKey = Symbol("shop-pricing-editor");

export const useShopPricingContext = () => {
  const context = inject(shopPricingContextKey);
  if (!context) {
    throw new Error("Shop pricing context is unavailable");
  }
  return context;
};
