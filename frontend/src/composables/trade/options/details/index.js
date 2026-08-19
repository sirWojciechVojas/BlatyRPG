import runtime from "./runtime";
import { createDetailsMethodsPart1 } from "./methodsPart1";
import { createDetailsMethodsPart2 } from "./methodsPart2";

export const detailsOptions = {
  methods: Object.assign(
    {},
    createDetailsMethodsPart1(runtime),
    createDetailsMethodsPart2(runtime),
  ),
};
