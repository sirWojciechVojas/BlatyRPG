import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/admin/AdminActivityTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminCampaignsTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminOverviewTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminSystemTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminUsersTab.vue", () => ({ default: {} }));

import options from "@/views/options/AdminView.options";

describe("AdminView dashboard", () => {
  it("provides compact operational tabs with live counters", () => {
    const tabs = options.computed.tabs.call({
      metrics: { users: 12, campaigns: 4 },
      activity: [{}, {}],
      $t: (key) => key,
    });

    expect(tabs.map((tab) => tab.id)).toEqual([
      "overview",
      "users",
      "campaigns",
      "activity",
      "system",
    ]);
    expect(tabs.find((tab) => tab.id === "users").count).toBe(12);
    expect(tabs.find((tab) => tab.id === "activity").count).toBe(2);
  });

  it("uses the same background asset as the landing page", () => {
    const css = readFileSync(
      resolve(process.cwd(), "src/views/styles/AdminView.css"),
      "utf8",
    );

    expect(css).toContain('url("../../assets/app-ui/img/background.jpg")');
  });
});
