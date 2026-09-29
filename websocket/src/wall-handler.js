import { createSceneElementHandler } from "./scene-element-handler.js";

const publicWall = (wall) => {
  const secret = wall.type === "secret";
  return {
    ...wall,
    name: secret ? `Wall ${wall.id}` : wall.name,
    type: secret ? "wall" : wall.type,
    doorState: secret ? null : wall.doorState,
    color: null,
    hidden: false,
    capabilities: { canManage: false },
  };
};

export const createWallHandler = (options) =>
  createSceneElementHandler({
    ...options,
    resource: "wall",
    publicUpdates: true,
    publicItem: publicWall,
  });
