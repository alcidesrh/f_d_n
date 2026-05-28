import { EventBus } from "quasar";

export const bus = new EventBus();

export const merror = (msg) => bus.emit("error", msg);

export const msuccess = (msg = "") =>
  bus.emit("positive", msg || getAlertText());

export const minfo = (msg) => bus.emit("info", msg);
