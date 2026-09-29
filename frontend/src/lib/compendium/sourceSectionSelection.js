export const selectSourceSections = (html, sectionKeys) => {
  if (!html || !sectionKeys?.length || typeof document === "undefined") {
    return "";
  }
  const selected = new Set(sectionKeys.map(String));
  const source = document.createElement("div");
  const output = document.createElement("div");
  source.innerHTML = html;
  let active = selected.has("lead");
  Array.from(source.childNodes).forEach((node) => {
    if (node.nodeType === 1 && /^H[2-6]$/u.test(node.tagName)) {
      active = selected.has(node.id);
    }
    if (active) output.append(node.cloneNode(true));
  });
  return output.innerHTML;
};
