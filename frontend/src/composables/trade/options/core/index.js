import runtime from "./runtime";
import { createCoreRootPart1 } from "./rootPart1";
import { createCoreComputedPart1 } from "./computedPart1";
import { createCoreComputedPart2 } from "./computedPart2";
import { createCoreComputedPart3 } from "./computedPart3";
import { createCoreComputedPart4 } from "./computedPart4";
import { createCoreComputedPart5 } from "./computedPart5";
import { createCoreComputedPart6 } from "./computedPart6";
import { createCoreComputedPart7 } from "./computedPart7";
import { createCoreWatchPart1 } from "./watchPart1";
import { createCoreMethodsPart1 } from "./methodsPart1";
import { createCoreMethodsPart2 } from "./methodsPart2";
import { createCoreMethodsPart3 } from "./methodsPart3";
import { createCoreMethodsPart4 } from "./methodsPart4";
import { createCoreMethodsPart5 } from "./methodsPart5";
import { createCoreMethodsPart6 } from "./methodsPart6";
import { createCoreMethodsPart7 } from "./methodsPart7";

export const coreOptions = {
  ...createCoreRootPart1(runtime),
  computed: Object.assign(
    {},
    createCoreComputedPart1(runtime),
    createCoreComputedPart2(runtime),
    createCoreComputedPart3(runtime),
    createCoreComputedPart4(runtime),
    createCoreComputedPart5(runtime),
    createCoreComputedPart6(runtime),
    createCoreComputedPart7(runtime),
  ),
  watch: Object.assign({}, createCoreWatchPart1(runtime)),
  methods: Object.assign(
    {},
    createCoreMethodsPart1(runtime),
    createCoreMethodsPart2(runtime),
    createCoreMethodsPart3(runtime),
    createCoreMethodsPart4(runtime),
    createCoreMethodsPart5(runtime),
    createCoreMethodsPart6(runtime),
    createCoreMethodsPart7(runtime),
  ),
};
