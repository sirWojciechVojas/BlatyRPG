import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableSettingsPanel.vue",
);
const source = readFileSync(componentPath, "utf8");
const { descriptor } = parse(source, { filename: componentPath });

const componentOptions = () => {
  const executable = descriptor.script.content
    .replace(/^import .*?;\n/gmu, "")
    .replace(/import \{[\s\S]*?\} from .*?;\n/gmu, "")
    .replace("export default {", "return {");
  return new Function(
    "AudioDeviceSettings",
    "campaignSettingsDraft",
    "systemsFromGames",
    "worldsForSystem",
    "gameCatalogApiClient",
    executable,
  )(
    {},
    (campaign) => campaign,
    () => [],
    () => [],
    {},
  );
};

describe("TableSettingsPanel", () => {
  it("keeps campaign administration behind canManage while audio stays available", () => {
    const tabs = componentOptions().computed.tabs;

    expect(tabs.call({ canManage: true }).map(({ id }) => id)).toEqual([
      "campaign",
      "members",
      "audio",
    ]);
    expect(tabs.call({ canManage: false }).map(({ id }) => id)).toEqual([
      "audio",
    ]);
  });

  it("locks role and removal controls for the campaign owner", () => {
    const options = componentOptions();
    const isOwner = options.methods.isOwner;

    expect(
      isOwner.call({ campaign: { gameMasterId: 7 } }, { userId: "7" }),
    ).toBe(true);
    expect(isOwner.call({ campaign: { gameMasterId: 7 } }, { userId: 8 })).toBe(
      false,
    );
    expect(descriptor.template.content).toContain(
      ':disabled="campaignBusy || isOwner(member)"',
    );
  });

  it("dispatches every migrated campaign operation through campaignContext", async () => {
    const options = componentOptions();
    const dispatch = vi.fn().mockResolvedValue({ id: 1 });
    const vm = {
      $store: { dispatch },
      campaignError: "",
      membersError: "",
      draft: { name: "Table", settings: { defaultGridSize: 50 } },
      inviteDraft: { identifier: "user", role: "player", message: "" },
      run: options.methods.run,
    };

    await options.methods.saveCampaign.call(vm);
    await options.methods.changeRole.call(vm, 9, "observer");
    await options.methods.removeMember.call(vm, 9);
    await options.methods.revokeInvitation.call(vm, 11);

    expect(dispatch.mock.calls.map(([action]) => action)).toEqual([
      "campaignContext/updateSettings",
      "campaignContext/changeMemberRole",
      "campaignContext/removeMember",
      "campaignContext/revokeInvitation",
    ]);
  });
});
