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
    const name = source.indexOf('class="scene-token-name"');

    expect(name).toBeGreaterThan(buttonEnd);
    expect(source).toContain("<TokenResourceOverlay");
    expect(source).not.toContain("token.id !== hudTokenId");
    expect(source).not.toMatch(
      /v-if="tokenInfoVisible\(token\)"\s+class="scene-token-facing"/u,
    );
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
});
