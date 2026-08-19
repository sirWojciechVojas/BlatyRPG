import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";
import { createRuntimePart3 } from "./part3";

const runtime = {};
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));
Object.assign(runtime, createRuntimePart3(runtime));

export const mockHeroes = runtime.mockHeroes;
export const mockTemplates = runtime.mockTemplates;
export const mockShops = runtime.mockShops;
export const mockInventoryItems = runtime.mockInventoryItems;
export const mockTrashItems = runtime.mockTrashItems;
