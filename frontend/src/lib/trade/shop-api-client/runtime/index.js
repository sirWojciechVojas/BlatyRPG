import { createApiRuntimePart1 } from "./part1";

const runtime = {};
Object.assign(runtime, createApiRuntimePart1(runtime));

export default runtime;
