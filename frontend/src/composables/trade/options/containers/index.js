import runtime from "./runtime";
import { createContainersComputedPart1 } from "./computedPart1";
import { createContainersWatchPart1 } from "./watchPart1";
import { createContainersMethodsPart1 } from "./methodsPart1";
import { createContainersMethodsPart2 } from "./methodsPart2";
import { createContainersMethodsPart3 } from "./methodsPart3";
import { createContainersMethodsPart4 } from "./methodsPart4";
import { createContainersMethodsPart5 } from "./methodsPart5";
import { createContainersMethodsPart6 } from "./methodsPart6";
import { createContainersMethodsPart7 } from "./methodsPart7";

export const containersOptions = {
  computed: Object.assign({}, createContainersComputedPart1(runtime)),
  watch: Object.assign({}, createContainersWatchPart1(runtime)),
  methods: Object.assign(
    {},
    createContainersMethodsPart1(runtime),
    createContainersMethodsPart2(runtime),
    createContainersMethodsPart3(runtime),
    createContainersMethodsPart4(runtime),
    createContainersMethodsPart5(runtime),
    createContainersMethodsPart6(runtime),
    createContainersMethodsPart7(runtime),
  ),
};
