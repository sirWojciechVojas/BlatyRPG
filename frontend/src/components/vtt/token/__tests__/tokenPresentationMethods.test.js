import { describe, expect, it, vi } from "vitest";
import { tokenPresentationMethods } from "../tokenPresentationMethods";

const context = (selectedIds = []) => {
  const vm = {
    hudTokenId: null,
    expandedTokenId: null,
    presentationClickTokenId: null,
    presentationClickTimer: null,
    effectiveSelectedIds: selectedIds,
    hasMultiSelection: selectedIds.length > 1,
    $emit: vi.fn(),
    openTokenActor: vi.fn(),
  };
  return Object.assign(vm, tokenPresentationMethods);
};

describe("token presentation interactions", () => {
  it("uses the first click only to select a token", () => {
    const vm = context([]);

    vm.selectToken({ detail: 1 }, 7);

    expect(vm.$emit).toHaveBeenCalledWith("select", {
      tokenId: 7,
      additive: false,
    });
    expect(vm.expandedTokenId).toBeNull();
    expect(vm.presentationClickTimer).toBeNull();
  });

  it("reveals controls after a later second single click", () => {
    vi.useFakeTimers();
    const vm = context([7]);

    vm.selectToken({ detail: 1 }, 7);
    expect(vm.expandedTokenId).toBeNull();
    vi.advanceTimersByTime(500);

    expect(vm.expandedTokenId).toBe(7);
    expect(vm.isTokenExpanded(7)).toBe(true);
    vi.useRealTimers();
  });

  it("does not reveal controls as part of a double click", () => {
    vi.useFakeTimers();
    const vm = context([7]);

    vm.selectToken({ detail: 1 }, 7);
    vm.selectToken({ detail: 2 }, 7);
    vm.openTokenActorFromDoubleClick({ id: 7 });
    vi.advanceTimersByTime(500);

    expect(vm.expandedTokenId).toBeNull();
    expect(vm.openTokenActor).toHaveBeenCalledWith({ id: 7 });
    vi.useRealTimers();
  });
});
