import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const source = readFileSync(
  resolve(process.cwd(), "src/components/characters/CharacterSheetEditor.vue"),
  "utf8",
);
const { descriptor } = parse(source);
const walletSource = readFileSync(
  resolve(process.cwd(), "src/components/characters/CharacterWalletEditor.vue"),
  "utf8",
);
const walletDescriptor = parse(walletSource).descriptor;

describe("CharacterSheetEditor layout", () => {
  it("puts compact characteristics before the identity form", () => {
    const template = descriptor.template.content;
    expect(
      template.indexOf("character-sheet-section--attributes"),
    ).toBeLessThan(template.indexOf("character-sheet-section--identity"));
    expect(template).toContain("character-attribute-table");
  });

  it("uses dedicated catalog and wallet editors", () => {
    const template = descriptor.template.content;
    expect(template).toContain("CharacterDevelopmentEditor");
    expect(template).toContain("CharacterWalletEditor");
    expect(template).not.toContain('v-model="skillsText"');
    expect(template).not.toContain('v-model="talentsText"');
  });

  it("has one global save flow and staged wallet controls", () => {
    const template = descriptor.template.content;
    const walletTemplate = walletDescriptor.template.content;
    const script = readFileSync(
      resolve(
        process.cwd(),
        "src/components/characters/options/CharacterSheetEditor.options.js",
      ),
      "utf8",
    );

    expect(template).toContain('type="submit"');
    expect(template).toContain('ref="walletEditor"');
    expect(template).toContain('ref="developmentEditor"');
    expect(script).toContain("this.$refs.walletEditor?.save()");
    expect(script).toContain("this.$refs.developmentEditor?.save()");
    expect(walletTemplate).toContain('v-model="primaryCurrencyCode"');
    expect(walletTemplate).toContain("availableCurrencies");
    expect(walletTemplate).toContain("characters.wallet.add");
    expect(walletTemplate).not.toContain('characters.wallet.save"');
  });
});
