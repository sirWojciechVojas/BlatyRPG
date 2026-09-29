import { beforeEach, describe, expect, it, vi } from "vitest";
import { VoiceService } from "../voiceService";

const mixer = () => ({
  categoryVolumes: { voice: 0.8 },
  setVoiceActive: vi.fn(),
  setCategoryVolume: vi.fn(),
  setOutputDevice: vi.fn(() => Promise.resolve(false)),
});

describe("VoiceService participant presentation state", () => {
  beforeEach(() => localStorage.clear());

  it("exposes real microphone and LiveKit connection quality state", () => {
    const publication = {
      trackName: "voice:microphone",
      isMuted: false,
    };
    const remote = {
      identity: "user_2",
      name: "Remote",
      metadata: '{"nickname":"Player","role":"player"}',
      connectionQuality: "good",
      audioTrackPublications: new Map([["track-2", publication]]),
    };
    const noMicrophone = {
      identity: "user_3",
      name: "Observer",
      metadata: '{"nickname":"Observer","role":"observer"}',
      connectionQuality: "lost",
      audioTrackPublications: new Map(),
    };
    const service = new VoiceService({
      mixer: mixer(),
      livekit: {
        room: { localParticipant: { identity: "user_1" } },
        participants: () => [remote, noMicrophone],
      },
      AudioContext: null,
    });

    service.refreshParticipants();

    expect(service.participants[0]).toMatchObject({
      hasMicrophone: true,
      muted: false,
      connectionQuality: "good",
    });
    expect(service.participants[1]).toMatchObject({
      hasMicrophone: false,
      muted: true,
      connectionQuality: "lost",
    });
  });

  it("mutes an existing remote graph locally without creating another graph", () => {
    const gain = { gain: { setTargetAtTime: vi.fn() } };
    const panner = { pan: { setTargetAtTime: vi.fn() } };
    const service = new VoiceService({ mixer: mixer(), AudioContext: null });
    service.context = { currentTime: 2 };
    service.remoteGraphs.set("user_2:track-2", {
      identity: "user_2",
      gain,
      panner,
    });
    service.livekit = { participants: () => [], room: null };

    service.setParticipantVolume("user_2", 0.5);
    service.setParticipantMuted("user_2", true);
    service.setDeafened(true);

    expect(service.remoteGraphs.size).toBe(1);
    expect(gain.gain.setTargetAtTime).toHaveBeenLastCalledWith(0, 2, 0.015);
    expect(service.participantPreferences.user_2).toMatchObject({
      volume: 0.5,
      muted: true,
    });
    expect(service.snapshot().deafened).toBe(true);
  });
});
