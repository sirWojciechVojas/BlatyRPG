import shopSignboardLexicon from "../../mock/shopSignboardLexicon";
import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";
import { createRuntimePart3 } from "./part3";
import { createRuntimePart4 } from "./part4";

const runtime = { shopSignboardLexicon };
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));
Object.assign(runtime, createRuntimePart3(runtime));
Object.assign(runtime, createRuntimePart4(runtime));

export const generateShopSignboard = runtime.generateShopSignboard;
export const __resetShopSignboardGeneratorState =
  runtime.__resetShopSignboardGeneratorState;
export default runtime.generateShopSignboard;
