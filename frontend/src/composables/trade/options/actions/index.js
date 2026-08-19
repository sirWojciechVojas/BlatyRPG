import runtime from "./runtime";
import { createActionsMethodsPart1 } from "./methodsPart1";
import { createActionsMethodsPart2 } from "./methodsPart2";
import { createActionsMethodsPart3 } from "./methodsPart3";
import { createActionsMethodsPart4 } from "./methodsPart4";

export const actionsOptions = {
  methods: Object.assign(
    {},
    createActionsMethodsPart1(runtime),
    createActionsMethodsPart2(runtime),
    createActionsMethodsPart3(runtime),
    createActionsMethodsPart4(runtime),
  ),
};
