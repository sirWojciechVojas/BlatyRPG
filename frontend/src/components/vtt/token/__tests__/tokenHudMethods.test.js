import { describe, expect, it, vi } from "vitest";
import { tokenHudMethods } from "../tokenHudMethods";

describe("token HUD interactions", () => {
  it("opens the linked actor on an authorized token double click", () => {
    const emit = vi.fn();
    tokenHudMethods.openTokenActor.call(
      { $emit: emit },
      {
        characterId: 42,
        capabilities: { canControl: true },
      },
    );

    expect(emit).toHaveBeenCalledWith("open-actor", 42);
  });

  it("does not expose an actor without viewing rights", () => {
    const emit = vi.fn();
    tokenHudMethods.openTokenActor.call(
      { $emit: emit },
      { characterId: 42, capabilities: {} },
    );

    expect(emit).not.toHaveBeenCalled();
  });

  it("allows resource editing to managers and unlocked controllers", () => {
    expect(
      tokenHudMethods.resourceEditable.call(
        { busy: false },
        { locked: true, capabilities: { canManage: true } },
      ),
    ).toBe(true);
    expect(
      tokenHudMethods.resourceEditable.call(
        { busy: false },
        { locked: false, capabilities: { canControl: true } },
      ),
    ).toBe(true);
    expect(
      tokenHudMethods.resourceEditable.call(
        { busy: false },
        { locked: true, capabilities: { canControl: true } },
      ),
    ).toBe(false);
  });

  it("shows token information only while selected unless configured otherwise", () => {
    const context = {
      hasMultiSelection: false,
      tokenStates: { 7: { selected: false } },
      isTokenExpanded: vi.fn(() => false),
    };
    expect(
      tokenHudMethods.tokenInfoVisible.call(context, {
        id: 7,
        showInfoUnselected: false,
      }),
    ).toBe(false);
    expect(
      tokenHudMethods.tokenInfoVisible.call(context, {
        id: 7,
        showInfoUnselected: true,
      }),
    ).toBe(true);
    context.tokenStates[7].selected = true;
    expect(tokenHudMethods.tokenInfoVisible.call(context, { id: 7 })).toBe(
      false,
    );
    context.isTokenExpanded.mockReturnValue(true);
    expect(tokenHudMethods.tokenInfoVisible.call(context, { id: 7 })).toBe(
      true,
    );
  });

  it("hides token information for every member of a multi-selection", () => {
    const context = {
      hasMultiSelection: true,
      tokenStates: { 7: { selected: true } },
      isTokenExpanded: vi.fn(() => false),
    };

    expect(
      tokenHudMethods.tokenInfoVisible.call(context, {
        id: 7,
        showInfoUnselected: true,
      }),
    ).toBe(false);
  });

  it("keeps token settings open until the authoritative save succeeds", () => {
    let update;
    const context = {
      settingsTokenId: 7,
      $emit: vi.fn((event, payload) => {
        if (event === "update") update = payload;
      }),
    };

    tokenHudMethods.saveTokenSettings.call(
      context,
      { id: 7, sceneId: 4 },
      { name: "Updated token" },
    );

    expect(context.settingsTokenId).toBe(7);
    expect(update).toEqual(
      expect.objectContaining({
        token: expect.objectContaining({ id: 7 }),
        changes: { name: "Updated token" },
        onSuccess: expect.any(Function),
      }),
    );

    update.onSuccess();
    expect(context.settingsTokenId).toBeNull();
  });
});
