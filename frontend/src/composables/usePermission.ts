import { useUserSessionStore } from '@/stores/autoimport/session'

export function usePermission() {
	const session = useUserSessionStore()

	function can(code: string): boolean {
		return session.can(code)
	}

	function canAny(codes: string[]): boolean {
		return session.canAny(codes)
	}

	function canAll(codes: string[]): boolean {
		return session.canAll(codes)
	}

	return { can, canAny, canAll }
}
