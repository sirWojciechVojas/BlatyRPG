import { describe, expect, it } from "vitest";
import {
  clampPercent,
  cloneDataWithNumber,
  createPlayerCharacterHudModel,
  selectHudCharacter,
  selectPlayerCharacter,
  shopOwnerCodeForCharacter,
} from "../playerCharacterHudModel";

describe("playerCharacterHudModel", () => {
  it("selects every character with edit access using focus, state and deterministic id order", () => {
    const characters = [
      {
        id: 9,
        campaignId: 4,
        ownerUserId: 7,
        name: "Nine",
        capabilities: { canEdit: true },
      },
      {
        id: 1,
        campaignId: 4,
        ownerUserId: 99,
        name: "Shared",
        capabilities: { canEdit: true },
      },
      {
        id: 3,
        campaignId: 4,
        ownerUserId: 7,
        name: "Observer",
        capabilities: { canEdit: false },
      },
    ];

    expect(
      selectPlayerCharacter({
        characters,
        userId: 7,
        focusedCharacterId: 9,
        selectedCharacterId: 3,
      })?.id,
    ).toBe(9);
    expect(
      selectPlayerCharacter({
        characters,
        userId: 7,
        focusedCharacterId: 1,
        selectedCharacterId: 9,
      })?.id,
    ).toBe(1);
    expect(
      selectPlayerCharacter({
        characters,
        userId: 7,
        selectedCharacterId: 1,
      })?.id,
    ).toBe(1);
    expect(selectPlayerCharacter({ characters, userId: 88 })).toBeNull();
  });

  it("selects only an explicitly indicated campaign character for a manager", () => {
    const characters = [
      { id: 9, campaignId: 4, ownerUserId: 7, name: "Nine" },
      { id: 3, campaignId: 4, ownerUserId: 99, name: "Three" },
      { id: 1, campaignId: null, ownerUserId: null, name: "Unassigned" },
    ];

    expect(selectHudCharacter({ characters, canManage: true })).toBeNull();
    expect(
      selectHudCharacter({
        characters,
        canManage: true,
        focusedCharacterId: 3,
        campaignId: 4,
      })?.id,
    ).toBe(3);
    expect(
      selectHudCharacter({
        characters,
        canManage: true,
        focusedCharacterId: 77,
        campaignId: 4,
      }),
    ).toBeNull();
    expect(
      selectHudCharacter({
        characters,
        canManage: true,
        selectedCharacterId: 9,
        campaignId: 4,
      })?.id,
    ).toBe(9);
    expect(
      selectHudCharacter({
        characters,
        canManage: true,
        focusedCharacterId: 1,
        campaignId: 4,
      }),
    ).toBeNull();
  });

  it("maps the selected HUD character to its player-shop owner code", () => {
    const access = {
      characters: [
        { characterId: 9, ownerCode: "hero_9" },
        { characterId: 3, ownerCode: "hero_3" },
      ],
    };

    expect(shopOwnerCodeForCharacter(access, 3)).toBe("HERO_3");
    expect(shopOwnerCodeForCharacter(access, 99)).toBeNull();
  });

  it("clamps percentages and returns zero for absent or invalid maximums", () => {
    expect(clampPercent(-5, 10)).toBe(0);
    expect(clampPercent(15, 10)).toBe(100);
    expect(clampPercent(5, 0)).toBe(0);
    expect(clampPercent(5, undefined)).toBe(0);
    expect(Number.isNaN(clampPercent(5, undefined))).toBe(false);
  });

  it("uses linked token health and current backend-compatible experience", () => {
    const character = {
      id: 4,
      data: {
        attributes: { actual: { hp: 8, hp_max: 12 } },
        experience: {
          current: 25,
          total: 100,
          spentWithoutAlternatives: 50,
        },
      },
    };
    const tokens = [
      {
        id: 6,
        sceneId: 2,
        characterId: 4,
        locked: false,
        capabilities: { canControl: true },
        resources: {
          bars: [
            {
              label: "HP",
              value: 8,
              max: 12,
              attributePath: "attributes.actual.hp",
              maxAttributePath: "attributes.actual.hp_max",
            },
          ],
        },
      },
    ];

    const model = createPlayerCharacterHudModel(character, tokens);

    expect(model.health).toMatchObject({
      current: 8,
      maximum: 12,
      editable: true,
      source: { kind: "token", barIndex: 0 },
    });
    expect(model.experience.complex.current).toBe(100);
    expect(model.experience.minimum.current).toBe(75);
    expect(model.experience.distinguishesMinimum).toBe(true);
  });

  it("keeps absent maximum resources neutral and never emits NaN", () => {
    const model = createPlayerCharacterHudModel({
      data: {
        health: { current: 4 },
        experience: { current: 10 },
      },
    });

    expect(model.health).toMatchObject({
      maximum: null,
      percent: 0,
      editable: false,
    });
    expect(model.experience.complex.spentPercent).toBe(0);
    expect(model.experience.complex.availablePercent).toBe(0);
    expect(model.experience.editable).toBe(false);
    expect(Number.isNaN(model.experience.complex.availablePercent)).toBe(false);
  });

  it("updates only the recognized numeric path in character data", () => {
    const source = {
      details: { history: "Keep me" },
      health: { current: 4, max: 10 },
      nested: { untouched: [1, 2, 3] },
    };

    expect(cloneDataWithNumber(source, "health.current", 5)).toEqual({
      details: { history: "Keep me" },
      health: { current: 5, max: 10 },
      nested: { untouched: [1, 2, 3] },
    });
    expect(source.health.current).toBe(4);
  });
});
