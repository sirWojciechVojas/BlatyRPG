import { TOKEN_ACTOR_MIME } from "./tokenDrop";

let activeActor = null;
let dragImage = null;

const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

export const normalizeDraggedActor = (source) => {
  const id = positiveId(source?.id);
  if (!id) return null;
  return {
    id,
    name: String(source?.name || "").trim() || `#${id}`,
    imageUrl: String(source?.imageUrl || ""),
  };
};

const removeDragImage = () => {
  dragImage?.remove();
  dragImage = null;
};

const createDragImage = (actor) => {
  if (typeof document === "undefined") return null;
  const element = document.createElement("div");
  element.className = "actor-drag-image";
  const portrait = actor.imageUrl
    ? Object.assign(document.createElement("img"), { src: actor.imageUrl })
    : Object.assign(document.createElement("span"), {
        textContent: actor.name.slice(0, 2).toLocaleUpperCase(),
      });
  const label = Object.assign(document.createElement("strong"), {
    textContent: actor.name,
  });
  element.append(portrait, label);
  document.body.append(element);
  return element;
};

export const beginActorDrag = (dataTransfer, source) => {
  const actor = normalizeDraggedActor(source);
  if (!actor || !dataTransfer) return null;
  removeDragImage();
  activeActor = actor;
  dataTransfer.effectAllowed = "copy";
  dataTransfer.setData(TOKEN_ACTOR_MIME, JSON.stringify(actor));
  dragImage = createDragImage(actor);
  if (dragImage && dataTransfer.setDragImage) {
    dataTransfer.setDragImage(dragImage, 38, 38);
  }
  window.requestAnimationFrame?.(removeDragImage);
  return actor;
};

export const currentActorDrag = () => activeActor;

export const endActorDrag = () => {
  activeActor = null;
  removeDragImage();
};
