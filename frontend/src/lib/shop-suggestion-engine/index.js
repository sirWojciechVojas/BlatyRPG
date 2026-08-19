import { worldProfiles } from "@/mock/worldProfiles";
import { shopSuggestionSeedLibrary } from "@/mock/shopSuggestionSeedLibrary";
import { resolveItemIconClass } from "@/lib/trade/shopItemIconResolver";
import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";
import { createRuntimePart3 } from "./part3";
import { createRuntimePart4 } from "./part4";
import { createRuntimePart5 } from "./part5";
import { createRuntimePart6 } from "./part6";
import { createRuntimePart7 } from "./part7";
import { createRuntimePart8 } from "./part8";
import { createRuntimePart9 } from "./part9";
import { createRuntimePart10 } from "./part10";
import { createRuntimePart11 } from "./part11";

const runtime = {
  worldProfiles,
  shopSuggestionSeedLibrary,
  resolveItemIconClass,
};
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));
Object.assign(runtime, createRuntimePart3(runtime));
Object.assign(runtime, createRuntimePart4(runtime));
Object.assign(runtime, createRuntimePart5(runtime));
Object.assign(runtime, createRuntimePart6(runtime));
Object.assign(runtime, createRuntimePart7(runtime));
Object.assign(runtime, createRuntimePart8(runtime));
Object.assign(runtime, createRuntimePart9(runtime));
Object.assign(runtime, createRuntimePart10(runtime));
Object.assign(runtime, createRuntimePart11(runtime));

export const generateShopSuggestionBundle =
  runtime.generateShopSuggestionBundle;
export const generateShopSuggestions = runtime.generateShopSuggestions;
export default runtime.generateShopSuggestions;
