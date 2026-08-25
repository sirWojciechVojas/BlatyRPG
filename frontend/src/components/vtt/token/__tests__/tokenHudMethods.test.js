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
});
