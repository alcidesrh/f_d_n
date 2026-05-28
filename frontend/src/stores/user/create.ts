import { defineStore } from "pinia";
import { SubmissionError } from "src/utils/error";
import api from "src/services/api";
import type { User } from "src/types/user";
import type { SubmissionErrors } from "src/types/error";

interface State {
  created?: User;
  isLoading: boolean;
  error?: string;
  violations?: SubmissionErrors;
}

export const useUserCreateStore = defineStore("userCreate", {
  state: (): State => ({
    created: undefined,
    isLoading: false,
    error: undefined,
    violations: undefined,
  }),

  actions: {
    async create(payload: User) {
      this.setError(undefined);
      this.setViolations(undefined);
      this.toggleLoading();

      try {
        const response = await api("users", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        const data: User = await response.json();

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

    setCreated(created: User) {
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
