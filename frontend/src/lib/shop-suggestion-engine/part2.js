import { createRuntimePart2Segment1 } from "./part2.segment1";
import { createRuntimePart2Segment2 } from "./part2.segment2";
import { createRuntimePart2Segment3 } from "./part2.segment3";
export const createRuntimePart2 = (runtime) => {
  const result = {};
  Object.assign(runtime, {
    result,
  });
  Object.assign(result, createRuntimePart2Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart2Segment2(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart2Segment3(runtime));
  Object.assign(runtime, result);
  return result;
};
