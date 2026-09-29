import { describe, expect, it } from "vitest";
import {
  actingTokenIds,
  isOtherUsersCharacterToken,
  secretDoorBlockedBySelection,
  selectedDoorTokens,
} from "@/lib/vtt/doorInteraction";

const characters = [
  { id: 7, ownerUserId: 11 },
  { id: 8, ownerUserId: 99 },
  { id: 9, ownerUserId: null },
];
const tokens = [
  { id: 21, characterId: 7 },
  { id: 22, characterId: 8 },
  { id: 23, characterId: 9 },
];

describe("door interaction context", () => {
  it("resolves all selected tokens without mixing string and numeric ids", () => {
    expect(selectedDoorTokens(tokens, null, ["21", 23])).toEqual([
      tokens[0],
      tokens[2],
    ]);
    expect(actingTokenIds([tokens[0], tokens[0], tokens[2]])).toEqual([21, 23]);
  });

  it("identifies a token owned by another user as a player token", () => {
    expect(isOtherUsersCharacterToken(tokens[0], characters, 99)).toBe(true);
    expect(isOtherUsersCharacterToken(tokens[1], characters, 99)).toBe(false);
    expect(isOtherUsersCharacterToken(tokens[2], characters, 99)).toBe(false);
  });

  it("blocks a secret door for a GM controlling a player's selected token", () => {
    expect(
      secretDoorBlockedBySelection(
        { doorType: "secret" },
        [tokens[0]],
        characters,
        99,
      ),
    ).toBe(true);
    expect(
      secretDoorBlockedBySelection(
        { doorType: "door" },
        [tokens[0]],
        characters,
        99,
      ),
    ).toBe(false);
    expect(
      secretDoorBlockedBySelection(
        { doorType: "secret" },
        [tokens[1]],
        characters,
        99,
      ),
    ).toBe(false);
  });
});
