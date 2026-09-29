import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/admin/AdminActivityTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminCampaignsTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminCharactersTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminOverviewTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminSystemTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminUsersTab.vue", () => ({ default: {} }));
vi.mock("@/components/admin/AdminIcon.vue", () => ({ default: {} }));

import options from "@/views/options/AdminView.options";

describe("AdminView dashboard", () => {
  it("provides compact operational tabs with live counters", () => {
    const tabs = options.computed.tabs.call({
      metrics: { users: 12, campaigns: 4 },
      characters: [{}, {}, {}],
      activity: [{}, {}],
      $t: (key) => key,
    });

    expect(tabs.map((tab) => tab.id)).toEqual([
      "overview",
      "users",
      "campaigns",
      "characters",
      "activity",
      "system",
    ]);
    expect(tabs.find((tab) => tab.id === "users").count).toBe(12);
    expect(tabs.find((tab) => tab.id === "activity").count).toBe(2);
    expect(tabs.map((tab) => tab.icon)).toEqual([
      "overview",
      "users",
      "campaigns",
      "characters",
      "activity",
      "system",
    ]);
  });

  it("uses the same background asset as the landing page", () => {
    const css = readFileSync(
      resolve(process.cwd(), "src/views/styles/AdminView.css"),
      "utf8",
    );

    expect(css).toContain('url("../../assets/app-ui/img/background.jpg")');
  });

  it("uses the full viewport without the legacy dashboard width cap", () => {
    const viewCss = readFileSync(
      resolve(process.cwd(), "src/views/styles/AdminView.css"),
      "utf8",
    );
    const compatibilityCss = readFileSync(
      resolve(process.cwd(), "src/styles/ui/compat-campaign-admin.css"),
      "utf8",
    );

    expect(viewCss).toContain(
      "height: calc(100dvh - var(--ui-navigation-height))",
    );
    expect(viewCss).toContain("grid-template-columns: 13rem minmax(0, 1fr)");
    expect(compatibilityCss).not.toContain("width: min(88rem");
  });

  it("gives the user list a fluid Bootstrap layout", () => {
    const usersTab = readFileSync(
      resolve(process.cwd(), "src/components/admin/AdminUsersTab.vue"),
      "utf8",
    );
    const adminView = readFileSync(
      resolve(process.cwd(), "src/views/AdminView.vue"),
      "utf8",
    );

    expect(usersTab).toContain("container-fluid h-100 p-0");
    expect(usersTab).toContain("col-12 col-xl-9 col-xxl-10");
    expect(usersTab).toContain("col-12 col-xl-3 col-xxl-2");
    expect(adminView).toContain(
      "activeTab === 'users' || activeTab === 'characters'",
    );
  });

  it("keeps every administrator navigation label fully visible", () => {
    const adminView = readFileSync(
      resolve(process.cwd(), "src/views/AdminView.vue"),
      "utf8",
    );
    const viewCss = readFileSync(
      resolve(process.cwd(), "src/views/styles/AdminView.css"),
      "utf8",
    );

    expect(adminView).toContain(
      "nav flex-column align-items-stretch gap-1 p-1",
    );
    expect(adminView).toContain('class="nav-link text-start"');
    expect(viewCss).toContain("grid-template-columns: 13rem minmax(0, 1fr)");
    expect(viewCss).toContain(
      ".admin-tabs button strong {\n  min-width: max-content;",
    );
  });

  it("maps API validation details to the exact create-account fields", () => {
    const fieldErrors = options.methods.resolveCreateFieldErrors.call(
      { $t: (key) => key },
      {
        payload: {
          errors: {
            username: "Ta nazwa użytkownika jest już zajęta.",
            password: "Password must meet the policy.",
          },
        },
      },
    );

    expect(fieldErrors).toEqual({
      username: "admin.errors.fields.usernameTaken",
      password: "admin.errors.fields.password",
    });
  });
});
