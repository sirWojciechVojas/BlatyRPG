import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/admin/AdminCompendiumTab.vue",
);

describe("AdminCompendiumTab", () => {
  it("keeps global operations outside the VTT while reusing the full editor", () => {
    const descriptor = parse(readFileSync(componentPath, "utf8"), {
      filename: componentPath,
    }).descriptor;

    expect(descriptor.template.content).toContain("<CompendiumWorkspace");
    expect(descriptor.template.content).toContain("savePolicy");
    expect(descriptor.template.content).toContain("saveProfile");
    expect(descriptor.template.content).toContain("rollbackImport");
    expect(descriptor.template.content).toContain("syncCatalog");
    expect(descriptor.template.content).toContain("deleteAsset");
    expect(descriptor.template.content).toContain("admin.compendium.rpgSystem");
    expect(descriptor.template.content).toContain("world.systemName");
    expect(descriptor.template.content).toContain("world.systemCode");
    expect(descriptor.template.content).toContain(
      '@submit.prevent="saveWorld"',
    );
    expect(descriptor.template.content).toContain("worldDraft.systemId");
    expect(descriptor.template.content).toContain("admin.compendium.newWorld");
    expect(descriptor.template.content).toContain('@click="editWorld"');
  });

  it("requires confirmation before exposing a source revision to players", () => {
    const script = parse(readFileSync(componentPath, "utf8"), {
      filename: componentPath,
    }).descriptor.script.content;

    expect(script).toContain("exposesToPlayers");
    expect(script).toContain("admin.compendium.exposeConfirm");
    expect(script).toContain('verificationStatus === "verified"');
  });
});
