import { describe, expect, it } from "vitest";
import {
  activeTokenUiStates,
  tokenUiClasses,
  tokenUiFlags,
} from "@/lib/vtt/tokenUiState";

describe("tokenUiState", () => {
  it("keeps simultaneous UI and domain states independent", () => {
    const flags = tokenUiFlags(
      {
        id: 7,
        locked: true,
        hidden: true,
        disposition: "secret",
        statuses: [{ code: "dead" }],
        capabilities: { canControl: true },
      },
      {
        selectedIds: [7, 8],
        hoveredId: 7,
        draggingId: 7,
        targetedIds: [7],
        activeTurnId: 7,
        disabled: true,
      },
    );

    expect(flags).toMatchObject({
      default: true,
      hover: true,
      selected: true,
      multiSelected: true,
      dragging: true,
      locked: true,
      disabled: true,
      controlled: true,
      uncontrolled: false,
      activeTurn: true,
      targeted: true,
      hidden: true,
      GMOnly: true,
      defeated: true,
      dead: true,
    });
    expect(activeTokenUiStates(flags)).toContain("multiSelected");
    expect(tokenUiClasses(flags)["token-state--gm-only"]).toBe(true);
  });

  it("marks a non-controlled combatant as waiting", () => {
    expect(
      tokenUiFlags(
        { id: 4, capabilities: { canControl: false } },
        { waitingTurnIds: [4] },
      ),
    ).toMatchObject({ uncontrolled: true, waitingTurn: true });
  });
});
