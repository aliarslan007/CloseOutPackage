import type { MediaAssetUploader } from '../types/media-library.types';

export function formatRoleLabel(role?: string | null): string {
    if (!role) return 'User';
    return role
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function getUploaderRoleName(uploader?: MediaAssetUploader | null): string {
    if (!uploader) return 'User';
    if (uploader.role_name) {
        return formatRoleLabel(uploader.role_name);
    }
    if (uploader.roles && uploader.roles.length > 0) {
        return formatRoleLabel(uploader.roles[0].name);
    }
    return 'User';
}

export function getUploaderInitials(name?: string | null): string {
    if (!name || name.trim().length === 0) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
