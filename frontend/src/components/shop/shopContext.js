import { inject } from "vue";

export const tradeModalContextKey = Symbol("trade-modal-content-context");

export function useTradeModalContext() {
  // Shared context from TradeModalContent to avoid prop-drilling across nested shop views.
  const context = inject(tradeModalContextKey, null);
  if (!context) {
    throw new Error("Trade modal context is not available");
  }
  return context;
}
