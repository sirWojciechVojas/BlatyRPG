import { describe, expect, it } from "vitest";
import { toggleTokenStatus } from "@/lib/vtt/tokenStatuses";

describe("tokenStatuses", () => {
  it("adds and removes a quick status without replacing other statuses", () => {
    expect(toggleTokenStatus(["poisoned"], "bleeding")).toEqual([
      "poisoned",
      "bleeding",
    ]);
    expect(toggleTokenStatus(["poisoned", "bleeding"], "poisoned")).toEqual([
      "bleeding",
    ]);
  });
});
