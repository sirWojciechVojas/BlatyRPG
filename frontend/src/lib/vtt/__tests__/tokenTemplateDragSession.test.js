import { afterEach, describe, expect, it, vi } from "vitest";
import {
  beginTokenTemplateDrag,
  currentTokenTemplateDrag,
  endTokenTemplateDrag,
  readDroppedTokenTemplate,
  TOKEN_TEMPLATE_MIME,
} from "../tokenTemplateDragSession";

describe("token template drag session", () => {
  afterEach(() => endTokenTemplateDrag());

  it("uses a dedicated MIME payload with grid dimensions", () => {
    const values = new Map();
    const transfer = {
      setData: vi.fn((type, value) => values.set(type, value)),
      getData: vi.fn((type) => values.get(type) || ""),
      setDragImage: vi.fn(),
      effectAllowed: "",
    };

    beginTokenTemplateDrag(transfer, {
      id: 8,
      name: "Ogre",
      imageUrl: "/private.png",
      widthCells: 2,
      heightCells: 3,
    });

    expect(transfer.setData).toHaveBeenCalledWith(
      TOKEN_TEMPLATE_MIME,
      expect.any(String),
    );
    expect(readDroppedTokenTemplate(transfer)).toMatchObject({
      id: 8,
      widthCells: 2,
      heightCells: 3,
    });
    expect(currentTokenTemplateDrag()?.id).toBe(8);
  });
});
