import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const read = (path) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("campaign entry routes", () => {
  it("redirects the legacy campaign URL into the scene workspace", () => {
    const router = read("src/router/index.js");

    expect(router).toContain('path: "/campaigns/:campaignId"');
    expect(router).toContain('name: "scene-workspace"');
    expect(router).not.toContain("campaign-lobby");
    expect(router).not.toContain("CampaignLobbyView");
  });

  it("opens campaigns from the dashboard and account overview directly in VTT", () => {
    const card = read("src/components/dashboard/CampaignCard.vue");
    const overview = read("src/components/account/UserOverviewTab.vue");
    const workspace = read("src/views/options/SceneWorkspaceView.options.js");

    expect(card).toContain('name: "scene-workspace"');
    expect(card).toContain('hash: "#table-characters"');
    expect(workspace).toContain('"#table-characters": "characters"');
    expect(overview).toContain("name: 'scene-workspace'");
    expect(card).not.toContain("campaign-lobby");
    expect(overview).not.toContain("campaign-lobby");
  });
});
