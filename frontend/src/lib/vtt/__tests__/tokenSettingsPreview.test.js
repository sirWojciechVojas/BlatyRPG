import { describe, expect, it } from "vitest";
import {
  tokenPreviewMetrics,
  tokenSettingsPreview,
} from "@/lib/vtt/tokenSettingsPreview";

describe("tokenSettingsPreview", () => {
  it("maps unsaved appearance and movement values to a preview token", () => {
    expect(
      tokenSettingsPreview(
        {
          name: "Nowa nazwa",
          imageUrl: "token.webp",
          rotation: 45,
          facing: 90,
          movementRange: 8,
          movementSpent: 2.5,
          disposition: "hostile",
        },
        { id: 7, statuses: ["poisoned"] },
      ),
    ).toMatchObject({
      id: 7,
      name: "Nowa nazwa",
      rotation: 45,
      facing: 90,
      movementPoints: 5.5,
      disposition: "hostile",
    });
  });

  it("keeps token proportions inside the preview stage", () => {
    expect(tokenPreviewMetrics({ widthCells: 3, heightCells: 2 })).toEqual({
      cellSize: 54,
      width: 162,
      height: 108,
    });
    expect(tokenPreviewMetrics({ widthCells: 10, heightCells: 5 })).toEqual({
      cellSize: 17.2,
      width: 172,
      height: 86,
    });
  });
});
