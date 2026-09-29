import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const source = (name) => {
  const filename = resolve(
    process.cwd(),
    `src/components/vtt/compendium/${name}`,
  );
  return parse(readFileSync(filename, "utf8"), { filename }).descriptor;
};

describe("compendium workspace contract", () => {
  it("keeps public and GM content in explicit audience tabs", () => {
    const descriptor = source("CompendiumEntryEditor.vue");
    expect(descriptor.template.content).toContain("compendium.content");
    expect(descriptor.template.content).toContain("gmNotes");
    expect(descriptor.script.content).toContain("publicContent:");
    expect(descriptor.script.content).toContain("gmContent:");
  });

  it("curates source sections before full knowledge reaches a hero", () => {
    const entry = source("CompendiumEntryView.vue");
    const knowledge = source("CompendiumBestiaryKnowledge.vue");

    expect(entry.template.content).toContain("sourceContent");
    expect(entry.template.content).toContain("curatedSourceHtml");
    expect(entry.script.content).toContain("showSourceDocument");
    expect(knowledge.template.content).toContain("bestiarySourceSelection");
    expect(knowledge.template.content).toContain('type="checkbox"');
    expect(knowledge.script.content).toContain("selectedSourceHtml");
    expect(knowledge.script.content).toContain("draftSectionKeys");
  });

  it("autosaves after 1.5 seconds and preserves local data on conflict", () => {
    const script = source("CompendiumEntryEditor.vue").script.content;
    expect(script).toContain("setTimeout(() => this.saveNow(), 1500)");
    expect(script).toContain('error?.code === "revision_conflict"');
    expect(script).toContain("navigator.clipboard.writeText");
  });

  it("supports compact list-to-reader flow, timeline and NPC materialization", () => {
    const workspace = source("CompendiumWorkspace.vue");
    const entry = source("CompendiumEntryView.vue");
    expect(workspace.script.content).toContain("compactReading");
    expect(workspace.template.content).toContain("backToList");
    expect(workspace.script.content).not.toContain("openInWindows");
    expect(workspace.script.content).toContain('sort: "timeline"');
    expect(workspace.script.content).toContain("chronologyLabel(entry)");
    expect(workspace.template.content).toContain(
      "compendium-workspace__timeline-date",
    );
    expect(entry.script.content).toContain("compendiumApiClient.materialize");
  });

  it("keeps bestiary as a view inside campaign Compendium", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).not.toContain(
      "compendium-workspace--bestiary",
    );
    expect(workspace.template.content).not.toContain("modeLocked");
    expect(workspace.script.content).toContain("initialMode");
    expect(workspace.script.content).toContain(
      'this.filters.department = "bestiary"',
    );
    expect(workspace.script.content).toContain(
      'this.filters.type = this.creatureType?.id || ""',
    );
  });

  it("keeps NPCs in a dedicated counted view next to the bestiary", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain(
      '$t("vtt.table.compendium.npcs")',
    );
    expect(workspace.template.content).toContain(
      'departmentCount("characters")',
    );
    expect(workspace.script.content).toContain(
      'this.filters.department = "characters"',
    );
    expect(workspace.script.content).toContain('mode === "npcs"');
    expect(workspace.template.content).toContain(
      "compendium-workspace__npc-filters",
    );
    expect(workspace.template.content).toContain("filters.npcKind === 'named'");
    expect(workspace.template.content).toContain(
      "filters.npcKind === 'generic'",
    );
    expect(workspace.template.content).toContain(
      "compendium-workspace__npc-kind",
    );
    expect(workspace.script.content).toContain("setNpcKind(kind)");
    expect(workspace.script.content).toContain("overview.npcs?.[kind]");
  });

  it("shows the source beside each version in the entry list", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain("item.versionNumber");
    expect(workspace.template.content).toContain("item.sourceName");
    expect(workspace.template.content).toContain(
      "compendium-workspace__source-badge",
    );
  });

  it("keeps creature navigation in the generic Compendium restricted to GMs", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain('v-if="navigationCanSeeGm"');
    expect(workspace.template.content).toContain("visibleDepartments");
    expect(workspace.template.content).toContain("visibleTypes");
    expect(workspace.script.content).toContain(
      'this.canSeeGm || department.key !== "bestiary"',
    );
    expect(workspace.script.content).toContain(
      'this.canSeeGm || type.code !== "creature"',
    );
  });

  it("moves three-level hero knowledge into the main entry tabs", () => {
    const workspace = source("CompendiumWorkspace.vue");
    const entry = source("CompendiumEntryView.vue");
    const knowledge = source("CompendiumBestiaryKnowledge.vue");

    expect(workspace.template.content).toContain(
      ':manage-character-knowledge="managesCharacterBestiary"',
    );
    expect(workspace.template.content).not.toContain(
      "compendium-bestiary-assignments",
    );
    expect(workspace.script.content).not.toContain("bestiaryApiClient");
    expect(entry.template.content).toContain("CompendiumBestiaryKnowledge");
    expect(entry.template.content).toContain("bestiaryKnowledgeTab");
    expect(entry.template.content.indexOf("bestiaryKnowledgeTab")).toBeLessThan(
      entry.template.content.indexOf("gmNotes"),
    );
    expect(knowledge.template.content).toContain('type="radio"');
    expect(knowledge.script.content).toContain('value: "unknown"');
    expect(knowledge.script.content).toContain('value: "summary"');
    expect(knowledge.script.content).toContain('value: "full"');
    expect(knowledge.script.content).toContain(
      "bestiaryApiClient.saveAssignments",
    );
    expect(workspace.script.content).toContain('this.mode === "bestiary"');
    expect(workspace.script.content).not.toContain("this.modeLocked");
    expect(workspace.template.content).toContain(
      ':allow-campaign-reveal="!managesCharacterBestiary"',
    );
    expect(entry.script.content).toContain("allowCampaignReveal");
  });

  it("keeps bulk reveal monotonic and uses a separate hide action", () => {
    const knowledge = source("CompendiumBestiaryKnowledge.vue");

    expect(knowledge.template.content).toContain(
      "@click=\"revealAll('summary')\"",
    );
    expect(knowledge.template.content).toContain(
      "@click=\"revealAll('full')\"",
    );
    expect(knowledge.template.content).toContain('@click="hideAll"');
    expect(knowledge.script.content).toContain(
      "LEVEL_RANK[current] >= targetRank ? current : level",
    );
    expect(knowledge.script.content).toContain("dirtyAssignments");
    expect(knowledge.script.content).toContain("previewCharacterId");
  });

  it("loads overview and the first page concurrently while keeping GM navigation stable", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain('v-if="navigationCanSeeGm"');
    expect(workspace.script.content).toContain("canSeeGmHint");
    expect(workspace.script.content).toContain(
      "const [overview, earlyResponse]",
    );
    expect(workspace.script.content).toContain("Promise.all([");
    expect(workspace.script.content).toContain("requestEntries(page)");
    expect(workspace.script.content).toContain("applyEntriesResponse");
    expect(workspace.script.content).toContain(
      "if (directEntry > 0) void this.openById(directEntry)",
    );
    expect(workspace.script.content).not.toContain("this.entries[0]?.id");
    expect(workspace.script.content).not.toContain("$route?.query?.entry");
  });

  it("renders immediately with a compact non-blocking progress bar", () => {
    const workspace = source("CompendiumWorkspace.vue");
    const stylesheet = readFileSync(
      resolve(process.cwd(), "src/components/vtt/compendium/compendium.css"),
      "utf8",
    );

    expect(workspace.template.content).toContain(
      "compendium-workspace__loadbar",
    );
    expect(workspace.template.content).toContain(':aria-busy="entryLoading"');
    expect(workspace.template.content).not.toContain(
      "compendium-workspace__loading",
    );
    expect(workspace.script.content).toContain(
      "setTimeout(() => this.loadEntries(), 120)",
    );
    expect(stylesheet).toContain("linear-gradient(90deg, #31934d");
    expect(stylesheet).toContain("@keyframes compendium-loadbar");
  });

  it("prioritizes search and keeps advanced filters collapsed", () => {
    const workspace = source("CompendiumWorkspace.vue");
    const stylesheet = readFileSync(
      resolve(process.cwd(), "src/components/vtt/compendium/compendium.css"),
      "utf8",
    );

    expect(workspace.template.content).toContain(
      "compendium-workspace__search-clear",
    );
    expect(workspace.template.content).toContain(
      "compendium-workspace__filter-toggle",
    );
    expect(workspace.template.content).toContain('v-if="filtersOpen"');
    expect(workspace.template.content).toContain(
      ':aria-expanded="filtersOpen"',
    );
    expect(workspace.script.content).toContain("activeFilterCount()");
    expect(workspace.script.content).toContain("clearAdvancedFilters()");
    expect(stylesheet).toContain(".compendium-workspace__search:focus-within");
  });

  it("loads subsequent result pages automatically near the end of the list", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain(
      '@scroll.passive="handleListScroll"',
    );
    expect(workspace.template.content).toContain(
      '@wheel.passive="handleListScrollIntent"',
    );
    expect(workspace.template.content).toContain(':aria-busy="loadingMore"');
    expect(workspace.template.content).not.toContain(
      "compendium-workspace__more",
    );
    expect(workspace.script.content).toContain(
      "shouldLoadNextCompendiumPage(list)",
    );
    expect(workspace.script.content).toContain(
      "requestedPage = nextCompendiumPage(this.page, append)",
    );
    expect(workspace.script.content).toContain(
      "loaded && this.listScrollActivated",
    );
  });

  it("loads full source articles lazily and keeps navigation inside the workspace", () => {
    const workspace = source("CompendiumWorkspace.vue");
    const entry = source("CompendiumEntryView.vue");
    const document = source("CompendiumSourceDocument.vue");
    expect(workspace.template.content).toContain("overview.departments");
    expect(workspace.template.content).toContain("selectedEntry.sections");
    expect(workspace.script.content).toContain("campaignEntry(");
    expect(entry.template.content).toContain("CompendiumSourceDocument");
    expect(entry.template.content).toContain('loading="lazy"');
    expect(entry.script.content).toContain("fetchCorpusAssetBlob");
    expect(document.script.content).toContain("dataset.compendiumSourceId");
    expect(document.script.content).not.toContain("window.open");
  });

  it("does not flash the previously selected article while opening another", () => {
    const workspace = source("CompendiumWorkspace.vue");

    expect(workspace.template.content).toContain('v-else-if="entryLoading"');
    expect(workspace.script.content).toContain(
      "if (Number(this.selectedEntry?.id || 0) !== entryId)",
    );
    expect(workspace.script.content).toContain("this.selectedEntry = null");
    expect(workspace.script.content).toContain("this.history = []");
  });

  it("uploads inline images as protected assets and keeps only stable IDs in content", () => {
    const editor = source("CompendiumEntryEditor.vue");
    const entry = source("CompendiumEntryView.vue");
    expect(editor.template.content).toContain('@upload-image="uploadAsset');
    expect(editor.script.content).toContain("URL.createObjectURL(event.file)");
    expect(editor.script.content).toContain("delete result.attrs.src");
    expect(editor.script.content).toContain("fetchAssetBlob(asset.id)");
    expect(entry.template.content).toContain("hasPublicRichContent");
    expect(entry.template.content).toContain("hasGmRichContent");
  });
});
