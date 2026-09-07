import { describe, expect, it } from "vitest";
import {
  appendGroup,
  characterGroupTree,
  createCharacterGroupLayout,
  moveCharacterToGroup,
  placeCharacterBefore,
  renameGroup,
} from "../characterPanelGroups";

const characters = [
  { id: 1, name: "Borin", ownerUserId: 7 },
  { id: 2, name: "Cultist", ownerUserId: null },
  { id: 3, name: "Alicia", ownerUserId: 7 },
];

describe("characterPanelGroups", () => {
  it("starts with immutable player and NPC roots", () => {
    const tree = characterGroupTree(
      createCharacterGroupLayout(null, characters),
      characters,
    );

    expect(tree.map((group) => group.id)).toEqual(["players", "npcs"]);
    expect(tree[0].characters.map((character) => character.id)).toEqual([3, 1]);
    expect(tree[1].characters.map((character) => character.id)).toEqual([2]);
  });

  it("keeps custom group nesting, placement and ordering", () => {
    let layout = createCharacterGroupLayout(null, characters);
    layout = appendGroup(layout, "npcs", "Cultists");
    const groupId = layout.groups[0].id;
    layout = renameGroup(layout, groupId, "Cult of the Eye");
    layout = moveCharacterToGroup(layout, 2, groupId);
    layout = placeCharacterBefore(layout, 1, 3);
    const tree = characterGroupTree(layout, characters);

    expect(tree[1].children[0].name).toBe("Cult of the Eye");
    expect(
      tree[1].children[0].characters.map((character) => character.id),
    ).toEqual([2]);
    expect(tree[0].characters.map((character) => character.id)).toEqual([1, 3]);
    expect(renameGroup(layout, "players", "Changed").groups).toEqual(
      layout.groups,
    );
  });
});
