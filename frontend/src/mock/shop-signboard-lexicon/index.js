import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";

const runtime = {};
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));

export const shopSignboardLexicon = runtime.shopSignboardLexicon;
export default runtime.shopSignboardLexicon;
