import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export function useNavigationGuard(
    isDirty: boolean,
    message = 'You have unsaved changes. Are you sure you want to leave?'
) {
    useEffect(() => {
        if (!isDirty) return;

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };

        const removeInertiaListener = router.on('before', (event) => {
            const visit = event.detail.visit;

            // Never block non-GET methods (uploads, draft saves, stage submits)
            const method = (visit.method ?? 'get').toLowerCase();
            if (method !== 'get') return;

            // Safely resolve the target pathname — visit.url may be null/undefined in edge cases
            let targetPath = '';
            try {
                if (visit.url != null) {
                    targetPath =
                        visit.url instanceof URL
                            ? visit.url.pathname
                            : new URL(String(visit.url), window.location.href).pathname;
                }
            } catch {
                // Malformed URL — treat as same-page, never block
                return;
            }

            const currentPath = window.location.pathname;

            // Same page, partial-only reload, or state-preserving visit — never block
            if (
                targetPath === currentPath ||
                !targetPath ||
                visit.preserveState ||
                (visit.only !== undefined && visit.only.length > 0)
            ) {
                return;
            }

            if (!globalThis.confirm(message)) {
                event.preventDefault();
            }
        });

        globalThis.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            globalThis.removeEventListener('beforeunload', handleBeforeUnload);
            removeInertiaListener();
        };
    }, [isDirty, message]);
}

export default useNavigationGuard;
