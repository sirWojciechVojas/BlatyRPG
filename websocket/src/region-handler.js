import { createSceneElementHandler } from "./scene-element-handler.js";

export const createRegionHandler = (options) =>
  createSceneElementHandler({
    ...options,
    resource: "region",
    publicUpdates: true,
    hideHidden: true,
  });
