import { createCoreMethodsPart1Segment1 } from "./methodsPart1.segment1";
import { createCoreMethodsPart1Segment2 } from "./methodsPart1.segment2";

export const createCoreMethodsPart1 = (runtime) => {
  return Object.assign(
    {},
    createCoreMethodsPart1Segment1(runtime),
    createCoreMethodsPart1Segment2(runtime),
  );
};
