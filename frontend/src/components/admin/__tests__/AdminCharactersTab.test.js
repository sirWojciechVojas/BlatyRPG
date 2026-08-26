import { describe, expect, it, vi } from "vitest";
import options from "../options/AdminCharactersTab.options";

describe("AdminCharactersTab", () => {
  it("keeps multiple Table and GM assignments for one character", () => {
    const context = {
      characterCampaigns: [
        { characterId: 9, campaignId: 4 },
        { characterId: 9, campaignId: 5 },
      ],
      characterOwners: [
        { characterId: 9, campaignId: 4, userId: 2 },
        { characterId: 9, campaignId: 5, userId: 7 },
      ],
    };

    expect(options.methods.campaignsFor.call(context, 9)).toHaveLength(2);
    expect(options.methods.ownersFor.call(context, 9)).toHaveLength(2);
  });

  it("adds and removes a character from a campaign container", () => {
    const emit = vi.fn();
    const character = { id: 9, name: "Bruder Witz" };
    const context = { characterCampaigns: [], $emit: emit };

    options.methods.changeCampaign.call(context, character, 4, true);
    context.characterCampaigns = [{ characterId: 9, campaignId: 4 }];
    options.methods.changeCampaign.call(context, character, 4, false);

    expect(emit).toHaveBeenNthCalledWith(1, "campaign-change", {
      character,
      campaignId: 4,
      assigned: true,
    });
    expect(emit).toHaveBeenNthCalledWith(2, "campaign-change", {
      character,
      campaignId: 4,
      assigned: false,
    });
  });

  it("does not duplicate an existing owner assignment", () => {
    const emit = vi.fn();
    const character = { id: 9 };
    const gameMaster = { campaignId: 4, userId: 2 };
    const context = {
      characterOwners: [{ characterId: 9, campaignId: 4, userId: 2 }],
      $emit: emit,
    };

    options.methods.changeOwner.call(context, character, gameMaster, true);

    expect(emit).not.toHaveBeenCalled();
  });
});
