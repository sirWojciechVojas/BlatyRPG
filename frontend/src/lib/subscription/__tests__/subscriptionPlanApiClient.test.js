import { describe, expect, it, vi } from "vitest";
import { createSubscriptionPlanApiClient } from "@/lib/subscription/subscriptionPlanApiClient";

describe("subscriptionPlanApiClient", () => {
  it("loads and normalizes the public plan catalog", async () => {
    const request = vi.fn().mockResolvedValue({
      plans: [
        {
          code: "starter",
          price: { amountMinor: "1900", currency: "PLN", interval: "month" },
          limits: { tables: "3", storageMb: null },
          features: ["vtt", "realtimeChat"],
          highlighted: true,
        },
      ],
    });

    await expect(
      createSubscriptionPlanApiClient({ request }).list(),
    ).resolves.toEqual([
      {
        code: "starter",
        price: { amountMinor: 1900, currency: "PLN", interval: "month" },
        limits: { tables: 3, storageMb: null },
        features: ["vtt", "realtimeChat"],
        highlighted: true,
      },
    ]);
    expect(request).toHaveBeenCalledWith("/public/subscription-plans", {});
  });

  it("drops invalid plan and feature codes", async () => {
    const request = vi.fn().mockResolvedValue({
      plans: [
        { code: "../bad", features: ["vtt"] },
        { code: "valid", features: ["vtt", "<script>"] },
      ],
    });

    await expect(
      createSubscriptionPlanApiClient({ request }).list(),
    ).resolves.toEqual([
      expect.objectContaining({ code: "valid", features: ["vtt"] }),
    ]);
  });
});
