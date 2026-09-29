import { YouTubeAudioProvider } from "./youtubeProvider";

const factories = new Map([
  ["youtube", (track, options) => new YouTubeAudioProvider(track, options)],
]);

export const registerAudioProvider = (name, factory) => {
  if (typeof factory !== "function")
    throw new TypeError("audio_provider_factory_required");
  factories.set(String(name).toLowerCase(), factory);
};

export const createAudioProvider = (track, options = {}) => {
  const factory = factories.get(String(track?.provider || "").toLowerCase());
  return factory ? factory(track, options) : null;
};
