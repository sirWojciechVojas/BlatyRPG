import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const template = (name) => {
  const path = resolve(process.cwd(), `src/components/vtt/token/${name}.vue`);
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor
    .template.content;
};

describe("token presentation layout", () => {
  it("keeps the token name outside the rotated artwork button", () => {
    const source = template("SceneTokenLayer");
    const buttonEnd = source.indexOf(
      "</button>",
      source.indexOf('class="scene-token"'),
    );
    const information = source.indexOf("<TokenInfoStack");

    expect(information).toBeGreaterThan(buttonEnd);
    expect(template("TokenInfoStack")).toContain('class="scene-token-name"');
    expect(source).toContain("<TokenResourceOverlay");
    expect(source).not.toContain("token.id !== hudTokenId");
    expect(source).not.toMatch(
      /v-if="tokenInfoVisible\(token\)"\s+class="scene-token-facing"/u,
    );
  });

  it("places resource bars before the name in the stack below the token", () => {
    const source = template("TokenInfoStack");

    expect(source.indexOf("<TokenResourceBars")).toBeLessThan(
      source.indexOf('class="scene-token-name"'),
    );
    expect(source).not.toContain("TokenMovementBar");
  });

  it("balances four actions on both HUD rails", () => {
    const source = template("TokenHud");
    const left = source.split("token-hud__rail--left")[1].split("</div>")[0];
    const right = source.split("token-hud__rail--right")[1].split("</div>")[0];

    expect(left.match(/<button/g)).toHaveLength(4);
    expect(right.match(/<button/g)).toHaveLength(4);
  });

  it("gives each resource bubble a stable color slot", () => {
    expect(template("TokenResourceOverlay")).toContain(
      "token-resource-bubble--slot-${entry.index + 1}",
    );
  });

  it("renders the unsaved token draft with shared resource visuals", () => {
    const panel = template("TokenSettingsPanel");
    const preview = template("TokenSettingsPreview");

    expect(panel).toContain("<TokenSettingsPreview");
    expect(panel.indexOf("<TokenSettingsPreview")).toBeLessThan(
      panel.indexOf('class="token-settings-panel__body"'),
    );
    expect(preview).toContain("<TokenResourceBars");
    expect(preview).toContain("<TokenResourceOverlay");
    expect(preview).toContain("previewToken.rotation");
    expect(preview).toContain("previewToken.facing");
  });
});
