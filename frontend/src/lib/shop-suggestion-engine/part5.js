import { createRuntimePart5Segment1 } from "./part5.segment1";
import { createRuntimePart5Segment2 } from "./part5.segment2";
import { createRuntimePart5Segment3 } from "./part5.segment3";
export const createRuntimePart5 = (runtime) => {
  const result = {};
  Object.assign(runtime, {
    result,
  });
  Object.assign(result, createRuntimePart5Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart5Segment2(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart5Segment3(runtime));
  Object.assign(runtime, result);
  return result;
};
