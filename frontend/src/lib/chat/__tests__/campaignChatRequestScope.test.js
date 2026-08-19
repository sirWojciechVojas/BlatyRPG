import { describe, expect, it, vi } from "vitest";
import {
  createCampaignChatRequestScope,
  createPendingChatMessage,
} from "../campaignChatRequestScope";

describe("campaign chat request scope", () => {
  it("invalidates responses after campaign reset and unmount", () => {
    const scope = createCampaignChatRequestScope();
    scope.mount();
    const first = scope.capture(1);
    expect(scope.isCurrent(first, 1)).toBe(true);
    scope.reset();
    expect(scope.isCurrent(first, 1)).toBe(false);
    const second = scope.capture(2);
    scope.unmount();
    expect(scope.isCurrent(second, 2)).toBe(false);
  });

  it("reuses nonce for the same pending body until success", () => {
    const factory = vi
      .fn()
      .mockReturnValueOnce("nonce-1")
      .mockReturnValueOnce("nonce-2");
    const pending = createPendingChatMessage(factory);
    expect(pending.nonceFor("hello")).toBe("nonce-1");
    expect(pending.nonceFor("hello")).toBe("nonce-1");
    expect(pending.nonceFor("changed")).toBe("nonce-2");
    expect(factory).toHaveBeenCalledTimes(2);
  });
});
