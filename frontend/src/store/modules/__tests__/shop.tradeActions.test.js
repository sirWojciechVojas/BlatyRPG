import { describe, expect, it, vi } from "vitest";
import { createTradeActions } from "../shop/tradeActions";

const createHarness = (response, resolveOwnerCode = () => "BG1") => {
  const shopApiClient = {
    tradeBuy: vi.fn().mockResolvedValue(response),
    tradeSell: vi.fn().mockResolvedValue(response),
    quoteTradeBuyPayment: vi.fn().mockResolvedValue(response),
  };
  const actions = createTradeActions({
    buildTradeIdempotencyKey: (type) => `${type}-key`,
    isRecoverableShopApiError: () => false,
    isShopApiAuthorizationError: () => false,
    nextIdFromItems: () => 1,
    normalizeShopApiError: (error) => error,
    resolveOwnerCode,
    resolveShopApiConfig: () => ({ ownerCode: "BG1" }),
    shopApiClient,
    shouldAllowShopMockFallback: () => false,
    shouldUseShopApi: () => true,
  });
  const commit = vi.fn();
  const dispatch = vi.fn().mockResolvedValue({ ok: true });
  const context = {
    state: {
      activeShopId: 1,
      actors: [{ ownerCode: "BG1", name: "Test hero" }],
      campaignId: 1,
    },
    commit,
    dispatch,
  };
  const payload = {
    ownerCode: "BG1",
    shopId: 1,
    selections: [{ templateId: 10, instanceId: 20, quantity: 1 }],
  };
  return { actions, commit, context, dispatch, payload, shopApiClient };
};

describe("shop API trade actions", () => {
  it.each([
    ["playerBuyFromShop", "tradeBuy", "BUY"],
    ["playerSellToShop", "tradeSell", "SELL"],
  ])(
    "%s applies the transaction response without reloading both trade lists",
    async (actionName, clientMethod, transactionType) => {
      const response = {
        ok: true,
        walletBrass: 125,
        walletBalance: 125,
        walletCurrencyCode: "wfrp_empire",
        walletBalances: [
          { currencyCode: "wfrp_empire", balance: 125 },
          { currencyCode: "wfrp_bretonnia", balance: 480 },
        ],
        containerState: { containers: [{ id: 1 }] },
        items: [{ templateId: 10, quantity: 1 }],
      };
      const { actions, commit, context, dispatch, payload, shopApiClient } =
        createHarness(response);

      const result = await actions[actionName](context, payload);

      expect(result).toMatchObject({ handled: true, ok: true, data: response });
      expect(shopApiClient[clientMethod]).toHaveBeenCalledOnce();
      expect(commit).toHaveBeenCalledWith(
        "setContainerState",
        response.containerState,
      );
      expect(commit).toHaveBeenCalledWith(
        "setWalletBalances",
        response.walletBalances,
      );
      expect(commit).toHaveBeenCalledWith("setWalletBalance", {
        currencyCode: "wfrp_empire",
        balance: 125,
      });
      expect(commit).toHaveBeenCalledWith(
        "setLastTradeReceipt",
        expect.objectContaining({ transactionType }),
      );
      expect(dispatch).not.toHaveBeenCalledWith(
        "loadTradingData",
        expect.anything(),
      );
      expect(dispatch).toHaveBeenCalledWith("loadPlayerTradeLedger", {
        ownerCode: "BG1",
      });
    },
  );

  it("uses the character owner supplied by the trade view", async () => {
    const response = { ok: true, walletBrass: 125, containerState: {} };
    const resolveOwnerCode = vi.fn((_state, ownerCode) => ownerCode);
    const { actions, context, payload, shopApiClient } = createHarness(
      response,
      resolveOwnerCode,
    );

    await actions.playerBuyFromShop(context, payload);

    expect(resolveOwnerCode).toHaveBeenCalledWith(context.state, "BG1");
    expect(shopApiClient.tradeBuy).toHaveBeenCalledWith(
      expect.objectContaining({ ownerCode: "BG1" }),
      expect.objectContaining({ ownerCode: "BG1", actorName: "Test hero" }),
      "buy-key",
    );
  });

  it("requests an authoritative payment quote with selected purses", async () => {
    const response = { ok: true, paymentQuote: { canPay: true } };
    const { actions, context, payload, shopApiClient } =
      createHarness(response);

    const result = await actions.playerQuoteBuyPayment(context, {
      ...payload,
      selectedCurrencyCodes: ["wfrp_bretonnia"],
    });

    expect(result).toMatchObject({ handled: true, ok: true, data: response });
    expect(shopApiClient.quoteTradeBuyPayment).toHaveBeenCalledWith(
      expect.anything(),
      expect.objectContaining({
        ownerCode: "BG1",
        selectedCurrencyCodes: ["wfrp_bretonnia"],
      }),
    );
  });

  it("keeps the current container cache when the fast trade response omits it", async () => {
    const response = {
      ok: true,
      walletBalance: 125,
      walletCurrencyCode: "wfrp_empire",
    };
    const { actions, commit, context, payload } = createHarness(response);

    const result = await actions.playerSellToShop(context, payload);

    expect(result).toMatchObject({ handled: true, ok: true });
    expect(commit).not.toHaveBeenCalledWith("setContainerState", {});
  });
});
