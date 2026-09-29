import { afterEach, describe, expect, it, vi } from "vitest";
import {
  TABLE_WINDOW_Z_INDEX_END,
  TABLE_WINDOW_Z_INDEX_START,
  focusTableWindow,
  registerTableWindow,
} from "../tableWindowLayers";

const activeRegistrations = [];

const register = (id, update) => {
  const registration = registerTableWindow(id, update);
  activeRegistrations.push(registration);
  return registration;
};

afterEach(() => {
  while (activeRegistrations.length) {
    activeRegistrations.pop().unregister();
  }
});

describe("table window layers", () => {
  it("puts every newly registered window above the previous windows", () => {
    const first = vi.fn();
    const second = vi.fn();

    register("first", first);
    register("second", second);

    expect(first).toHaveBeenLastCalledWith(TABLE_WINDOW_Z_INDEX_START);
    expect(second).toHaveBeenLastCalledWith(TABLE_WINDOW_Z_INDEX_START + 1);
  });

  it("brings a focused window to the front across component owners", () => {
    const first = vi.fn();
    const second = vi.fn();
    const firstRegistration = register("first", first);
    register("second", second);

    firstRegistration.focus();

    expect(second).toHaveBeenLastCalledWith(TABLE_WINDOW_Z_INDEX_START);
    expect(first).toHaveBeenLastCalledWith(TABLE_WINDOW_Z_INDEX_START + 1);
    expect(focusTableWindow("second")).toBe(true);
    expect(second).toHaveBeenLastCalledWith(TABLE_WINDOW_Z_INDEX_START + 1);
  });

  it("keeps floating windows below modal dialogs", () => {
    expect(TABLE_WINDOW_Z_INDEX_END).toBeLessThan(900);
  });
});
