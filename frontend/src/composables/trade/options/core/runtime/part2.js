import { createCoreRuntimePart2Segment1 } from "./part2.segment1";
import { createCoreRuntimePart2Segment2 } from "./part2.segment2";

export const createCoreRuntimePart2 = (runtime) => {
  const result = {};
  Object.assign(result, createCoreRuntimePart2Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createCoreRuntimePart2Segment2(runtime));
  Object.assign(runtime, result);
  return result;
};
