import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/dashboard/CampaignCarousel.vue", () => ({ default: {} }));
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

  it("uses the campaign carousel and existing creation form", () => {
    expect(Object.keys(options.components)).toEqual([
      "CampaignCarousel",
      "CampaignCreateForm",
    ]);
  });

  it("does not duplicate the global account navigation", () => {
    const template = readFileSync(
      resolve(process.cwd(), "src/views/DashboardHomeView.vue"),
      "utf8",
    );

    expect(template).not.toContain("dashboard-topbar");
    expect(template).not.toContain("auth.profile.title");
    expect(template).not.toContain("dashboard.actions.logout");
  });
});
