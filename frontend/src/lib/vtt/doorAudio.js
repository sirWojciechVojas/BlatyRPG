import { wallAudioRuntime } from "@/services/wallAudioRuntime";

export const playDoorSound = (wall, cue) => {
  return wallAudioRuntime.playCue(wall, cue);
};
