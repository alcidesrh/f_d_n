import type { SubmissionErrors } from '~/types/error';
// import { UserItemData } from '~/types/api';
// import type { Config } from './Qu                                                ickTypes';
import type { User } from '~/types/user';
import { defineStore } from 'pinia';

// export interface setUserSession {
//     username?: string;
//     apiTokens?: string;
//     uri?: string;
// }
interface State {
	user?: Ref<User>;
	isLoading: boolean;
	redirectTo: Ref<string>;
	error?: string;
	violations?: SubmissionErrors;
	// config?: Config;
	authErrorAttempts: number;
}

export const useUserSessionStore = defineStore('userSession', {
	persist: true,
	state: (): State => {
		return {
			user: ref({}),
			isLoading: false,
			error: undefined,
			violations: undefined,
			authErrorAttempts: 0,
			redirectTo: ref('/'),
		};
	},

	actions: {
		clear() {
			this.$reset();
			user.value = null;
		},
		setData({ user, isLoading, error, violations }: UserItemData<User>) {
			this.setUser(user.value);
			this.setLoading(isLoading.value);
			this.setViolations(violations.value);

			if (error.value instanceof Error) {
				this.setError(error.value?.message);
			}
		},

		setUserSession(user?: User) {
			this.user = user;
			this.isLogin = true;
		},

		setLoading(isLoading: boolean) {
			this.isLoading = isLoading;
		},

		setError(error: string | undefined) {
			this.error = error;
		},

		setViolations(violations: SubmissionErrors | undefined) {
			this.violations = violations;
		},
	},
});
