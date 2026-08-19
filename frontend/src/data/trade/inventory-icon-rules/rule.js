export function rule(
  tokens,
  name,
  typeKeys,
  subtypeKeys,
  tags = [],
  metadata = {},
) {
  return {
    tokens,
    name,
    typeKeys,
    subtypeKeys,
    tags,
    ...metadata,
  };
}
