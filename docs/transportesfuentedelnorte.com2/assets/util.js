export const spinner = (el, link = false) => {
  const root = document.createElement("div");

  root.classList.add("flex");

  root.classList.add("justify-center");

  root.classList.add("items-center");

  root.classList.add("spinner-wrap");

  if (!link) {
    el = el.submitter;
  }
  // Submit programatico (form.requestSubmit() sin boton): Turbo entrega
  // formSubmission.submitter = undefined, y sin este guard el listener de
  // turbo:submit-start crashea con 'can't access property "offsetWidth",
  // t is undefined' (KA-09648). No-op: no hay boton donde anclar el spinner.
  if (!el) {
    return;
  }
  root.style.width = el.offsetWidth + "px";
  root.style.height = el.offsetHeight + "px";

  el.classList.add("!hidden");
  el.classList.add("spinner-temp");

  const div = document.createElement("div");

  div.classList.add("lds-spinner");

  for (let index = 0; index < 12; index++) {
    div.appendChild(document.createElement("div"));
  }

  root.appendChild(div);

  el.parentNode.insertBefore(root, el);
};
