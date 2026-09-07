import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { reactive } from "vue";
import { describe, expect, it, vi } from "vitest";

const loadComponent = () => {
  const path = resolve(
    process.cwd(),
    "src/components/vtt/token/TokenRotationHandles.vue",
  );
  const { descriptor } = parse(readFileSync(path, "utf8"), { filename: path });
  const executable = descriptor.script.content
    .replace(
      /import \{ tokenPointerAngle \} from "@\/lib\/vtt\/tokenRotation";/u,
      "",
    )
    .replace("export default {", "return {");
  return new Function("tokenPointerAngle", executable)(vi.fn());
};

describe("TokenRotationHandles", () => {
  it("resets only the selected orientation control to its base angle", () => {
    const component = loadComponent();
    const clear = vi.fn();
    const clearClick = vi.fn();
    const emit = vi.fn();
    const context = { available: true, clear, clearClick, $emit: emit };

    component.methods.reset.call(context, "facing");

    expect(clear).toHaveBeenCalledOnce();
    expect(emit).toHaveBeenNthCalledWith(1, "cancel");
    expect(clearClick).toHaveBeenCalledWith("facing");
    expect(emit).toHaveBeenNthCalledWith(2, "commit", {
      field: "facing",
      value: 270,
    });
  });

  it("does not reset a disabled control", () => {
    const component = loadComponent();
    const context = {
      available: false,
      clear: vi.fn(),
      clearClick: vi.fn(),
      $emit: vi.fn(),
    };

    component.methods.reset.call(context, "rotation");

    expect(context.clear).not.toHaveBeenCalled();
    expect(context.$emit).not.toHaveBeenCalled();
  });

  it("resets the artwork rotation to zero", () => {
    const component = loadComponent();
    const emit = vi.fn();
    const context = {
      available: true,
      clear: vi.fn(),
      clearClick: vi.fn(),
      $emit: emit,
    };

    component.methods.reset.call(context, "rotation");

    expect(emit).toHaveBeenLastCalledWith("commit", {
      field: "rotation",
      value: 0,
    });
  });

  it("does not treat pointer jitter as a drag or commit", () => {
    const component = loadComponent();
    const clear = vi.fn();
    const emit = vi.fn();
    const drag = {
      id: 4,
      field: "rotation",
      value: 90,
      moved: false,
      start: { x: 100, y: 100 },
    };
    const context = {
      drag,
      move: component.methods.move,
      clear,
      $emit: emit,
    };

    component.methods.finish.call(context, {
      pointerId: 4,
      clientX: 100,
      clientY: 100,
    });

    expect(clear).toHaveBeenCalledOnce();
    expect(emit).not.toHaveBeenCalled();
  });

  it("rotates artwork by 90 degrees after a single click", () => {
    vi.useFakeTimers();
    const component = loadComponent();
    const emit = vi.fn();
    const context = reactive({
      clicks: {},
      clickSerial: 0,
      ignoreClickUntil: 0,
      clearClick: component.methods.clearClick,
      $emit: emit,
    });

    component.methods.handleClick.call(context, "rotation", 270, 1);
    vi.advanceTimersByTime(800);

    expect(emit).toHaveBeenCalledWith("commit", {
      field: "rotation",
      value: 0,
    });
    vi.useRealTimers();
  });

  it("aligns artwork with the facing handle after one facing click", () => {
    vi.useFakeTimers();
    const component = loadComponent();
    const emit = vi.fn();
    const context = reactive({
      clicks: {},
      clickSerial: 0,
      ignoreClickUntil: 0,
      clearClick: component.methods.clearClick,
      $emit: emit,
    });

    component.methods.handleClick.call(context, "facing", 35, 1);
    vi.advanceTimersByTime(800);

    expect(emit).toHaveBeenCalledWith("commit", {
      field: "rotation",
      value: 125,
    });
    vi.useRealTimers();
  });

  it("uses the second native click to reset instead of rotating", () => {
    vi.useFakeTimers();
    const component = loadComponent();
    const emit = vi.fn();
    const context = {
      available: true,
      clicks: {},
      clickSerial: 0,
      ignoreClickUntil: 0,
      clearClick: component.methods.clearClick,
      reset: component.methods.reset,
      clear: vi.fn(),
      $emit: emit,
    };

    component.methods.handleClick.call(context, "rotation", 180, 1);
    component.methods.handleClick.call(context, "rotation", 180, 2);
    vi.advanceTimersByTime(800);

    expect(emit).toHaveBeenLastCalledWith("commit", {
      field: "rotation",
      value: 0,
    });
    expect(emit).toHaveBeenCalledTimes(2);
    vi.useRealTimers();
  });
});
