import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const layoutStyles = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/styles/table-layout.css"),
  "utf8",
);
const panelStyles = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/styles/table-panels.css"),
  "utf8",
);

describe("TableUtilityDrawer layout", () => {
  it("overlays the map without changing the workspace grid", () => {
    expect(layoutStyles).not.toContain(".scene-workspace__layout--drawer {");
    expect(layoutStyles).toMatch(
      /\.scene-workspace__layout > \.table-utility-drawer\s*{[^}]*position: absolute;/s,
    );
  });

  it("does not repeat the embedded chat title below the drawer title", () => {
    expect(panelStyles).toMatch(
      /\.table-utility-drawer__body\s*> \.campaign-chat--embedded\s*> \.campaign-chat__header\s*{[^}]*display:\s*none;/s,
    );
  });

  it("aligns every sidebar chat card to the same full-width column", () => {
    expect(panelStyles).toMatch(
      /\.table-utility-drawer__body\s*> \.campaign-chat--embedded\s*\.campaign-chat__message\s*{[^}]*width:\s*100%;[^}]*max-width:\s*100%;[^}]*margin-right:\s*0;[^}]*margin-left:\s*0;/s,
    );
  });
});
