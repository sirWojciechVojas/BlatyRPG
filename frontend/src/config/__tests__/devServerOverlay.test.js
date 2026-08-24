import { createRequire } from "node:module";
import { describe, expect, it } from "vitest";

const require = createRequire(import.meta.url);
const config = require("../../../vue.config.js");
const runtimeErrorFilter = config.devServer.client.overlay.runtimeErrors;

describe("development error overlay", () => {
  it.each([
    "ResizeObserver loop completed with undelivered notifications.",
    "ResizeObserver loop limit exceeded",
  ])("ignores the benign browser notification: %s", (message) => {
    expect(runtimeErrorFilter(new Error(message))).toBe(false);
  });

  it("continues to display real runtime failures", () => {
    expect(
      runtimeErrorFilter(new Error("Unexpected application failure")),
    ).toBe(true);
  });
});
