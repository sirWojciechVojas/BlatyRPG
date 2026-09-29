import { describe, expect, it, vi } from "vitest";
import { createCharacterDevelopmentApiClient } from "@/lib/character/characterDevelopmentApiClient";

describe("characterDevelopmentApiClient", () => {
  it("loads and normalizes a selected system dictionary", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [
        {
          id: "7",
          category: "umiejetnosc",
          name: "  Wiedza (Prawo) ",
          description: "Test",
          metadata: { cecha: "Int" },
        },
      ],
    });

    await expect(
      createCharacterDevelopmentApiClient({ request }).definitions(
        2,
        "umiejetnosc",
      ),
    ).resolves.toEqual([
      {
        id: 7,
        category: "umiejetnosc",
        name: "Wiedza (Prawo)",
        description: "Test",
        metadata: { cecha: "Int" },
      },
    ]);
    expect(request).toHaveBeenCalledWith(
      "/systems/2/data?category=umiejetnosc",
      { signal: undefined },
    );
  });
});
