export const TOKEN_TEMPLATE_MIME = "application/x-blatyrpg-token-template";

let activeTemplate = null;
let dragImage = null;

export const normalizeDraggedTemplate = (source = {}) => {
  const id = Number(source.id);
  if (!Number.isSafeInteger(id) || id < 1) return null;
  return {
    id,
    name: String(source.name || "").trim() || `#${id}`,
    imageUrl: String(source.imageUrl || ""),
    widthCells: Math.max(0.25, Number(source.widthCells) || 1),
    heightCells: Math.max(0.25, Number(source.heightCells) || 1),
  };
};

const removeDragImage = () => {
  dragImage?.remove();
  dragImage = null;
};

const createDragImage = (template) => {
  if (typeof document === "undefined") return null;
  const element = document.createElement("div");
  element.className = "actor-drag-image token-template-drag-image";
  const portrait = document.createElement("span");
  portrait.textContent = template.name.slice(0, 2).toLocaleUpperCase();
  const label = document.createElement("strong");
  label.textContent = template.name;
  element.append(portrait, label);
  document.body.append(element);
  return element;
};

export const beginTokenTemplateDrag = (dataTransfer, source) => {
  const template = normalizeDraggedTemplate(source);
  if (!template || !dataTransfer) return null;
  removeDragImage();
  activeTemplate = template;
  dataTransfer.effectAllowed = "copy";
  dataTransfer.setData(TOKEN_TEMPLATE_MIME, JSON.stringify(template));
  dragImage = createDragImage(template);
  if (dragImage && dataTransfer.setDragImage) {
    dataTransfer.setDragImage(dragImage, 38, 38);
  }
  window.requestAnimationFrame?.(removeDragImage);
  return template;
};

export const readDroppedTokenTemplate = (dataTransfer) => {
  try {
    return normalizeDraggedTemplate(
      JSON.parse(dataTransfer?.getData(TOKEN_TEMPLATE_MIME) || ""),
    );
  } catch (_error) {
    return null;
  }
};

export const currentTokenTemplateDrag = () => activeTemplate;

export const endTokenTemplateDrag = () => {
  activeTemplate = null;
  removeDragImage();
};
