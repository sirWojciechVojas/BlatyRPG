import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";
import { createRuntimePart3 } from "./part3";

const runtime = {};
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));
Object.assign(runtime, createRuntimePart3(runtime));

export const createContainerState = runtime.createContainerState;
export const moveInstance = runtime.moveInstance;
export const assignTemplateToShop = runtime.assignTemplateToShop;
export const buyFromShop = runtime.buyFromShop;
export const trashItem = runtime.trashItem;
export const restoreFromTrash = runtime.restoreFromTrash;
export const moveTemplateStack = runtime.moveTemplateStack;
export const getSystemContainerId = runtime.getSystemContainerId;
