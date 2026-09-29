import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/wall/SceneDoorLayer.vue",
);
const component = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const styles = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/styles/scene-walls.css"),
  "utf8",
);

describe("SceneDoorLayer interaction contract", () => {
  it("opens the graphical door control with right click", () => {
    expect(component.template?.content).toContain(
      '@contextmenu.stop.prevent="handleContextMenu($event, wall)"',
    );
    expect(component.script?.content).toContain("this.toggle(wall)");
  });

  it("keeps the control above the active wall editor without stealing the segment", () => {
    expect(styles).toMatch(/\.scene-door-layer\s*\{[^}]*z-index:\s*220;/su);
    expect(styles).toMatch(
      /\.scene-door__segment\s*\{[^}]*pointer-events:\s*none;/su,
    );
    expect(styles).toMatch(
      /\.scene-door__control-hit\s*\{[^}]*pointer-events:\s*all;/su,
    );
  });
});
