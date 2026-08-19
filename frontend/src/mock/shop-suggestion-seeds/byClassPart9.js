import { seed } from "./seed";
export default {
  MISC: [
    seed(
      "Skrzynka kupiecka",
      "Zamykana skrzynka na towary i dokumenty.",
      "MISC",
      "UTILITY",
      "mid",
      "products",
    ),
    seed(
      "Plomba cechowa",
      "Plomba do oznaczania legalnych dostaw.",
      "MISC",
      "UTILITY",
      "cheap",
      "products",
    ),
    seed(
      "Worek płócienny",
      "Worek do transportu drobnych towarów.",
      "MISC",
      "UTILITY",
      "cheap",
      "products",
    ),
    seed(
      "Sznur pieczętny",
      "Sznur do zamykania pakunków.",
      "MISC",
      "UTILITY",
      "cheap",
      "ingredients",
    ),
  ],
};
