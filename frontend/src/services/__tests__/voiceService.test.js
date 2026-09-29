import { describe, expect, it, vi } from "vitest";
import { normalizeVoiceError, VoiceService } from "../voiceService";

const mixer = () => ({
  categoryVolumes: { voice: 1 },
  setVoiceActive: vi.fn(),
  setCategoryVolume: vi.fn(),
  setOutputDevice: vi.fn(() => Promise.resolve(false)),
});

describe("VoiceService microphone permission", () => {
  it("normalizes browser permission failures to a stable UI error", () => {
    const error = Object.assign(new Error("Permission denied"), {
      name: "NotAllowedError",
    });

    expect(normalizeVoiceError(error)).toBe("microphone_permission_denied");
  });

  it("recovers an already subscribed remote track when its mixer graph is missing", async () => {
    const track = { mediaStreamTrack: { readyState: "live" } };
    const publication = { trackSid: "track-9", track };
    const participant = {
      identity: "user_9",
      audioTrackPublications: new Map([[publication.trackSid, publication]]),
    };
    const service = new VoiceService({
      livekit: {
        room: {
          remoteParticipants: new Map([[participant.identity, participant]]),
        },
      },
      mixer: mixer(),
      AudioContext: null,
    });
    service.attachRemote = vi.fn(() => Promise.resolve());

    await service.ensureRemoteAudio();

    expect(service.attachRemote).toHaveBeenCalledWith(
      track,
      publication,
      participant,
    );
  });

  it("attaches remote voice through the LiveKit Web Audio pipeline", async () => {
    const audioNode = () => ({ connect: vi.fn(), disconnect: vi.fn() });
    const analyser = {
      ...audioNode(),
      fftSize: 512,
      getFloatTimeDomainData: vi.fn((samples) => samples.fill(0)),
    };
    const gain = {
      ...audioNode(),
      gain: { setTargetAtTime: vi.fn() },
    };
    const panner = {
      ...audioNode(),
      pan: { setTargetAtTime: vi.fn() },
    };
    const context = {
      state: "running",
      currentTime: 1,
      createAnalyser: () => analyser,
      createGain: () => gain,
      createStereoPanner: () => panner,
      createMediaStreamSource: vi.fn(),
    };
    const element = document.createElement("audio");
    const track = {
      mediaStreamTrack: { readyState: "live" },
      setAudioContext: vi.fn(),
      setWebAudioPlugins: vi.fn(),
      attach: vi.fn(() => element),
      detach: vi.fn(),
    };
    const publication = {
      trackSid: "track-4",
      trackName: "voice:microphone",
      track,
      isMuted: false,
    };
    const participant = {
      identity: "user_4",
      metadata: '{"nickname":"Remote"}',
      audioTrackPublications: new Map([[publication.trackSid, publication]]),
    };
    const service = new VoiceService({
      livekit: {
        room: {
          localParticipant: { identity: "user_1" },
          remoteParticipants: new Map([[participant.identity, participant]]),
        },
        participants: () => [participant],
      },
      mixer: mixer(),
      AudioContext: vi.fn(),
    });
    service.context = context;

    await service.attachRemote(track, publication, participant);

    expect(track.setAudioContext).toHaveBeenCalledWith(context);
    expect(track.setWebAudioPlugins).toHaveBeenCalledWith([
      analyser,
      gain,
      panner,
    ]);
    expect(track.attach).toHaveBeenCalledOnce();
    expect(context.createMediaStreamSource).not.toHaveBeenCalled();
    expect(element.parentElement).toBe(document.body);

    service.detachRemote(participant.identity, publication.trackSid);
    expect(track.detach).toHaveBeenCalledWith(element);
  });

  it("does not leave a participant connected when microphone access is denied", async () => {
    const permissionError = Object.assign(new Error("Permission denied"), {
      name: "NotAllowedError",
    });
    const devices = {
      subscribe: vi.fn((listener) => {
        listener({ inputs: [], outputs: [] });
        return vi.fn();
      }),
      captureMicrophone: vi.fn(() => Promise.reject(permissionError)),
      emitDevices: vi.fn(() => Promise.resolve()),
    };
    const livekit = {
      connect: vi.fn(),
      disconnect: vi.fn(() => Promise.resolve()),
      unpublish: vi.fn(() => Promise.resolve()),
      participants: vi.fn(() => []),
    };
    const service = new VoiceService({
      api: {
        token: vi.fn(() =>
          Promise.resolve({ serverUrl: "wss://voice.test", token: "token" }),
        ),
      },
      devices,
      livekit,
      mixer: mixer(),
      AudioContext: null,
    });

    await expect(service.join(5)).rejects.toBe(permissionError);

    expect(livekit.connect).not.toHaveBeenCalled();
    expect(livekit.disconnect).toHaveBeenCalledTimes(2);
    expect(service.snapshot()).toMatchObject({
      campaignId: null,
      status: "error",
      joining: false,
      participants: [],
      error: "microphone_permission_denied",
    });
  });
});

