import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const filename = resolve(
  process.cwd(),
  "src/components/vtt/token/TokenSyncPanel.vue",
);
const descriptor = parse(readFileSync(filename, "utf8"), {
  filename,
}).descriptor;
const template = descriptor.template?.content || "";
const script = descriptor.script?.content || "";
const styles = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/styles/token-sync.css"),
  "utf8",
);
const sceneManager = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/SceneManagerPanel.vue"),
  "utf8",
);
const tokenSettings = readFileSync(
  resolve(process.cwd(), "src/components/vtt/token/TokenSettingsPanel.vue"),
  "utf8",
);
const tokenHud = readFileSync(
  resolve(process.cwd(), "src/components/vtt/token/TokenHud.vue"),
  "utf8",
);

describe("TokenSyncPanel contract", () => {
  it("filters targets by the same character and other scenes", () => {
    expect(script).toContain(
      "Number(token.characterId) === Number(this.sourceToken.characterId)",
    );
    expect(script).toContain(
      "Number(token.sceneId) !== Number(this.sourceToken.sceneId)",
    );
    expect(script).toContain("this.targetSceneIds.map(Number)");
  });

  it("provides all-current-token selection and requires a fresh preview", () => {
    expect(template).toContain('@click="toggleAll"');
    expect(template).toContain(':disabled="busy || !previewCurrent"');
    expect(script).toContain("previewSignature === this.currentSignature");
    expect(script).toContain(
      "sourceRevision: Number(this.preview.sourceRevision)",
    );
    expect(script).toContain("revision: Number(target.revision)");
  });

  it("keeps one-time transfer separate from live-link management", () => {
    expect(template).toContain("apply('transfer')");
    expect(template).toContain("apply('createLinks')");
    expect(template).toContain('@click="toggleLink(link)"');
    expect(template).toContain('@click="applyLink(link)"');
    expect(template).toContain('@click="removeLink(link)"');
    expect(script).toContain("window.confirm");
  });

  it("responds to the floating window width without horizontal overflow", () => {
    expect(styles).toContain("container-type: inline-size");
    expect(styles).toContain("@container (max-width: 760px)");
    expect(styles).toContain("grid-template-columns: 1fr");
  });

  it("starts synchronization from Scenes, not token editing controls", () => {
    expect(sceneManager).toContain('v-if="canManageTokenSync"');
    expect(sceneManager).toContain("$emit('token-sync')");
    expect(tokenSettings).not.toContain("openForToken");
    expect(tokenSettings).not.toContain("$emit('sync')");
    expect(tokenHud).not.toContain("$emit('sync')");
  });
});
