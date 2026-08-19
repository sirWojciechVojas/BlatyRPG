import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/dashboard/CampaignCard.vue", () => ({ default: {} }));
vi.mock("@/components/dashboard/CampaignCreateForm.vue", () => ({
  default: {},
}));
import options from "@/views/options/DashboardHomeView.options";

describe("DashboardHomeView authentication boundary", () => {
  it("does not implement a second login flow", () => {
    expect(options.methods.login).toBeUndefined();
    expect(options.data()).not.toHaveProperty("loginError");
    expect(options.data()).not.toHaveProperty("isLoggingIn");
  });

  it("uses the existing campaign card and creation form", () => {
    expect(Object.keys(options.components)).toEqual([
      "CampaignCard",
      "CampaignCreateForm",
    ]);
  });
});
