export const seed = (
  namePl,
  descriptionPl,
  itemClass,
  itemGenre,
  priceTier = "mid",
  segment = "products",
  tags = [],
) => ({
  namePl,
  descriptionPl,
  itemClass,
  itemGenre,
  priceTier,
  segment,
  tags,
});
