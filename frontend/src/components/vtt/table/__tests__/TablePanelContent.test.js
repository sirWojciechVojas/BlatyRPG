import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TablePanelContent.vue",
);

describe("TablePanelContent", () => {
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
    expect(descriptor.template.content).toContain(
      ':can-select-for-hud="canManage"',
    );
    expect(descriptor.template.content).toContain(
      "@select-for-hud=\"$emit('select-character', $event)\"",
    );
  });

  it("routes movement approvals through the existing notifications window", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      "panelId === 'notifications'",
    );
    expect(descriptor.template.content).toContain("resolve-movement-request");
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

    expect(template).toContain("panelId === 'compendium'");
    expect(template).toContain("<CompendiumWorkspace");
    expect(compendium).toContain(":compact=\"instanceId === 'drawer'\"");
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
});
