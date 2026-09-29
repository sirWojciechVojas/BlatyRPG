import { createSceneElementHandler } from "./scene-element-handler.js";

export const createLightHandler = (options) =>
  createSceneElementHandler({ ...options, resource: "light", publicUpdates: true });
