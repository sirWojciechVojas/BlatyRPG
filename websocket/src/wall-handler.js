import { createSceneElementHandler } from "./scene-element-handler.js";

export const createWallHandler = (options) =>
  createSceneElementHandler({ ...options, resource: "wall" });
