import { describe, expect, it } from "vitest";
import { toggledSceneTool } from "../tableSceneTools";

describe("table scene tools", () => {
  it("returns to selection when the active tool is clicked again", () => {
    expect(toggledSceneTool("measure", "measure")).toBe("select");
  });

  it("switches directly to another tool", () => {
    expect(toggledSceneTool("measure", "templates")).toBe("templates");
  });

  it("keeps selection as the safe default tool", () => {
    expect(toggledSceneTool("select", "select")).toBe("select");
  });
});
