import { useCallback, useEffect, useState } from 'react';
import { mediaLibraryApi } from '../api/media-library.api';
import type { TeamSupervisor } from '../types/media-library.types';

export function useTeamSupervisors(enabled = true, initialSupervisors?: TeamSupervisor[]) {
    const [supervisors, setSupervisors] = useState<TeamSupervisor[]>(() => initialSupervisors ?? []);
    const [isLoading, setIsLoading] = useState((!initialSupervisors || initialSupervisors.length === 0) && enabled);
    const [error, setError] = useState<Error | null>(null);

    const fetchTeam = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            setSupervisors(await mediaLibraryApi.getTeamSupervisors());
        } catch (err: unknown) {
            setError(err instanceof Error ? err : new Error('Failed to load team supervisors.'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (initialSupervisors && initialSupervisors.length > 0) {
            setSupervisors(initialSupervisors);
            setIsLoading(false);
            return;
        }

        if (enabled) {
            fetchTeam();
        }
    }, [enabled, fetchTeam, initialSupervisors]);

    return { supervisors, isLoading, error, refetch: fetchTeam };
}
