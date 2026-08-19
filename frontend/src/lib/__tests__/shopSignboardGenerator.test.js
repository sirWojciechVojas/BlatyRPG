import { beforeEach, describe, expect, it } from "vitest";
import {
  __resetShopSignboardGeneratorState,
  generateShopSignboard,
} from "../shopSignboardGenerator";
import shopSignboardLexicon from "@/mock/shopSignboardLexicon";

const createSequenceRandom = (sequence = [0.5]) => {
  let index = 0;
  return () => {
    if (!sequence.length) {
      return 0.5;
    }
    const value = sequence[index % sequence.length];
    index += 1;
    return value;
  };
};

const createSeededRandom = (seed) => {
  let state = seed >>> 0;
  return () => {
    state = (state * 1664525 + 1013904223) >>> 0;
    return state / 4294967296;
  };
};

const MALFORMED_SIGNBOARD_RE =
  /Karczmiany|Beczyn|Pod\s+Pod|Pod\s+Lukiem|Półmilkiem|Gorzelnego|Korzeniarza|Łukmistrza|Oprawcy|Papiernicy|Młotek i Aukcja|Dziw i Kuriozum|U\s+(?:Ręki|Grete|Vanna|Peeka|Cichego)|[ÄĂĹÅ]|�/iu;

const baseContext = {
  typeId: "brewery_tavern",
  groupId: "food_drinks",
  domainId: "food_drink",
  typeName: "Browar i wyszynk",
  groupName: "Napoje i rozrywka stołu",
  domainName: "Żywność i napoje",
  locationType: "miasto",
  worldProfileId: "standard",
  legalStatus: "legal",
  wealthTier: "standard",
  reputation: "neutralna",
  seasonality: "caloroczny",
  ownerName: "Hupp",
  existingNames: [],
};

