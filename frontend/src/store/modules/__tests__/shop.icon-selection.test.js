import { describe, expect, it, vi } from "vitest";
import { createActionGroup1 } from "@/store/modules/shop/actions/actionGroup1";
import { createContainerActions } from "@/store/modules/shop/containerActions";

const createActions = () =>
  createActionGroup1({
    buildPersistedTradePayload: vi.fn(),
    buildTradeIdempotencyKey: vi.fn(),
    cloneItem: (value) => value,
    createCatalogActions: () => ({}),
    createContainerActions: () => ({}),
    createLedgerActions: () => ({}),
    createTradeActions: () => ({}),
    isRecoverableShopApiError: () => false,
    isShopApiAuthorizationError: () => false,
    nextIdFromItems: () => 1,
    normalizeShopApiError: (error) => error,
    persistTradeData: vi.fn(),
    resolveOwnerCode: () => "BG1",
    // Deliberately return a different automatic suggestion. An explicit picker
    // choice must still win.
    resolveItemIconClass: () => "v0001",
    resolveShopApiConfig: () => ({}),
    shopApiClient: {},
    shouldAllowShopMockFallback: () => false,
    shouldUseShopApi: () => false,
  });

describe("shop template icon selection", () => {
  it("preserves the explicit picker icon when saving a template", async () => {
    const actions = createActions();
    const commit = vi.fn();

    const saved = await actions.saveTemplateRecord(
      { state: {}, commit, dispatch: vi.fn() },
      { ID: 7, NAME: "Bukłak", IMG_CLASS: "v0785" },
    );

    expect(saved.IMG_CLASS).toBe("v0785");
    expect(commit).toHaveBeenCalledWith(
      "updateTemplateItem",
      expect.objectContaining({ IMG_CLASS: "v0785" }),
    );
  });
});

describe("shop instance icon selection", () => {
  it.each([
    ["imgClass", "v1089"],
    ["icon", "v0785"],
  ])(
    "preserves an explicit picker icon passed as %s",
    async (field, selectedIcon) => {
      const updateItemInstance = vi.fn(() =>
        Promise.resolve({ itemInstance: { id: 29 } }),
      );
      const actions = createContainerActions({
        normalizeShopApiError: (error) => error,
        resolveItemIconClass: () => "v0183",
        resolveShopApiConfig: () => ({}),
        shopApiClient: { updateItemInstance },
        shouldUseShopApi: () => true,
      });

      await actions.saveItemInstance(
        { state: {}, commit: vi.fn() },
        {
          id: 29,
          name: "Belty kusznicze",
          [field]: selectedIcon,
        },
      );

      expect(updateItemInstance).toHaveBeenCalledWith(
        {},
        29,
        expect.objectContaining({ imgClass: selectedIcon }),
      );
    },
  );

  it("preserves an explicit picker icon when creating an instance", async () => {
    const createItemInstance = vi.fn(() =>
      Promise.resolve({ itemInstance: { id: 30 } }),
    );
    const actions = createContainerActions({
      normalizeShopApiError: (error) => error,
      resolveItemIconClass: () => "v0183",
      resolveShopApiConfig: () => ({}),
      shopApiClient: { createItemInstance },
      shouldUseShopApi: () => true,
    });

    await actions.createItemInstanceRecord(
      { state: {}, commit: vi.fn(), dispatch: vi.fn() },
      {
        templateId: 16,
        containerId: 2,
        name: "Belty kusznicze",
        imgClass: "v1089",
      },
    );

    expect(createItemInstance).toHaveBeenCalledWith(
      {},
      expect.objectContaining({ imgClass: "v1089" }),
    );
  });
});
