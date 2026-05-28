import { defineStore } from "pinia";
import { SubmissionError } from "src/utils/error";
import api from "src/services/api";
import type { Role } from "src/types/role";
import type { SubmissionErrors } from "src/types/error";

interface State {
  created?: Role;
  isLoading: boolean;
  error?: string;
  violations?: SubmissionErrors;
}

export const useRoleCreateStore = defineStore("roleCreate", {
  state: (): State => ({
    created: undefined,
    isLoading: false,
    error: undefined,
    violations: undefined,
  }),

  actions: {
    async create(payload: Role) {
      this.setError(undefined);
      this.setViolations(undefined);
      this.toggleLoading();

      try {
        const response = await api("roles", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        const data: Role = await response.json();

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

    setCreated(created: Role) {
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
