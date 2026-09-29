import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const component = (name) => {
  const path = resolve(process.cwd(), `src/components/vtt/wall/${name}.vue`);
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor;
};

describe("wall sound editor", () => {
  it("uses the jukebox library and exposes configurable rules", () => {
    const editor = component("WallSoundRulesEditor");
    expect(editor.script.content).toContain("soundEffects/preview");
    expect(editor.template.content).toContain('max="12"');
    expect(editor.script.content).toContain("WALL_SOUND_TRIGGERS");
    expect(editor.template.content).toContain('value="offsetLine"');
    expect(editor.script.content).toContain("source.tracks");
  });

  it("renders live map regions and interactive geometry handles", () => {
    const overlay = component("WallSoundZoneOverlay");
    expect(overlay.template.content).toContain("reversedZones");
    expect(overlay.template.content).toContain("beginPointDrag");
    expect(overlay.template.content).toContain("beginOffsetDrag");
    expect(overlay.template.content).toContain(
      '@dblclick.stop.prevent="addPoint"',
    );
  });

  it("keeps the sound editor available for every wall", () => {
    const panel = component("WallPropertiesPanel");
    expect(panel.template.content).toContain("WallSoundRulesEditor");
    expect(panel.template.content).not.toContain(
      'v-if="doorLike" class="wall-properties__wide wall-properties__sound"',
    );
  });

  it("uses compact property tabs without squeezing nested header actions", () => {
    const panel = component("WallPropertiesPanel");
    const styles = readFileSync(
      resolve(process.cwd(), "src/components/vtt/scene/styles/scene-walls.css"),
      "utf8",
    );

    expect(panel.template.content).toContain("wall-properties__tabs");
    expect(panel.template.content).toContain("setTab('sound')");
    expect(panel.template.content).toContain("wall-properties__viewport");
    expect(styles).not.toContain(".wall-properties header button");
    expect(styles).toContain(".wall-sound-editor__header > button");
    expect(styles).toContain("min-width: 7.5rem");
  });
});
