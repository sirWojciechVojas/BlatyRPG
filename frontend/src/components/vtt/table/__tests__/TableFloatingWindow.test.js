import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableFloatingWindow.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/vtt/scene/styles/table-windows.css",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const styles = readFileSync(stylesPath, "utf8");

describe("TableFloatingWindow", () => {
  it("registers every instance in the shared window layer", () => {
    expect(descriptor.script.content).toContain("registerTableWindow");
    expect(descriptor.template.content).toContain('@pointerdown="focusWindow"');
    expect(descriptor.script.content).toContain('this.$emit("layer-change"');
  });

  it("uses a compact restorable minimized bar without changing saved bounds", () => {
    expect(descriptor.script.content).toContain("Math.min(192");
    expect(descriptor.script.content).toContain(
      'height: minimized ? "auto" : `${height}px`',
    );
    expect(descriptor.template.content).toContain("requestMinimize");
    expect(descriptor.template.content).toContain("requestHeaderAction");
    expect(styles).toMatch(
      /\.table-floating-window--minimized\s*\{[^}]*min-width:\s*0;/s,
    );
    expect(styles).toContain("min-height: 1.75rem");
  });

  it("supports maximization and an optional footer without affecting old windows", () => {
    expect(descriptor.template.content).toContain("model.maximizable");
    expect(descriptor.template.content).toContain('@click="requestMaximize"');
    expect(descriptor.template.content).toContain(
      "$slots.footer || model.footerText",
    );
    expect(descriptor.template.content).toContain('<slot name="footer">');
    expect(styles).toContain(".table-floating-window__footer");
  });

  it("keeps HUD modal chrome out of movable sidebar windows", () => {
    expect(styles).not.toContain(".table-floating-window--source-player-hud");
    expect(styles).not.toContain("gfx/buttonClose.png");
  });

  it("preserves dedicated chatbox proportions and artwork", () => {
    expect(descriptor.script.content).toContain("this.model.aspectRatio");
    expect(styles).toContain(".table-floating-window--chat");
    expect(styles).toContain(
      'url("../../../../assets/app-ui/img/chatbox.webp")',
    );
    expect(styles).toContain(
      ".table-floating-window--chat .campaign-chat__composer",
    );
    expect(styles).toMatch(
      /\.table-floating-window--chat \.campaign-chat__header\s*{[^}]*display:\s*none;/s,
    );
    expect(styles).toMatch(
      /\.table-floating-window--chat \.campaign-chat__message\s*{[^}]*width:\s*100%;[^}]*max-width:\s*100%;/s,
    );
    expect(styles).toMatch(
      /\.table-floating-window--chat \.campaign-chat__composer\s*{[^}]*padding:\s*0\.7rem 0\.35rem 0\.5rem;/s,
    );
    expect(styles).toMatch(
      /\.table-floating-window--chat \.campaign-chat__composer input\s*{[^}]*height:\s*1\.25rem;[^}]*min-height:\s*0;/s,
    );
  });
});
