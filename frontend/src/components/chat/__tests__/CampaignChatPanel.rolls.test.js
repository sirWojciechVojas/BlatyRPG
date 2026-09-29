import { describe, expect, it, vi } from "vitest";
import options from "../options/CampaignChatPanel.options";
import { createDiceRollMessage } from "@/lib/chat/diceRollMessage";
import { campaignChatText } from "@/lib/chat/campaignChatText";
import { createMagicCastMessage } from "@/lib/chat/magicCastMessage";

vi.mock("@/lib/auth/authSession", () => ({
  authSession: { read: vi.fn(() => ({ user: { id: 3 } })) },
}));

describe("CampaignChatPanel dice rolls", () => {
  it("decorates a structured roll while leaving normal messages untouched", () => {
    const rollBody = createDiceRollMessage({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", display: "00" },
        { type: "d10", display: "0" },
      ],
      total: 100,
    });
    const messages = options.computed.messages.call({
      currentUserId: 3,
      chat: {
        messages: [
          {
            id: 1,
            body: rollBody,
            author: { id: 3, name: "Ada" },
          },
          {
            id: 2,
            body: "Hello",
            author: { id: 4, name: "Jan" },
          },
          {
            id: 3,
            body: "",
            author: { id: 3, name: "Ada" },
          },
        ],
      },
    });

    expect(messages[0].diceRoll).toMatchObject({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", value: "00" },
        { type: "d10", value: "0" },
      ],
      total: "100",
      groups: [
        {
          type: "d100",
          notation: "1d100",
          total: "0",
          dice: [{ type: "d100", value: "00", isMaximum: false }],
        },
        {
          type: "d10",
          notation: "1d10",
          total: "0",
          dice: [{ type: "d10", value: "0", isMaximum: false }],
        },
      ],
    });
    expect(messages[0].author.isCurrentUser).toBe(true);
    expect(messages[1].diceRoll).toBeNull();
    expect(messages).toHaveLength(2);
  });

  it("provides labels for the Foundry-style result card", () => {
    expect(
      campaignChatText("pl", "roll.summary", {
        name: "admin",
        formula: "1d100 + 1d10",
      }),
    ).toBe("admin rzuca 1d100 + 1d10");
    expect(campaignChatText("pl", "roll.flavor")).toBe("Rzut kośćmi");
    expect(campaignChatText("pl", "roll.subtotal")).toBe("Suma częściowa");
    expect(campaignChatText("pl", "roll.total")).toBe("Wynik");
    expect(campaignChatText("pl", "newMessage")).toBe("Nowa");
    expect(campaignChatText("en", "newMessage")).toBe("New");
  });

  it("matches author avatars from campaign members and creates initials", () => {
    const authorAvatarById = options.computed.authorAvatarById.call({
      members: [
        { userId: 3, avatarUrl: "/avatars/ada.webp" },
        { userId: 4, avatarUrl: "" },
      ],
    });
    const [message] = options.computed.messages.call({
      currentUserId: 4,
      authorAvatarById,
      chat: {
        messages: [
          { id: 8, body: "Hej", author: { id: 3, name: "Ada Nowak" } },
        ],
      },
    });

    expect(message.author.avatarUrl).toBe("/avatars/ada.webp");
    expect(message.author.initials).toBe("AN");
  });

  it("decorates an authoritative spell result as a magic card", () => {
    const body = createMagicCastMessage({
      characterName: "Elsa",
      cast: {
        id: 4,
        spell: { name: "Korona ognia" },
        result: {
          spell: { name: "Korona ognia" },
          powerDice: [9, 2],
          chaosDice: [9],
          modifier: 0,
          powerTotal: 11,
          castingNumber: 8,
          spellSucceeded: true,
          automaticFailure: false,
          willpowerTestRequired: false,
          manifestations: [{ face: 9, matchingDice: 2, severity: "minor" }],
          channel: { attempted: false },
          ingredient: null,
          targetDefense: { message: "Brak." },
          effect: { message: "Korona płomieni otacza maga." },
        },
      },
    });
    const [message] = options.computed.messages.call({
      currentUserId: 3,
      chat: { messages: [{ id: 4, body, author: { id: 3, name: "Ada" } }] },
    });

    expect(message.magicCast).toMatchObject({
      spell: "Korona ognia",
      powerDice: [9, 2],
      chaosDice: [9],
      powerTotal: 11,
      succeeded: true,
    });
    expect(message.diceRoll).toBeNull();
  });
});
