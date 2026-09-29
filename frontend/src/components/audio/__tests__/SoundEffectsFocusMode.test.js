import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const panel = readFileSync(
  resolve(process.cwd(), "src/components/audio/SoundEffectsPanel.vue"),
  "utf8",
);
const styles = readFileSync(
  resolve(process.cwd(), "src/components/audio/soundEffects.css"),
  "utf8",
);

describe("sound effects focus mode", () => {
  it("provides one accessible toggle for both side panels", () => {
    expect(panel).toContain(':aria-pressed="focusMode"');
    expect(panel).toContain('@click="toggleFocusMode"');
    expect(panel).toContain("this.focusMode = !this.focusMode");
  });

  it("expands the pad and hides both side columns", () => {
    expect(styles).toMatch(
      /\.sound-effects__full--focus\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/su,
    );
    expect(styles).toContain(
      ".sound-effects__full--focus > .sound-effects__screens-column",
    );
    expect(styles).toContain(
      ".sound-effects__full--focus > .sound-effects__library-column",
    );
  });
});
