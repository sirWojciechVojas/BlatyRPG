import { createEditorViewModelPart1Segment1 } from "./part1.segment1";
import { createEditorViewModelPart1Segment2 } from "./part1.segment2";
import { createEditorViewModelPart1Segment3 } from "./part1.segment3";
export const createEditorViewModelPart1 = (runtime) => {
  const result = {};
  Object.assign(runtime, {
    result,
  });
  Object.assign(result, createEditorViewModelPart1Segment1(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createEditorViewModelPart1Segment2(runtime));
  Object.assign(runtime, result);
  Object.assign(result, createEditorViewModelPart1Segment3(runtime));
  Object.assign(runtime, result);
  return result;
};
