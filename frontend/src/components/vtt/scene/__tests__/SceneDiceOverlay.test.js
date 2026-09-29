import { beforeEach, describe, expect, it, vi } from "vitest";
import options from "../SceneDiceOverlay.options";

vi.mock("@/components/dice/DiceRoller.vue", () => ({ default: {} }));

const createContext = (overrides = {}) => {
  const roller = {
    resize: vi.fn(),
    roll: vi.fn(() => true),
    clear: vi.fn(() => true),
    showSelector: vi.fn(() => true),
  };
  const closeButton = { focus: vi.fn() };
  const context = {
    ...options.data(),
    visible: true,
    mode: "quick",
    rollRequest: 1,
    $refs: { roller, closeButton },
    $emit: vi.fn(),
    $nextTick: (callback) => callback(),
    ...overrides,
  };
  Object.entries(options.methods).forEach(([name, method]) => {
    context[name] = method.bind(context);
  });
  return { closeButton, context, roller };
};

describe("SceneDiceOverlay", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("keeps the first d100 request until DiceRoller is ready", () => {
    const { context, roller } = createContext();

    context.queueVisibleAction();
    expect(context.pendingAction).toBe("roll");
    expect(roller.roll).not.toHaveBeenCalled();

    context.handleReady();
    expect(roller.resize).toHaveBeenCalledOnce();
    expect(roller.roll).toHaveBeenCalledWith("1d100+1d10");
  });

  it("opens the full selector without starting an automatic roll", () => {
    const { context, roller } = createContext({ mode: "selector" });

    context.queueVisibleAction();
    context.handleReady();

    expect(roller.clear).toHaveBeenCalledOnce();
    expect(roller.showSelector).toHaveBeenCalledOnce();
    expect(roller.roll).not.toHaveBeenCalled();
  });

  it("clears and closes through the button or Escape", () => {
    const { context, roller } = createContext({ ready: true });

    context.requestClose();
    expect(roller.clear).toHaveBeenCalledOnce();
    expect(context.$emit).toHaveBeenCalledWith("close");

    const preventDefault = vi.fn();
    const stopPropagation = vi.fn();
    context.handleKeydown({
      key: "Escape",
      preventDefault,
      stopPropagation,
    });
    expect(preventDefault).toHaveBeenCalledOnce();
    expect(stopPropagation).toHaveBeenCalledOnce();
    expect(context.$emit).toHaveBeenCalledTimes(2);
  });

  it("ignores Escape while the overlay is hidden", () => {
    const { context } = createContext({ visible: false });

    context.handleKeydown({
      key: "Escape",
      preventDefault: vi.fn(),
      stopPropagation: vi.fn(),
    });

    expect(context.$emit).not.toHaveBeenCalled();
  });

  it("forwards completed rolls to the scene", () => {
    const { context } = createContext();
    const result = { notation: "1d100+1d10", total: 73 };

    context.handleRollComplete(result);

    expect(context.$emit).toHaveBeenCalledWith("roll-complete", result);
  });
});
