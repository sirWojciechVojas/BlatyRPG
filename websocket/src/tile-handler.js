import { createSceneElementHandler } from "./scene-element-handler.js";

export const createTileHandler = (options) =>
  createSceneElementHandler({
    ...options,
    resource: "tile",
    publicUpdates: true,
    hideHidden: true,
  });
