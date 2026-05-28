import { defineStore } from "pinia";
import { SubmissionError } from "src/utils/error";
import api from "src/services/api";
import type { Permiso } from "src/types/permiso";
import type { SubmissionErrors } from "src/types/error";

interface State {
  created?: Permiso;
  isLoading: boolean;
  error?: string;
  violations?: SubmissionErrors;
}

export const usePermisoCreateStore = defineStore("permisoCreate", {
  state: (): State => ({
    created: undefined,
    isLoading: false,
    error: undefined,
    violations: undefined,
  }),

  actions: {
    async create(payload: Permiso) {
      this.setError(undefined);
      this.setViolations(undefined);
      this.toggleLoading();

      try {
        const response = await api("permisos", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        const data: Permiso = await response.json();

        this.toggleLoading();
        this.setCreated(data);
      } catch (error) {
        this.toggleLoading();

        if (error instanceof SubmissionError) {
          this.setViolations(error.errors);
          this.setError(error.errors._error);
          return;
        }

        if (error instanceof Error) {
          this.setError(error.message);
        }
      }
    },

    setCreated(created: Permiso) {
      this.created = created;
    },

    toggleLoading() {
      this.isLoading = !this.isLoading;
    },

    setError(error: string | undefined) {
      this.error = error;
    },

    setViolations(violations: SubmissionErrors | undefined) {
      this.violations = violations;
    },
  },
});
