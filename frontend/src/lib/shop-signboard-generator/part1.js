import { createRuntimePart1Segment1 } from "./part1.segment1";
import { createRuntimePart1Segment2 } from "./part1.segment2";

export const createRuntimePart1 = (runtime) => {
  const result = {};
  Object.assign(result, createRuntimePart1Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment2(runtime));
  Object.assign(runtime, result);
  return result;
};
