import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TablePanelContent.vue",
);
const settingsPanelPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableSettingsPanel.vue",
);
const jukeboxPanelPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxPanel.vue",
);
const jukeboxLibraryPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxLibraryTab.vue",
);

describe("TablePanelContent", () => {
  it("passes campaign members to chat for author avatars", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const start = template.indexOf("<CampaignChatPanel");
    const end = template.indexOf("/>", start);

    expect(template.slice(start, end)).toContain(':members="members"');
  });

  it("launches token synchronization from the Scenes module", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const start = template.indexOf("<SceneManagerPanel");
    const end = template.indexOf("/>", start);
    const scenes = template.slice(start, end);

    expect(scenes).toContain(':can-manage-token-sync="canManageTokenSync"');
    expect(scenes).toContain("@token-sync=\"$emit('token-sync', selectedId)\"");
  });

  it("forwards token creation capability to the character browser", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      ':can-create-token="canCreateToken"',
    );
    expect(descriptor.template.content).toContain(':campaign="campaign"');
    expect(descriptor.template.content).toContain(
      ":compact=\"instanceId === 'drawer'\"",
    );
    expect(descriptor.template.content).toContain(':can-select-for-hud="true"');
    expect(descriptor.template.content).toContain(
      "@select-for-hud=\"$emit('select-character', $event)\"",
    );
  });

  it("renders the searchable token template module and forwards center placement", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const start = template.indexOf("<TokenTemplatePanel");
    const end = template.indexOf("/>", start);
    const tokenTemplates = template.slice(start, end);

    expect(template).toContain("panelId === 'token-templates'");
    expect(tokenTemplates).toContain(':campaign-id="campaignId"');
    expect(tokenTemplates).toContain(':busy-id="tokenTemplateBusyId"');
    expect(tokenTemplates).toContain(
      "@place=\"$emit('place-token-template', $event)\"",
    );
  });

  it("routes movement approvals through the existing notifications window", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      "panelId === 'notifications'",
    );
    expect(descriptor.template.content).toContain("resolve-movement-request");
    expect(descriptor.template.content).toContain(
      ':manual-retry-available="manualRetryAvailable"',
    );
    expect(descriptor.template.content).toContain("retry-realtime");
  });

  it("renders campaign administration inside the VTT settings drawer", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("panelId === 'settings'");
    expect(template).toContain("<TableSettingsPanel");
    expect(template).toContain(':members="members"');
    expect(template).toContain(':invitations="invitations"');
    expect(template).toContain(':can-manage="canManage"');
  });

  it("uses the handout workspace as a list that opens document windows", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const start = template.indexOf("<HandoutWorkspace");
    const end = template.indexOf("/>", start);

    expect(template.slice(start, end)).toContain(
      ":compact=\"instanceId === 'drawer'\"",
    );
    expect(template.slice(start, end)).toContain("open-in-windows");
    expect(template.slice(start, end)).toContain("open-handout");
  });

  it("renders an independent handout document window", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("panelId === 'handout-document'");
    expect(template).toContain("<HandoutWindow");
    expect(template).toContain(':handout-id="handoutId"');
    expect(template).toContain(':start-editing="handoutStartEditing"');
    expect(template).toContain("handout-window-update");
  });

  it("renders one self-contained compendium in compact and full variants", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const start = template.indexOf("<CompendiumWorkspace");
    const end = template.indexOf("/>", start);
    const compendium = template.slice(start, end);

    expect(descriptor.script.content).toContain("webpackPrefetch: true");

    expect(template).toContain("panelId === 'compendium'");
    expect(template).toContain("<CompendiumWorkspace");
    expect(compendium).toContain(":compact=\"instanceId === 'drawer'\"");
    expect(compendium).toContain(':can-see-gm-hint="canSeeCompendiumBestiary"');
    expect(descriptor.script.content).toContain("canSeeCompendiumBestiary()");
    expect(descriptor.script.content).toContain("this.canManageTokenSync");
    expect(compendium).not.toContain("open-in-windows");
    expect(template).not.toContain("panelId === 'compendium-entry'");
  });

  it("opens the real combat tracker instead of a context placeholder", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain("panelId === 'combat'");
    expect(descriptor.template.content).toContain("<TableCombatPanel");
    expect(descriptor.template.content).toContain("combat-command");
  });

  it("renders only the character-scoped HUD bestiary as an independent module", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("panelId === 'bestiary'");
    expect(template).not.toContain("bestiaryScope");
    expect(template).not.toContain('initial-mode="bestiary"');
    expect(template).not.toContain("mode-locked");
    expect(template).not.toContain('appearance="bestiary"');
    expect(template).toContain("<BestiaryContent");
    expect(template).toContain(
      ':character-id="bestiaryCharacterId || characterId"',
    );
    expect(template).toContain(':variant="bestiaryVariant"');
    expect(descriptor.script.content).not.toContain("bestiaryScope");
    expect(descriptor.script.content).toContain(
      'webpackChunkName: "table-bestiary"',
    );
  });

  it("renders voice and jukebox as independent table modules", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain("panelId === 'voice'");
    expect(template).toContain("<VoicePanel");
    expect(template).toContain("panelId === 'jukebox'");
    expect(template).toContain("<JukeboxPanel");
    expect(template).not.toContain("<TableAudioPanel");
    expect(script).toContain('webpackChunkName: "table-voice"');
    expect(script).toContain('webpackChunkName: "table-jukebox"');
  });

  it("passes the active campaign and members to the setting calendar", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("panelId === 'calendar'");
    expect(template).toContain("<CampaignCalendar");
    expect(template).toContain(':campaign-id="campaignId"');
    expect(template).toContain(':members="members"');
    expect(descriptor.script.content).toContain(
      'import CampaignCalendar from "@/components/calendar/CampaignCalendar.vue"',
    );
    expect(descriptor.script.content).not.toContain(
      'webpackChunkName: "campaign-calendar"',
    );
  });

  it("keeps device configuration in settings and the complete library in the jukebox", () => {
    const settings = parse(readFileSync(settingsPanelPath, "utf8"), {
      filename: settingsPanelPath,
    }).descriptor.template.content;
    const jukebox = parse(readFileSync(jukeboxPanelPath, "utf8"), {
      filename: jukeboxPanelPath,
    }).descriptor.template.content;
    const library = parse(readFileSync(jukeboxLibraryPath, "utf8"), {
      filename: jukeboxLibraryPath,
    }).descriptor.template.content;

    expect(settings).toContain("<AudioDeviceSettings");
    expect(settings).toContain("activeTab === 'campaign'");
    expect(settings).toContain("activeTab === 'members'");
    expect(jukebox).toContain('v-for="tab in tabs"');
    expect(jukebox).toContain("<JukeboxLibraryTab");
    expect(library).toContain('v-for="track in filteredTracks"');
    expect(library).toContain('v-for="scope in scopes"');
    expect(library).toContain("sourceMode === 'device'");
    expect(library).toContain("deviceConfiguredInSettings");
  });
});
