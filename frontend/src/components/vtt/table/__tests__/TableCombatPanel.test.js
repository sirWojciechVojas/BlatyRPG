import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const component = (name) => {
  const path = resolve(process.cwd(), `src/components/vtt/table/${name}.vue`);
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor;
};

describe("TableCombatPanel", () => {
  it("shows token artwork and a compact resource-style movement meter", () => {
    const panel = component("TableCombatPanel").template.content;
    const row = component("CombatMovementRow").template.content;
    const identity = component("CombatTokenIdentity").template.content;

    expect(panel).toContain("<CombatMovementRow");
    expect(row).toContain("<CombatTokenIdentity");
    expect(row).toContain("<CombatMovementBar");
    expect(identity).toContain(':src="token.imageUrl"');
  });

  it("keeps only one movement editor open and adapts to panel width", () => {
    const panel = component("TableCombatPanel");
    const styles = readFileSync(
      resolve(
        process.cwd(),
        "src/components/vtt/scene/styles/table-combat-responsive.css",
      ),
      "utf8",
    );

    expect(panel.script.content).toContain("expandedTokenId");
    expect(panel.template.content).toContain(
      ':expanded="expandedTokenId === token.id"',
    );
    expect(styles).toContain("@container (max-width: 430px)");
  });
});
