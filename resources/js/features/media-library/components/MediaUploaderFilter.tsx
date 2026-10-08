import { cn } from '@/lib/utils';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Check, ChevronDown, Images, User as UserIcon } from 'lucide-react';
import type { TeamSupervisor } from '../types/media-library.types';
import { getUploaderInitials } from '../utils/format-uploader';

export interface MediaUploaderFilterProps {
    readonly currentUserId?: number | null;
    readonly loggedInUserId?: number;
    readonly technicians: TeamSupervisor[];
    readonly sectionLabel?: string;
    readonly onChange: (userId?: number) => void;
}

/** Section heading for the uploader list: who this role can filter by. */
export function uploaderSectionLabel(isFieldSupervisor: boolean, isAdmin: boolean): string {
    if (isFieldSupervisor) {
        return 'Managers & Admins';
    }
    if (isAdmin) {
        return 'Technicians & Managers';
    }
    return 'Field Technicians';
}

const ITEM_BASE_CLASS =
    'flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 text-xs transition-colors text-left';
const ITEM_SELECTED_CLASS = 'font-semibold text-foreground bg-muted/50';

function getItemClass(focus: boolean, isSelected: boolean): string {
    return cn(
        ITEM_BASE_CLASS,
        focus ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground',
        isSelected && ITEM_SELECTED_CLASS
    );
}

function UploaderAvatar({ name, photoUrl }: { name: string; photoUrl?: string | null }) {
    return photoUrl ? (
        <img
            src={photoUrl}
            alt={name}
            width={24}
            height={24}
            className="h-6 w-6 aspect-square rounded-full object-cover shrink-0 border border-border"
        />
    ) : (
        <span className="flex h-6 w-6 aspect-square shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
            {getUploaderInitials(name)}
        </span>
    );
}

export function MediaUploaderFilter({
    currentUserId,
    loggedInUserId,
    technicians,
    sectionLabel = 'Field Technicians',
    onChange,
}: MediaUploaderFilterProps) {
    const isAllSelected = !currentUserId;
    const isMineSelected = Boolean(
        currentUserId && loggedInUserId && currentUserId === loggedInUserId
    );
    const selectedTech =
        !isAllSelected && !isMineSelected ? technicians.find((t) => t.id === currentUserId) : null;

    return (
        <Menu as="div" className="relative inline-block text-left">
            <MenuButton
                className="flex h-9 min-w-[170px] max-w-[240px] items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 text-xs text-foreground shadow-2xs hover:bg-muted/40 focus:outline-none focus:ring-1 focus:ring-primary cursor-pointer select-none"
                aria-label="Filter photos by uploader"
            >
                <div className="flex items-center gap-2 truncate">
                    {isAllSelected && (
                        <>
                            <Images className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span className="truncate">Everyone</span>
                        </>
                    )}
                    {isMineSelected && (
                        <>
                            <UserIcon className="h-3.5 w-3.5 text-primary shrink-0" />
                            <span className="truncate font-medium">My Photos</span>
                        </>
                    )}
                    {selectedTech && (
                        <>
                            {selectedTech.profile_photo_url ? (
                                <img
                                    src={selectedTech.profile_photo_url}
                                    alt={selectedTech.name}
                                    width={16}
                                    height={16}
                                    className="h-4 w-4 aspect-square rounded-full object-cover shrink-0 border border-border"
                                />
                            ) : (
                                <span className="flex h-4 w-4 aspect-square shrink-0 items-center justify-center rounded-full bg-primary/10 text-[8px] font-bold text-primary">
                                    {getUploaderInitials(selectedTech.name)}
                                </span>
                            )}
                            <span className="truncate font-medium" title={selectedTech.name}>
                                {selectedTech.name}
                            </span>
                        </>
                    )}
                    {!isAllSelected && !isMineSelected && !selectedTech && (
                        <>
                            <UserIcon className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                            <span className="truncate">Uploader #{currentUserId}</span>
                        </>
                    )}
                </div>
                <ChevronDown className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
            </MenuButton>

            <MenuItems
                transition
                anchor={{ to: 'bottom start', gap: 6 }}
                className="z-50 min-w-[14rem] max-w-[18rem] overflow-hidden rounded-xl border border-border/80 bg-card p-1 shadow-xl ring-1 ring-black/5 focus:outline-none transition duration-100 ease-out data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                {/* 1. Everyone — no uploader narrow-down */}
                <MenuItem>
                    {({ focus }) => (
                        <button
                            type="button"
                            onClick={() => onChange(undefined)}
                            className={getItemClass(focus, isAllSelected)}
                        >
                            <div className="flex items-center gap-2.5 truncate">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted/80 text-muted-foreground">
                                    <Images className="h-3.5 w-3.5" />
                                </div>
                                <span className="truncate">Everyone</span>
                            </div>
                            {isAllSelected && (
                                <Check className="h-3.5 w-3.5 text-primary shrink-0" />
                            )}
                        </button>
                    )}
                </MenuItem>

                {/* 2. My Photos */}
                {loggedInUserId && (
                    <MenuItem>
                        {({ focus }) => (
                            <button
                                type="button"
                                onClick={() => onChange(loggedInUserId)}
                                className={getItemClass(focus, isMineSelected)}
                            >
                                <div className="flex items-center gap-2.5 truncate">
                                    <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <UserIcon className="h-3.5 w-3.5" />
                                    </div>
                                    <span className="truncate">My Photos</span>
                                </div>
                                {isMineSelected && (
                                    <Check className="h-3.5 w-3.5 text-primary shrink-0" />
                                )}
                            </button>
                        )}
                    </MenuItem>
                )}

                {/* Horizontal Divider */}
                <div className="my-1 border-t border-border" />

                {/* Section label */}
                <div className="px-2.5 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    {sectionLabel}
                </div>

                {/* 3. Uploaders List */}
                <div className="max-h-56 overflow-y-auto space-y-0.5 pr-0.5">
                    {technicians.length === 0 ? (
                        <div className="px-2.5 py-2 text-center text-[11px] text-muted-foreground">
                            No uploaders to show
                        </div>
                    ) : (
                        technicians.map((tech) => {
                            const isSelected = currentUserId === tech.id;
                            return (
                                <MenuItem key={tech.id}>
                                    {({ focus }) => (
                                        <button
                                            type="button"
                                            onClick={() => onChange(tech.id)}
                                            className={getItemClass(focus, isSelected)}
                                            title={tech.name}
                                        >
                                            <div className="flex items-center gap-2.5 min-w-0 truncate">
                                                <UploaderAvatar
                                                    name={tech.name}
                                                    photoUrl={tech.profile_photo_url}
                                                />
                                                <span className="truncate">{tech.name}</span>
                                            </div>
                                            {isSelected && (
                                                <Check className="h-3.5 w-3.5 text-primary shrink-0 ml-1.5" />
                                            )}
                                        </button>
                                    )}
                                </MenuItem>
                            );
                        })
                    )}
                </div>
            </MenuItems>
        </Menu>
    );
}
