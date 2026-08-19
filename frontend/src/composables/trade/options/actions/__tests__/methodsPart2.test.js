import { describe, expect, it, vi } from "vitest";
import { createActionsMethodsPart2 } from "../methodsPart2";

describe("handleBuyAction", () => {
  it("waits for the API before confirming the purchase", async () => {
    let resolveRequest;
    const pendingRequest = new Promise((resolve) => {
      resolveRequest = resolve;
    });
    const item = {
      ID: 51,
      INV_ID: 13,
      NAME: "Kubek gliniany",
      QUANTITY: 1,
    };
    const runtime = {
      GM_MODES: { TEMPLATES: "templates", TRASH: "trash" },
      applyBuyToUi: vi.fn(),
      capturePlayerTradeUi: vi.fn(() => ({ shopItems: [item] })),
      clearShopNotificationZone: vi.fn(),
      formatTradeCoins: vi.fn(),
      notifyTradeProblem: vi.fn(),
      notifyTradeSuccess: vi.fn(),
      t: vi.fn(),
    };
    const vm = {
      activeBgOwner: "BG1",
      activeSettlementCurrencyCode: "wfrp_empire",
      activeShopId: 7,
      bgEncumbranceCurrent: 0,
      bgEncumbranceLimit: 100,
      bgEncumbranceUnitShort: "P",
      bgWalletBrass: 100,
      buyHasSelection: true,
      buyItems: [item],
      buyTotalBrass: 9,
      buyTransactionPending: false,
      isGM: false,
      persistTradingData: vi.fn(),
      playerBuyFromShop: vi.fn(() => pendingRequest),
      resolveItemCharge: vi.fn(() => 0),
      restorePlayerTradeUi: vi.fn(),
      selectedBuyIds: [51],
      selectedBuyQuantities: { 51: 1 },
      sellTransactionPending: false,
      walletAlert: "previous alert",
    };
    const methods = createActionsMethodsPart2(runtime);
    Object.assign(vm, methods);

    const action = methods.handleBuyAction.call(vm);

    expect(runtime.applyBuyToUi).not.toHaveBeenCalled();
    expect(runtime.notifyTradeSuccess).not.toHaveBeenCalled();
    expect(vm.buyTransactionPending).toBe(true);
    expect(vm.playerBuyFromShop).toHaveBeenCalledWith({
      ownerCode: "BG1",
      shopId: 7,
      selections: [
        {
          templateId: 13,
          instanceId: 51,
          quantity: 1,
          clientId: 51,
        },
      ],
      encumbrance: { current: 0, limit: 100, unit: "P" },
    });

    resolveRequest({ handled: true, ok: true });
    await action;

    expect(runtime.applyBuyToUi).not.toHaveBeenCalled();
    expect(runtime.notifyTradeSuccess).toHaveBeenCalledOnce();
    expect(vm.buyTransactionPending).toBe(false);
  });

  it("keeps a refreshed stale quote confirmable without closing the shop", async () => {
    const quote = {
      canPay: true,
      requiresConversion: true,
      quoteFingerprint: "fresh",
    };
    const runtime = {
      applyBuyToUi: vi.fn(),
      clearShopNotificationZone: vi.fn(),
      notifyTradeProblem: vi.fn(),
      notifyTradeSuccess: vi.fn(),
    };
    const methods = createActionsMethodsPart2(runtime);
    const vm = {
      activeBgOwner: "CHAR_5",
      activeShopId: 1,
      activeSettlementCurrencyCode: "wfrp_empire",
      bgEncumbranceCurrent: 0,
      bgEncumbranceLimit: 100,
      bgEncumbranceUnitShort: "P",
      buyTransactionPending: false,
      paymentQuote: {},
      pendingPaymentPurchase: null,
      playerBuyFromShop: vi.fn().mockResolvedValue({
        handled: true,
        ok: false,
        error: {
          code: "payment_quote_stale",
          payload: { paymentQuote: quote },
        },
      }),
      resolveTradeApiAlert: vi.fn(() => "Odświeżono"),
      showPaymentConversionDialog: false,
    };
    Object.assign(vm, methods);
    const selectedItems = [{ id: 8, item: { ID: 8 }, quantity: 1 }];
    const selections = [{ templateId: 7, instanceId: 8, quantity: 1 }];

    await vm.submitPlayerBuy(selectedItems, selections, 141);

    expect(vm.showPaymentConversionDialog).toBe(true);
    expect(vm.paymentQuote).toEqual(quote);
    expect(vm.pendingPaymentPurchase).toMatchObject({
      ownerCode: "CHAR_5",
      shopId: 1,
      totalCost: 141,
    });
  });
});