describe("VoiceService account device settings", () => {
  const availableDevices = {
    inputs: [
      { deviceId: "mic-admin" },
      { deviceId: "mic-player" },
      { deviceId: "cable-admin" },
    ],
    outputs: [{ deviceId: "output-admin" }, { deviceId: "output-player" }],
  };

  it("replaces every selection when the authenticated account changes", async () => {
    const api = {
      deviceSettings: vi
        .fn()
        .mockResolvedValueOnce({
          microphoneDeviceId: "mic-admin",
          outputDeviceId: "output-admin",
          externalInputs: {
            "external-1": "cable-admin",
            "external-2": "",
          },
        })
        .mockResolvedValueOnce({
          microphoneDeviceId: "mic-player",
          outputDeviceId: "output-player",
          externalInputs: { "external-1": "", "external-2": "" },
        }),
    };
    const service = new VoiceService({
      api,
      mixer: mixer(),
      AudioContext: null,
    });
    service.deviceList = availableDevices;

    await service.switchAccount({ user: { id: 1 } });
    expect(service.snapshot()).toMatchObject({
      microphoneDeviceId: "mic-admin",
      outputDeviceId: "output-admin",
      externalDeviceIds: { "external-1": "cable-admin" },
    });

    await service.switchAccount({ user: { id: 2 } });
    expect(service.snapshot()).toMatchObject({
      microphoneDeviceId: "mic-player",
      outputDeviceId: "output-player",
      externalDeviceIds: { "external-1": "", "external-2": "" },
    });
    expect(api.deviceSettings).toHaveBeenCalledTimes(2);
  });

  it("falls back locally without erasing an unavailable account preference", async () => {
    const api = {
      deviceSettings: vi.fn().mockResolvedValue({
        microphoneDeviceId: "microphone-on-another-computer",
        outputDeviceId: "output-on-another-computer",
        externalInputs: {
          "external-1": "cable-on-another-computer",
          "external-2": "",
        },
      }),
    };
    const service = new VoiceService({
      api,
      mixer: mixer(),
      AudioContext: null,
    });
    service.deviceList = availableDevices;

    await service.switchAccount({ user: { id: 7 } });

    expect(service.snapshot()).toMatchObject({
      microphoneDeviceId: "",
      outputDeviceId: "",
      externalDeviceIds: {
        "external-1": "cable-on-another-computer",
        "external-2": "",
      },
    });
    expect(service.preferredDeviceSettings).toMatchObject({
      microphoneDeviceId: "microphone-on-another-computer",
      outputDeviceId: "output-on-another-computer",
      externalInputs: { "external-1": "cable-on-another-computer" },
    });
  });

  it("debounces and saves microphone, output and external inputs together", async () => {
    vi.useFakeTimers();
    const api = {
      deviceSettings: vi.fn().mockResolvedValue({}),
      saveDeviceSettings: vi.fn().mockResolvedValue({}),
    };
    const service = new VoiceService({
      api,
      mixer: mixer(),
      AudioContext: null,
    });
    service.deviceList = availableDevices;
    await service.switchAccount({ user: { id: 9 } });

    await service.changeMicrophone("mic-admin");
    service.setExternalInputDevice("external-1", "cable-admin");
    await vi.advanceTimersByTimeAsync(250);

    expect(api.saveDeviceSettings).toHaveBeenCalledTimes(1);
    expect(api.saveDeviceSettings).toHaveBeenCalledWith({
      microphoneDeviceId: "mic-admin",
      outputDeviceId: "",
      externalInputs: {
        "external-1": "cable-admin",
        "external-2": "",
      },
    });
    expect(service.snapshot().deviceSettingsStatus).toBe("saved");
    vi.useRealTimers();
  });
});
