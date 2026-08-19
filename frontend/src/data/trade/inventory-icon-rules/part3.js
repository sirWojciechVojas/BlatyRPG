import { rule } from "./rule";
export default [
  rule(
    ["bread", "loaf", "pie", "biscuit", "cake"],
    "wypiek",
    ["FOOD"],
    ["BREAD_BAKERY"],
    ["jedzenie"],
  ),
  rule(
    ["fruit", "vegetable", "nuts"],
    "owoce lub warzywa",
    ["FOOD"],
    ["FRUIT_VEGETABLES"],
    ["jedzenie"],
  ),
  rule(
    ["salt", "sugar", "spice"],
    "przyprawa",
    ["FOOD"],
    ["SPICES_HERBS"],
    ["przyprawa"],
  ),
  rule(
    ["egg", "stew", "dish", "cheese", "porridge", "cream"],
    "jedzenie",
    ["FOOD"],
    ["PREPARED_MEALS"],
    ["jedzenie"],
  ),
  rule(
    ["quest", "seal", "artifact", "relic", "piece"],
    "przedmiot fabularny",
    ["MISC"],
    ["QUEST"],
    ["fabularne"],
  ),
];
