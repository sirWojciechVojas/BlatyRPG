import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, nextTick } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import { selectSourceSections } from "@/lib/compendium/sourceSectionSelection";

const filename = resolve(
  process.cwd(),
  "src/components/vtt/compendium/CompendiumBestiaryKnowledge.vue",
);
const descriptor = parse(readFileSync(filename, "utf8"), {
  filename,
}).descriptor;
const script = descriptor.script.content
  .replace(/import[\s\S]*?from\s+["'][^"']+["'];\n/gu, "")
  .replace("export default {", "return {");

const component = (api) => {
  const result = new Function(
    "bestiaryApiClient",
    "CompendiumDocument",
    "CompendiumSourceDocument",
    "selectSourceSections",
    script,
  )(
    api,
    { template: "<div class='public-document'></div>" },
    { template: "<div class='source-document'></div>" },
    selectSourceSections,
  );
  result.template = descriptor.template.content;
  return result;
};

const settle = async () => {
  await nextTick();
  await Promise.resolve();
  await nextTick();
};

let app;
let host;

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("CompendiumBestiaryKnowledge runtime", () => {
  it("keeps bulk reveals monotonic and saves all dirty heroes once", async () => {
    const api = {
      assignments: vi.fn().mockResolvedValue({
        assignments: [
          { characterId: 11, characterName: "Adelinde", level: "full" },
          { characterId: 12, characterName: "Gustav", level: "unknown" },
          { characterId: 14, characterName: "Legacy Hero", level: "summary" },
        ],
      }),
      saveAssignments: vi
        .fn()
        .mockImplementation((_campaignId, _entryId, assignments) =>
          Promise.resolve({
            assignments: assignments.map((assignment) => ({
              ...assignment,
              characterName: String(assignment.characterId),
            })),
          }),
        ),
    };
    host = document.createElement("div");
    document.body.append(host);
    app = createApp(component(api), {
      campaignId: 7,
      entry: {
        id: 51,
        title: "Horned Reaper",
        excerpt: "Forest creature",
        publicContent: { type: "doc", content: [] },
      },
    });
    app.config.globalProperties.$t = (key) => key;
    const vm = app.mount(host);
    await settle();

    vm.revealAll("summary");
    expect(vm.draftLevels).toEqual({
      11: "full",
      12: "summary",
      14: "summary",
    });
    expect(vm.dirtyCount).toBe(1);

    vm.revealAll("full");
    expect(vm.draftLevels).toEqual({ 11: "full", 12: "full", 14: "full" });
    expect(vm.dirtyCount).toBe(2);
    await vm.save();
    await settle();

    expect(api.saveAssignments).toHaveBeenCalledTimes(1);
    expect(api.saveAssignments).toHaveBeenCalledWith(
      7,
      51,
      [
        { characterId: 12, level: "full" },
        { characterId: 14, level: "full" },
      ],
      undefined,
    );
    expect(vm.dirtyCount).toBe(0);
  });

  it("only lowers knowledge through an explicit hide action", async () => {
    const api = {
      assignments: vi.fn().mockResolvedValue({
        assignments: [
          { characterId: 11, characterName: "Adelinde", level: "full" },
          { characterId: 12, characterName: "Gustav", level: "summary" },
        ],
      }),
      saveAssignments: vi.fn(),
    };
    host = document.createElement("div");
    document.body.append(host);
    app = createApp(component(api), {
      campaignId: 7,
      entry: {
        id: 51,
        title: "Horned Reaper",
        publicContent: { type: "doc", content: [] },
      },
    });
    app.config.globalProperties.$t = (key) => key;
    const vm = app.mount(host);
    await settle();

    vm.revealAll("summary");
    expect(vm.draftLevels).toEqual({ 11: "full", 12: "summary" });
    vm.hideAll();
    expect(vm.draftLevels).toEqual({ 11: "unknown", 12: "unknown" });
    expect(vm.dirtyCount).toBe(2);
    vm.discard();
    expect(vm.draftLevels).toEqual({ 11: "full", 12: "summary" });
  });

  it("saves a curated source selection and previews only those sections", async () => {
    const api = {
      assignments: vi.fn().mockResolvedValue({
        sectionKeys: ["lead"],
        assignments: [
          { characterId: 11, characterName: "Adelinde", level: "full" },
        ],
      }),
      saveAssignments: vi.fn().mockResolvedValue({
        sectionKeys: ["lead", "habitat"],
        assignments: [],
      }),
    };
    host = document.createElement("div");
    document.body.append(host);
    app = createApp(component(api), {
      campaignId: 7,
      entry: {
        id: 51,
        title: "Horned Reaper",
        sourceBacked: true,
        sourceHtml:
          '<p>Introduction.</p><h2 id="habitat">Habitat</h2><p>Forest lore.</p><h2 id="combat">Combat</h2><p>Combat lore.</p>',
        sections: [
          { id: "lead", title: "Introduction" },
          { id: "habitat", title: "Habitat" },
          { id: "combat", title: "Combat" },
        ],
      },
    });
    app.config.globalProperties.$t = (key) => key;
    const vm = app.mount(host);
    await settle();

    expect(vm.selectedSourceHtml).toContain("Introduction.");
    expect(vm.selectedSourceHtml).not.toContain("Forest lore.");
    vm.toggleSection("habitat");
    expect(vm.dirtyCount).toBe(1);
    expect(vm.selectedSourceHtml).toContain("Forest lore.");
    expect(vm.selectedSourceHtml).not.toContain("Combat lore.");

    await vm.save();
    await settle();

    expect(api.saveAssignments).toHaveBeenCalledWith(
      7,
      51,
      [],
      ["lead", "habitat"],
    );
    expect(vm.dirtyCount).toBe(0);
  });
});
