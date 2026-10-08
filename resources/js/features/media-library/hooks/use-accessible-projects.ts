import { useState, useEffect } from 'react';
import { mediaLibraryApi } from '../api/media-library.api';
import type { MediaLibraryProject } from '../types/media-library.types';

let cachedProjects: MediaLibraryProject[] | null = null;

export function useAccessibleProjects(initialProjects?: MediaLibraryProject[]) {
    const [projects, setProjects] = useState<MediaLibraryProject[]>(() => {
        if (initialProjects && initialProjects.length > 0) {
            cachedProjects = initialProjects;
            return initialProjects;
        }
        return cachedProjects ?? [];
    });
    const [isLoading, setIsLoading] = useState<boolean>(
        (!initialProjects || initialProjects.length === 0) && cachedProjects === null
    );

    useEffect(() => {
        if (initialProjects && initialProjects.length > 0) {
            setProjects(initialProjects);
            cachedProjects = initialProjects;
            setIsLoading(false);
            return;
        }

        if (cachedProjects !== null) {
            setProjects(cachedProjects);
            setIsLoading(false);
            return;
        }

        let isMounted = true;
        setIsLoading(true);

        mediaLibraryApi
            .getProjects()
            .then((list) => {
                if (isMounted) {
                    cachedProjects = list;
                    setProjects(list);
                }
            })
            .catch(() => {
                if (isMounted) {
                    setProjects([]);
                }
            })
            .finally(() => {
                if (isMounted) {
                    setIsLoading(false);
                }
            });

        return () => {
            isMounted = false;
        };
    }, [initialProjects]);

    return { projects, isLoading };
}
