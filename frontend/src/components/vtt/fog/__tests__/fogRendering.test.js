import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("Fog of War rendering pipeline", () => {
  it("uses a viewport alpha canvas without CSS pixelation or blur", () => {
    const component = readFileSync(
      resolve(process.cwd(), "src/components/vtt/fog/SceneFogLayer.vue"),
      "utf8",
    );
    const styles = readFileSync(
      resolve(process.cwd(), "src/components/vtt/scene/styles/scene-fog.css"),
      "utf8",
    );
    expect(component).toContain("fogBackingMetrics");
    expect(component).toContain("visibilityGeometry");
    expect(component).toContain('imageSmoothingQuality = "high"');
    expect(styles).not.toContain("image-rendering: pixelated");
    expect(styles).not.toMatch(/\.scene-fog-layer__mask[^}]*filter:\s*blur/su);
  });
});
