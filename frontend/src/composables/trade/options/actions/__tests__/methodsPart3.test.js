import { describe, expect, it, vi } from "vitest";
import { createActionsMethodsPart3 } from "../methodsPart3";

const buildHarness = (apiResult) => {
  const item = {
    ID: 41,
    INV_ID: 12,
    NAME: "Miecz imperialny",
    QUANTITY: 1,
  };
  const runtime = {
    GM_MODES: { TEMPLATES: "templates" },
    applySellToUi: vi.fn(),
    capturePlayerTradeUi: vi.fn(() => ({ inventoryItems: [item] })),
    clearShopNotificationZone: vi.fn(),
    notifyTradeProblem: vi.fn(),
    notifyTradeSuccess: vi.fn(),
  };
  const vm = {
    isGM: false,
    showSellAddForm: false,
    sellHasSelection: true,
    sellTotalBrass: 17,
    selectedSellIds: [41],
    selectedSellQuantities: { 41: 1 },
    sellItems: [item],
    buyTransactionPending: false,
    activeBgOwner: "BG1",
    activeShopId: 7,
    activeSettlementCurrencyCode: "wfrp_empire",
    playerSellToShop: vi.fn().mockResolvedValue(apiResult),
    persistTradingData: vi.fn(),
    resolveTradeApiAlert: vi.fn(() => "Odrzucono"),
    restorePlayerTradeUi: vi.fn(),
    sellTransactionPending: false,
    walletAlert: "previous alert",
  };
  const methods = createActionsMethodsPart3(runtime);
  return { item, methods, runtime, vm };
};

describe("handleSellAction", () => {
  it("waits for the API before confirming the sale", async () => {
    let resolveRequest;
    const pendingRequest = new Promise((resolve) => {
      resolveRequest = resolve;
    });
    const { methods, runtime, vm } = buildHarness({
      handled: true,
      ok: true,
    });
    vm.playerSellToShop.mockReturnValue(pendingRequest);

    const action = methods.handleSellAction.call(vm);

    expect(vm.playerSellToShop).toHaveBeenCalledWith({
      ownerCode: "BG1",
      shopId: 7,
      selections: [
        {
          templateId: 12,
          instanceId: 41,
          quantity: 1,
          clientId: 41,
        },
      ],
    });
    expect(runtime.applySellToUi).not.toHaveBeenCalled();
    expect(runtime.notifyTradeSuccess).not.toHaveBeenCalled();
    expect(vm.sellTransactionPending).toBe(true);

    resolveRequest({ handled: true, ok: true });
    await action;

    expect(runtime.applySellToUi).not.toHaveBeenCalled();
    expect(runtime.notifyTradeSuccess).toHaveBeenCalledOnce();
    expect(vm.sellTransactionPending).toBe(false);
  });

  it("keeps inventory unchanged when the API rejects the sale", async () => {
    const { methods, runtime, vm } = buildHarness({
      handled: true,
      ok: false,
      error: { code: "invalid_payload" },
    });

    await methods.handleSellAction.call(vm);

    expect(runtime.applySellToUi).not.toHaveBeenCalled();
    expect(vm.restorePlayerTradeUi).not.toHaveBeenCalled();
    expect(vm.persistTradingData).not.toHaveBeenCalled();
    expect(runtime.notifyTradeProblem).toHaveBeenCalledWith({
      zone: "sell",
      title: "Transakcja odrzucona",
      message: "Odrzucono",
    });
  });
});
