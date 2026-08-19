import { createCoreRuntimePart1Segment1 } from "./part1.segment1";
import { createCoreRuntimePart1Segment2 } from "./part1.segment2";
export const createCoreRuntimePart1 = (runtime) => {
  const result = {};
  Object.assign(runtime, {
    result,
  });
  Object.assign(result, createCoreRuntimePart1Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createCoreRuntimePart1Segment2(runtime));
  Object.assign(runtime, result);
  return result;
};
