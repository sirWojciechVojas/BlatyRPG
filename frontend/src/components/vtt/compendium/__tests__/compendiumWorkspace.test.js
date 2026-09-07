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
    expect(descriptor.template.content).toContain("playerKnowledge");
    expect(descriptor.template.content).toContain("gmNotes");
    expect(descriptor.script.content).toContain("publicContent:");
    expect(descriptor.script.content).toContain("gmContent:");
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
    expect(entry.script.content).toContain("compendiumApiClient.materialize");
  });
});
