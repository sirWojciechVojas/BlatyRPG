import { createRuntimePart3Segment1 } from "./part3.segment1";
import { createRuntimePart3Segment2 } from "./part3.segment2";
import { createRuntimePart3Segment3 } from "./part3.segment3";

export const createRuntimePart3 = (runtime) => {
  const result = {};
  Object.assign(result, createRuntimePart3Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart3Segment2(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart3Segment3(runtime));
  Object.assign(runtime, result);
  return result;
};
