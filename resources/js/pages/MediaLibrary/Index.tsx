import { MediaLibraryPage } from '@/features/media-library';
import type { MediaLibraryProject, TeamSupervisor } from '@/features/media-library';

export interface MediaLibraryIndexProps {
    readonly projects?: MediaLibraryProject[];
    readonly technicians?: TeamSupervisor[];
}

export function MediaLibraryIndex({ projects = [], technicians = [] }: MediaLibraryIndexProps) {
    return <MediaLibraryPage projects={projects} technicians={technicians} />;
}

export default MediaLibraryIndex;
