import { beforeEach, describe, expect, it } from "vitest";
import { __resetShopSignboardGeneratorState } from "@/lib/shopSignboardGenerator";
import {
  drawShopSignboard,
  localizedShopTypeLabel,
  resolveSignboardGeneratorTypeId,
} from "@/lib/shopSignboardService";

const sequenceRandom = (values) => {
  let index = 0;
  return () => values[index++ % values.length];
};

const typeOptions = [
  {
    id: "piekarnia",
    labelPl: "Piekarnia",
    labelEn: "Bakery",
    category: "Żywność i zapasy",
  },
  {
    id: "platnerz",
    labelPl: "Płatnerz",
    labelEn: "Armourer",
    category: "Broń, pancerze i wojna",
  },
  {
    id: "paserska_komora",
    labelPl: "Paserska komora",
    labelEn: "Fence",
    category: "Półświatek",
  },
];

describe("shop signboard service", () => {
  beforeEach(() => __resetShopSignboardGeneratorState());

  it("maps current database shop types to specialized generator types", () => {
    expect(resolveSignboardGeneratorTypeId("piekarnia", "Piekarnia")).toBe(
      "bakery",
    );
    expect(resolveSignboardGeneratorTypeId("platnerz", "Płatnerz")).toBe(
      "armorer",
    );
    expect(
      resolveSignboardGeneratorTypeId("paserska_komora", "Paserska komora"),
    ).toBe("fence_goods");
  });

  it("draws names from different historical families for different trades", () => {
    const bakery = drawShopSignboard(
      { typeId: "piekarnia", typeOptions },
      { randomFn: sequenceRandom([0.11, 0.27, 0.63, 0.42]) },
    );
    const armourer = drawShopSignboard(
      { typeId: "platnerz", typeOptions },
      { randomFn: sequenceRandom([0.11, 0.27, 0.63, 0.42]) },
    );

    expect(bakery.meta.familyId).toBe("family_food");
    expect(armourer.meta.familyId).toBe("family_metal");
    expect(bakery.signboardName).not.toBe(armourer.signboardName);
  });

  it("uses locale-aware labels without losing the database id", () => {
    expect(localizedShopTypeLabel(typeOptions[0], "en")).toBe("Bakery");
    const result = drawShopSignboard({
      typeId: "piekarnia",
      typeOptions,
      locale: "en",
    });
    expect(result.meta.requestedTypeId).toBe("piekarnia");
    expect(result.meta.typeName).toBe("Bakery");
  });
});
