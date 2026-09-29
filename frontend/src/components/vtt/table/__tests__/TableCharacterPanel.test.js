import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableCharacterPanel.vue",
);
const source = readFileSync(componentPath, "utf8");
const { descriptor } = parse(source, { filename: componentPath });

const componentOptions = () => {
  const executable = descriptor.script.content
    .replace(/^import .*?;\n/gmu, "")
    .replace(/import \{[\s\S]*?\} from .*?;\n/gmu, "")
    .replace("export default {", "return {");
  return new Function(
    "AuthenticatedImage",
    "CharacterSheetEditor",
    "TableCharacterAccessPanel",
    "TableCharacterCreateForm",
    "tableCharacterCreationMethods",
    "characterApiClient",
    "characterErrorKey",
    "resolveCharacterAvatar",
    "resolveCharacterTokenSource",
    "beginActorDrag",
    "endActorDrag",
    executable,
  )(
    {},
    {},
    {},
    {},
    {},
    {},
    () => "",
    () => "",
    () => "",
    () => null,
    () => {},
  );
};

describe("TableCharacterPanel", () => {
  it("keeps the character sheet out of the compact drawer", () => {
    const template = descriptor.template.content;

    expect(template).toContain("'table-character-panel--compact': compact");
    expect(template).toContain(
      '<div v-if="!compact" class="table-character-panel__sheet">',
    );
    expect(template).toContain('v-if="canCreate && !compact"');
  });

  it("docks the selected character outside the scrollable group list", () => {
    const template = descriptor.template.content;
    const selected = template.indexOf(
      'class="table-character-panel__selected-character"',
    );
    const groups = template.indexOf(
      'class="table-character-panel__groups-shell"',
    );
    const scroller = template.indexOf('ref="characterBrowser"');

    expect(selected).toBeGreaterThan(-1);
    expect(groups).toBeGreaterThan(selected);
    expect(scroller).toBeGreaterThan(groups);
    expect(template).toContain('class="table-character-panel__groups"');
    expect(template).toContain('@scroll.passive="syncCharacterScrollbar"');
  });

  it("places search and grouped list actions above the character list", () => {
    const template = descriptor.template.content;
    const toolbar = template.indexOf(
      'class="table-character-panel__search-bar"',
    );
    const groups = template.indexOf(
      'class="table-character-panel__groups-shell"',
    );

    expect(toolbar).toBeGreaterThan(-1);
    expect(toolbar).toBeLessThan(groups);
    expect(template).toContain('class="table-character-panel__search-field"');
    expect(template).toContain("vtt.table.characters.listActions");
  });

  it("emits the chosen character only when HUD selection is permitted", () => {
    const emit = vi.fn();
    const selectCharacterForHud =
      componentOptions().methods.selectCharacterForHud;

    selectCharacterForHud.call({ canSelectForHud: false, $emit: emit }, 9);
    expect(emit).not.toHaveBeenCalled();

    selectCharacterForHud.call({ canSelectForHud: true, $emit: emit }, "9");
    expect(emit).toHaveBeenCalledWith("select-for-hud", 9);
  });

  it("offers player HUD selection for a character with edit access", () => {
    const canSelectCharacterForHud =
      componentOptions().methods.canSelectCharacterForHud;

    expect(
      canSelectCharacterForHud.call(
        { canManageGroups: false },
        { capabilities: { canEdit: true } },
      ),
    ).toBe(true);
    expect(
      canSelectCharacterForHud.call(
        { canManageGroups: false },
        { capabilities: { canEdit: false } },
      ),
    ).toBe(false);
  });

  it("clears the HUD selection from the docked character control", () => {
    const template = descriptor.template.content;
    const emit = vi.fn();
    const clearHudCharacterSelection =
      componentOptions().methods.clearHudCharacterSelection;

    expect(template).toContain('@click="clearHudCharacterSelection"');
    expect(template).toContain(
      "$t('vtt.table.playerHud.actions.clearSelection')",
    );

    clearHudCharacterSelection.call({ canSelectForHud: false, $emit: emit });
    expect(emit).not.toHaveBeenCalled();

    clearHudCharacterSelection.call({ canSelectForHud: true, $emit: emit });
    expect(emit).toHaveBeenCalledWith("select-for-hud", null);
  });

  it("does not automatically choose the first character after loading", () => {
    expect(descriptor.script.content).not.toContain(
      "this.selectCharacterForHud(defaultCharacter.id)",
    );
  });

  it("promotes the compact browser and exposes GM access management only in the full view", () => {
    const template = descriptor.template.content;

    expect(template).toContain('class="table-character-panel__promote"');
    expect(template).toContain("$emit('open-window')");
    expect(template).toContain("<TableCharacterAccessPanel");
    expect(template).toContain('v-if="canManageAccess && selectedId"');
    expect(template).toContain(':members="members"');
  });
});
