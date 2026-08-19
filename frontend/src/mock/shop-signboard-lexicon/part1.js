import { createRuntimePart1Segment1 } from "./part1.segment1";
import { createRuntimePart1Segment2 } from "./part1.segment2";
import { createRuntimePart1Segment3 } from "./part1.segment3";
import { createRuntimePart1Segment4 } from "./part1.segment4";
import { createRuntimePart1Segment5 } from "./part1.segment5";
import { createRuntimePart1Segment6 } from "./part1.segment6";
import { createRuntimePart1Segment7 } from "./part1.segment7";
import { createRuntimePart1Segment8 } from "./part1.segment8";
import { createRuntimePart1Segment9 } from "./part1.segment9";
export const createRuntimePart1 = (runtime) => {
  const result = {};
  Object.assign(runtime, {
    result,
  });
  Object.assign(result, createRuntimePart1Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment2(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment3(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment4(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment5(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment6(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment7(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment8(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createRuntimePart1Segment9(runtime));
  Object.assign(runtime, result);
  return result;
};
