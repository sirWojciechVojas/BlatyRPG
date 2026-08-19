import { createContainersMethodsPart3Segment1 } from "./methodsPart3.segment1";
import { createContainersMethodsPart3Segment2 } from "./methodsPart3.segment2";
import { createContainersMethodsPart3Segment3 } from "./methodsPart3.segment3";

export const createContainersMethodsPart3 = (runtime) => {
  return Object.assign(
    {},
    createContainersMethodsPart3Segment1(runtime),
    createContainersMethodsPart3Segment2(runtime),
    createContainersMethodsPart3Segment3(runtime),
  );
};
