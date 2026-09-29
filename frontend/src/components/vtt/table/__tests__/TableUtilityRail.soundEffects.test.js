import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableUtilityRail.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const utilities = [{ id: "scenes" }, { id: "token-sync", windowOnly: true }];
const Rail = new Function(
  "TableRailIcon",
  "IMPLEMENTED_TABLE_UTILITIES",
  "TABLE_UTILITIES",
  executable,
)({}, [], utilities);

afterEach(() => {
  vi.useRealTimers();
});

describe("TableUtilityRail click arbitration", () => {
  it("hides window-only tools from the main rail", () => {
    expect(Rail.data().utilities).toEqual([{ id: "scenes" }]);
  });

  it("delays a single click and emits only the compact panel action", () => {
    vi.useFakeTimers();
    const vm = { clickTimer: null, $emit: vi.fn() };

    Rail.methods.scheduleSelect.call(vm, "sound-effects");
    expect(vm.$emit).not.toHaveBeenCalled();
    vi.advanceTimersByTime(219);
    expect(vm.$emit).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);

    expect(vm.$emit).toHaveBeenCalledTimes(1);
    expect(vm.$emit).toHaveBeenCalledWith("select", "sound-effects");
  });

  it("cancels the pending single click when the icon is double-clicked", () => {
    vi.useFakeTimers();
    const vm = { clickTimer: null, $emit: vi.fn() };

    Rail.methods.scheduleSelect.call(vm, "sound-effects");
    Rail.methods.openWindow.call(vm, "sound-effects");
    vi.runAllTimers();

    expect(vm.$emit).toHaveBeenCalledTimes(1);
    expect(vm.$emit).toHaveBeenCalledWith("open", "sound-effects");
  });
});
