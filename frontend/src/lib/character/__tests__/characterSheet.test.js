import { describe, expect, it } from "vitest";
import {
  createCharacterDraft,
  parseCharacterJson,
  textToList,
} from "@/lib/character/characterSheet";

describe("characterSheet", () => {
  it("creates editable sections without mutating the API character", () => {
    const character = {
      name: "Roch",
      revision: "3",
      data: { details: { race: "human" } },
    };
    const draft = createCharacterDraft(character);
    draft.data.details.race = "dwarf";

    expect(character.data.details.race).toBe("human");
    expect(draft.data.attributes.actual).toEqual({});
    expect(draft.data.attributes.skills).toEqual([]);
    expect(draft.revision).toBe(3);
  });

  it("parses extensible JSON only when the root is an object", () => {
    expect(parseCharacterJson('{"custom":{"luck":3}}')).toEqual({
      ok: true,
      data: { custom: { luck: 3 } },
    });
    expect(parseCharacterJson("[1,2]")).toEqual({
      ok: false,
      error: "json_object_required",
    });
    expect(parseCharacterJson("{").ok).toBe(false);
  });

  it("normalizes one skill or talent per line", () => {
    expect(textToList(" Dodge \n\n Gossip\r\n")).toEqual(["Dodge", "Gossip"]);
  });
});
