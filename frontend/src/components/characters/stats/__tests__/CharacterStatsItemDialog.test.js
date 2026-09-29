import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/characters/stats/CharacterStatsItemDialog.vue",
);
const { descriptor } = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
});

const componentOptions = () => {
  const executable = descriptor.script.content
    .replace(/^import .*?;\n/gmu, "")
    .replace("export default {", "return {");
  return new Function("CurrencyDisplay", "ItemIcon", executable)({}, {});
};

const createContext = (options, item) => ({
  item,
  $t: (key) => key,
  $nextTick: (callback) => callback(),
  $refs: {},
  $emit: () => {},
  quantity: options.computed.quantity.call({ item }),
  currencyCode: options.computed.currencyCode.call({ item }),
});

describe("CharacterStatsItemDialog", () => {
  it("renders Bountify-style item and weapon data from the inventory payload", () => {
    const options = componentOptions();
    const context = createContext(options, {
      NAME: "Miecz rodowy",
      ITEM_CLASS: "WEAPON",
      ITEM_GENRE: "arms",
      QUANTITY: 2,
      CHARGE: 3,
      ACTIVE_PRICE: 120,
      CURRENCY: "wfrp_empire",
      ATTRIBUTES: ["MAGICAL", "RARE"],
      DESCRIPTION: "Stal odziedziczona po przodkach.",
      PERSONAL_DESC: "Rękojeść nosi herb rodu.",
      WEAPON: {
        TYPE: "sieczna",
        HANDED: "broń jednoręczna",
        DAMAGE: "S+1",
        DICE: "1K10",
        QUALITIES: "precyzyjna",
      },
    });

    const rows = options.computed.metadataRows.call(context);
    expect(rows).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "class", value: "WEAPON" }),
        expect.objectContaining({ key: "quantity", value: "2" }),
        expect.objectContaining({ key: "charge", value: "3" }),
        expect.objectContaining({ key: "value", value: 120, type: "currency" }),
        expect.objectContaining({ key: "attributes", value: "MAGICAL, RARE" }),
      ]),
    );
    expect(options.computed.weaponRows.call(context)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ key: "type", value: "sieczna" }),
        expect.objectContaining({ key: "damage", value: "S+1 1K10" }),
        expect.objectContaining({ key: "qualities", value: "precyzyjna" }),
      ]),
    );
    expect(options.computed.descriptionParts.call(context)).toEqual([
      "Stal odziedziczona po przodkach.",
      "Rękojeść nosi herb rodu.",
    ]);
  });

  it("keeps the right-click dialog accessible with a close control", () => {
    expect(descriptor.template.content).toContain('aria-modal="true"');
    expect(descriptor.template.content).toContain("@click=\"$emit('close')\"");
    expect(descriptor.template.content).toContain(
      "@click=\"$emit('primary-action')\"",
    );
    expect(descriptor.template.content).toContain(
      "@click=\"$emit('consume-action')\"",
    );
    expect(descriptor.template.content).toContain("mechanicsHelp");
    expect(descriptor.template.content).toContain("effectWindow");
    const footer = descriptor.template.content
      .split('class="character-stats-item-dialog__footer"')[1]
      .split("</footer>")[0];
    expect(footer).not.toContain("$emit('close')");
  });
});
