import { describe, expect, it, vi } from "vitest";
import { tableTokenMethods } from "../tableTokenMethods";

describe("tableTokenMethods resources", () => {
  it("clears the active token when the canvas is selected", () => {
    const commit = vi.fn();
    tableTokenMethods.selectToken.call(
      { $store: { commit } },
      {
        tokenId: null,
        additive: false,
      },
    );

    expect(commit).toHaveBeenCalledWith("vtt/SELECT_TOKEN", { tokenId: null });
  });

  it("focuses a linked character card from the token", () => {
    const openUtilityWindow = vi.fn();
    const vm = { focusedCharacterId: null, openUtilityWindow };

    tableTokenMethods.openActor.call(vm, 42);

    expect(vm.focusedCharacterId).toBe(42);
    expect(openUtilityWindow).toHaveBeenCalledWith("characters");
  });

  it("refreshes actor context after a token resource update", async () => {
    const updated = { id: 7, revision: 3 };
    const dispatch = vi.fn((action) =>
      Promise.resolve(action === "vtt/updateToken" ? updated : null),
    );
    const vm = { $store: { dispatch } };
    const payload = {
      token: { id: 7, revision: 2 },
      changes: { resources: { bars: [], bubbles: [] } },
    };

    const result = await tableTokenMethods.updateToken.call(vm, payload);

    expect(result).toBe(updated);
    expect(dispatch).toHaveBeenCalledWith("vtt/updateToken", payload);
    expect(dispatch).toHaveBeenCalledWith("campaignContext/refresh");
  });

  it("reloads tokens after an optimistic revision conflict", async () => {
    const conflict = Object.assign(new Error("conflict"), { status: 409 });
    const dispatch = vi
      .fn()
      .mockRejectedValueOnce(conflict)
      .mockResolvedValueOnce(null);
    const vm = { $store: { dispatch } };

    await tableTokenMethods.updateToken.call(vm, {
      token: { id: 7 },
      changes: { resources: {} },
    });

    expect(dispatch).toHaveBeenLastCalledWith("vtt/loadTokens");
  });
});
