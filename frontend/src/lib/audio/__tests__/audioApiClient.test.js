import { describe, expect, it, vi } from "vitest";
import { createAudioApiClient } from "../audioApiClient";

describe("audioApiClient account device settings", () => {
  it("loads and normalizes the authenticated user's device settings", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        settings: {
          microphoneDeviceId: "mic-user-1",
          outputDeviceId: "headphones-user-1",
          externalInputs: { "external-1": "cable-a" },
        },
      }),
    };

    const settings = await createAudioApiClient({
      api: client,
    }).deviceSettings();

    expect(client.request).toHaveBeenCalledWith("auth/audio-device-settings");
    expect(settings).toEqual({
      microphoneDeviceId: "mic-user-1",
      outputDeviceId: "headphones-user-1",
      externalInputs: { "external-1": "cable-a", "external-2": "" },
    });
  });

  it("writes only the current account's normalized device payload", async () => {
    const client = {
      request: vi
        .fn()
        .mockImplementation((_path, options) =>
          Promise.resolve({ settings: options.body }),
        ),
    };
    const api = createAudioApiClient({ api: client });

    await api.saveDeviceSettings({
      microphoneDeviceId: "mic-user-2",
      externalInputs: { "external-2": "voicemeeter-2" },
    });

    expect(client.request).toHaveBeenCalledWith("auth/audio-device-settings", {
      method: "PUT",
      body: {
        microphoneDeviceId: "mic-user-2",
        outputDeviceId: "",
        externalInputs: {
          "external-1": "",
          "external-2": "voicemeeter-2",
        },
      },
    });
  });
});

describe("audioApiClient personal jukebox library", () => {
  it("updates the title and external URL of an owned track", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({ track: { id: 17 } }),
    };
    const api = createAudioApiClient({ api: client });

    await api.updatePersonal(5, 17, {
      title: "New title",
      url: "https://youtu.be/dQw4w9WgXcQ",
    });

    expect(client.request).toHaveBeenCalledWith(
      "campaigns/5/audio/library/tracks/17",
      {
        method: "PATCH",
        body: {
          title: "New title",
          url: "https://youtu.be/dQw4w9WgXcQ",
        },
      },
    );
  });
});
