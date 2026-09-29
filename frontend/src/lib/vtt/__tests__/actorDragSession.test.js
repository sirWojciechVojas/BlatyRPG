import { afterEach, describe, expect, it, vi } from "vitest";
import {
  beginActorDrag,
  currentActorDrag,
  endActorDrag,
  normalizeDraggedActor,
} from "../actorDragSession";
import { TOKEN_ACTOR_MIME } from "../tokenDrop";

afterEach(() => {
  endActorDrag();
  vi.restoreAllMocks();
});

describe("actor drag session", () => {
  it("normalizes an actor and installs a named token drag image", () => {
    vi.spyOn(window, "requestAnimationFrame").mockImplementation(() => 1);
    const transfer = { setData: vi.fn(), setDragImage: vi.fn() };

    expect(
      beginActorDrag(transfer, {
        id: "7",
        name: "  Alda Storm  ",
        imageUrl: "/alda.webp",
      }),
    ).toEqual({ id: 7, name: "Alda Storm", imageUrl: "/alda.webp" });
    expect(currentActorDrag()).toMatchObject({ id: 7, name: "Alda Storm" });
    expect(transfer.effectAllowed).toBe("copy");
    expect(transfer.setData).toHaveBeenCalledWith(
      TOKEN_ACTOR_MIME,
      JSON.stringify({ id: 7, name: "Alda Storm", imageUrl: "/alda.webp" }),
    );
    expect(transfer.setDragImage).toHaveBeenCalledWith(
      expect.objectContaining({ className: "actor-drag-image" }),
      38,
      38,
    );
  });

  it("rejects non-integer identifiers before a drag can reach the API", () => {
    expect(normalizeDraggedActor({ id: "7.5", name: "Broken" })).toBeNull();
    expect(beginActorDrag({}, { id: null })).toBeNull();
    expect(currentActorDrag()).toBeNull();
  });
});