describe("shopSignboardGenerator", () => {
  beforeEach(() => {
    __resetShopSignboardGeneratorState();
  });

  it("keeps stable family for the same profile", () => {
    const a = generateShopSignboard(baseContext, {
      mode: "mixed",
      style: "medieval_lore",
      randomFn: createSequenceRandom([0.1, 0.8, 0.2]),
    });
    const b = generateShopSignboard(baseContext, {
      mode: "mixed",
      style: "medieval_lore",
      randomFn: createSequenceRandom([0.9, 0.3, 0.7]),
    });

    expect(a.meta.familyId).toBe(b.meta.familyId);
    expect(a.meta.familyId).toBe("family_tavern");
  });

  it("creates variations in mixed mode", () => {
    const a = generateShopSignboard(baseContext, {
      mode: "mixed",
      randomFn: createSequenceRandom([0.05, 0.1, 0.15]),
    });
    const b = generateShopSignboard(baseContext, {
      mode: "mixed",
      randomFn: createSequenceRandom([0.85, 0.9, 0.95]),
    });

    expect(a.signboardName).not.toBe(b.signboardName);
  });

  it("works without a selected type", () => {
    const result = generateShopSignboard(
      {
        ...baseContext,
        typeId: "",
        groupId: "",
        domainId: "",
        typeName: "",
        groupName: "",
        domainName: "",
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.4, 0.2]),
      },
    );

    expect(result.signboardName.length).toBeGreaterThan(0);
    expect(result.aliases.length).toBeGreaterThanOrEqual(3);
    expect(result.aliases.length).toBeLessThanOrEqual(6);
    expect(result.meta.familyId.length).toBeGreaterThan(0);
  });

  it("changes output by legal status", () => {
    const legal = generateShopSignboard(baseContext, {
      mode: "mixed",
      randomFn: createSequenceRandom([0.6, 0.2, 0.31]),
    });
    const illegal = generateShopSignboard(
      {
        ...baseContext,
        legalStatus: "illegal",
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.6, 0.2, 0.31]),
      },
    );

    expect(legal.signboardName).not.toBe(illegal.signboardName);
  });

  it("changes output by wealth tier", () => {
    const poor = generateShopSignboard(
      {
        ...baseContext,
        wealthTier: "biedny",
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.31, 0.54, 0.22]),
      },
    );
    const luxury = generateShopSignboard(
      {
        ...baseContext,
        wealthTier: "luksusowy",
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.31, 0.54, 0.22]),
      },
    );

    expect(poor.signboardName).not.toBe(luxury.signboardName);
  });

  it("returns unique aliases in range 3-6", () => {
    const result = generateShopSignboard(baseContext, {
      mode: "mixed",
      randomFn: createSequenceRandom([0.12, 0.45, 0.78, 0.35]),
    });

    const unique = new Set(result.aliases);
    expect(result.aliases.length).toBeGreaterThanOrEqual(3);
    expect(result.aliases.length).toBeLessThanOrEqual(6);
    expect(unique.size).toBe(result.aliases.length);
  });

  it("handles collisions with existing names", () => {
    const first = generateShopSignboard(baseContext, {
      mode: "mixed",
      randomFn: createSequenceRandom([0.18, 0.35, 0.52]),
    });
    const second = generateShopSignboard(
      {
        ...baseContext,
        existingNames: [first.signboardName],
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.18, 0.35, 0.52]),
      },
    );

    expect(second.signboardName).not.toBe(first.signboardName);
  });

  it("keeps polish diacritics in generated names", () => {
    const result = generateShopSignboard(
      {
        ...baseContext,
        typeId: "bakery",
        groupId: "food_bakery",
        domainId: "food_drink",
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.01, 0.02, 0.03]),
      },
    );

    expect(result.signboardName).toMatch(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/);
  });

  it("blocks low quality phrases", () => {
    const result = generateShopSignboard(
      {
        ...baseContext,
        existingNames: [
          "Pod Czarnym Gryfem",
          "Pod Mostem",
          "Pod Gościńcem",
          "Pęknięty Kufel",
          "Kości i Karty",
        ],
      },
      {
        mode: "mixed",
        randomFn: createSequenceRandom([0.99, 0.99, 0.99, 0.99]),
      },
    );

    expect(result.signboardName).not.toMatch(/Pod Znak|Rzetelny Pod|Cienisty/i);
  });

  it("does not repeat signboard names on repeated rolls within one type", () => {
    const generated = [];
    for (let i = 0; i < 12; i += 1) {
      const result = generateShopSignboard(baseContext, {
        mode: "mixed",
        randomFn: createSequenceRandom([0.25, 0.25, 0.25, 0.25]),
      });
      generated.push(result.signboardName);
    }
    const unique = new Set(generated);
    expect(unique.size).toBe(generated.length);
  });

  it("does not put an arbitrary owner name after the preposition u", () => {
    for (let seed = 1; seed <= 48; seed += 1) {
      const result = generateShopSignboard(
        { ...baseContext, ownerName: "Marta Kowalska" },
        { mode: "mixed", randomFn: createSeededRandom(seed) },
      );

      expect(result.signboardName).not.toMatch(/^U Marta Kowalska\b/u);
      expect(result.aliases).not.toContain("U Marta Kowalska");
    }
  });

  it("filters malformed legacy owner labels from names and aliases", () => {
    for (let seed = 101; seed <= 132; seed += 1) {
      const result = generateShopSignboard(
        { ...baseContext, ownerName: "Karczmiany" },
        { mode: "mixed", randomFn: createSeededRandom(seed) },
      );

      expect([result.signboardName, ...result.aliases].join(" | ")).not.toMatch(
        /Karczmiany/u,
      );
    }
  });

  it("keeps every supported trade free of known malformed constructions", () => {
    Object.keys(shopSignboardLexicon.familiesByTypeId).forEach(
      (typeId, typeIndex) => {
        __resetShopSignboardGeneratorState();
        for (let sample = 1; sample <= 12; sample += 1) {
          const result = generateShopSignboard(
            {
              ...baseContext,
              typeId,
              typeName: typeId.replaceAll("_", " "),
              ownerName: sample % 2 ? "Otto Kramer" : "",
            },
            {
              mode: "mixed",
              randomFn: createSeededRandom(typeIndex * 100 + sample),
            },
          );

          [result.signboardName, ...result.aliases].forEach((name) => {
            expect(name, `${typeId}: ${name}`).not.toMatch(
              MALFORMED_SIGNBOARD_RE,
            );
            expect(name.trim(), typeId).not.toBe("U");
          });
        }
      },
    );
  });

  it("offers a broad pool of evocative tavern names requested by the GM", () => {
    const tavern = shopSignboardLexicon.familiesByTypeId.brewery_tavern;
    const requestedNames = [
      "Pod Trzema Piórami",
      "Pod Srebrnym Grotem",
      "Pod Wędrownym Krukiem",
      "Pod Śpiącym Psem",
      "Wesoły Osioł",
      "Dłoń Aweliny",
      "Gospoda Ostatniej Szansy",
      "Pod Okiem Sigmara",
      "Puszka Pandory",
    ];

    expect(tavern.canonicalNames.length).toBeGreaterThan(80);
    expect(tavern.canonicalNames).toEqual(
      expect.arrayContaining(requestedNames),
    );

    requestedNames.forEach((expectedName) => {
      __resetShopSignboardGeneratorState();
      const canonicalIndex = tavern.canonicalNames.indexOf(expectedName);
      const result = generateShopSignboard(baseContext, {
        mode: "mixed",
        randomFn: createSequenceRandom([
          0.01,
          0.01,
          (canonicalIndex + 0.01) / tavern.canonicalNames.length,
          0.01,
          0.01,
        ]),
      });

      expect(result.signboardName).toBe(expectedName);
    });
  });

  it("keeps owner-role tavern names as an occasional variant", () => {
    const generated = [];
    for (let seed = 1; seed <= 80; seed += 1) {
      generated.push(
        generateShopSignboard(baseContext, {
          mode: "mixed",
          randomFn: createSeededRandom(seed * 7919),
        }).signboardName,
      );
    }

    const ownerRoleNames = generated.filter((name) => /^U\s/u.test(name));
    expect(ownerRoleNames.length).toBeLessThanOrEqual(16);
    expect(new Set(generated).size).toBe(generated.length);
  });
});
