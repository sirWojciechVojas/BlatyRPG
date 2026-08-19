import runtime from "./runtime";
import { createContentRootPart1 } from "./rootPart1";
import { createContentPropsPart1 } from "./propsPart1";
import { createContentPropsPart2 } from "./propsPart2";
import { createContentPropsPart3 } from "./propsPart3";
import { createContentPropsPart4 } from "./propsPart4";
import { createContentPropsPart5 } from "./propsPart5";
import { createContentWatchPart1 } from "./watchPart1";
import { createContentComputedPart1 } from "./computedPart1";
import { createContentComputedPart2 } from "./computedPart2";
import { createContentComputedPart3 } from "./computedPart3";
import { createContentMethodsPart1 } from "./methodsPart1";
import { createContentMethodsPart2 } from "./methodsPart2";
import { createContentMethodsPart3 } from "./methodsPart3";
import { createContentMethodsPart4 } from "./methodsPart4";
import { createContentMethodsPart5 } from "./methodsPart5";
import { createContentMethodsPart6 } from "./methodsPart6";

const contentOptions = {
  ...createContentRootPart1(runtime),
  props: Object.assign(
    {},
    createContentPropsPart1(runtime),
    createContentPropsPart2(runtime),
    createContentPropsPart3(runtime),
    createContentPropsPart4(runtime),
    createContentPropsPart5(runtime),
  ),
  watch: Object.assign({}, createContentWatchPart1(runtime)),
  computed: Object.assign(
    {},
    createContentComputedPart1(runtime),
    createContentComputedPart2(runtime),
    createContentComputedPart3(runtime),
  ),
  methods: Object.assign(
    {},
    createContentMethodsPart1(runtime),
    createContentMethodsPart2(runtime),
    createContentMethodsPart3(runtime),
    createContentMethodsPart4(runtime),
    createContentMethodsPart5(runtime),
    createContentMethodsPart6(runtime),
  ),
};

export default contentOptions;
