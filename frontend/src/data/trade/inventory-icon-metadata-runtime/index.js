import iconSourceNames from "../inventory-icon-source-names";
import primaryRulesPart1 from "../inventory-icon-rules/part1";
import primaryRulesPart2 from "../inventory-icon-rules/part2";
import primaryRulesPart3 from "../inventory-icon-rules/part3";
import { createRuntimePart1 } from "./part1";
import { createRuntimePart2 } from "./part2";
import { createRuntimePart3 } from "./part3";

const runtime = {
  iconSourceNames,
  primaryRulesPart1,
  primaryRulesPart2,
  primaryRulesPart3,
};
Object.assign(runtime, createRuntimePart1(runtime));
Object.assign(runtime, createRuntimePart2(runtime));
Object.assign(runtime, createRuntimePart3(runtime));

export const inventoryIconMetadataMap = runtime.inventoryIconMetadataMap;
export const inventoryIconClasses = runtime.inventoryIconClasses;
export default runtime.inventoryIconMetadataMap;
