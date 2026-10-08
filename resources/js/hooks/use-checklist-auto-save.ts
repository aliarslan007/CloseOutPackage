import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

export interface ChecklistItemData {
    id: number;
    label: string;
    has_explain_field: boolean;
    allows_na?: boolean;
}

export interface ChecklistResponseData {
    id: number;
    answer: boolean | null;
    explain: string | null;
    is_na?: boolean;
    quantity?: number | null;
    checklist_item: ChecklistItemData;
}

export interface ChecklistState {
    answer: boolean | null;
    explain: string;
    is_na: boolean;
    /** Raw input value; '' means no quantity was entered. */
    quantity: string;
}

/** Mirrors the `min:1` / `max:9999` rules on responses.*.quantity. */
export const CHECKLIST_QUANTITY_MAX = 9999;

/** Mirrors the `max:1000` rules on responses.*.explain. */
export const CHECKLIST_NOTE_MAX_LENGTH = 1_000;

/** Mirrors the `max:20000` rule on the package site notes. */
export const PACKAGE_SITE_NOTES_MAX_LENGTH = 20_000;

/** Mirrors CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT. */
export const PACKAGE_SITE_NOTES_IMAGE_LIMIT = 6;

/** Mirrors the `max:10240` (KB) rule on images.* in StorePackageNoteImageRequest. */
export const PACKAGE_SITE_NOTES_MAX_FILE_MB = 10;

export interface PackageSiteNoteImage {
    id: number;
    file_name?: string;
    url: string;
    thumb_url?: string;
}

interface ChecklistSeed {
    explain?: string | null;
    quantity?: number | string | null;
}

/**
 * Single place where the N/A rules are applied: marking an item N/A clears its
 * answer, explanation and quantity. Accepts a raw server response (numeric
 * quantity) as well as existing local state (string quantity).
 */
export function toChecklistState(
    seed: ChecklistSeed,
    answer: boolean | null,
    isNa: boolean
): ChecklistState {
    const quantity = seed.quantity;

    return {
        answer: isNa ? null : answer,
        explain: isNa ? '' : seed.explain || '',
        is_na: isNa,
        quantity: isNa || quantity == null || quantity === '' ? '' : String(quantity),
    };
}

/** '' -> no quantity. Anything else non-integer or out of range is dropped so a
 *  half-typed value can never fail the whole auto-save payload. */
function parseQuantity(raw: string | undefined): number | null {
    if (raw === undefined || raw === '') {
        return null;
    }

    const parsed = Number(raw);

    return Number.isInteger(parsed) && parsed >= 1 && parsed <= CHECKLIST_QUANTITY_MAX
        ? parsed
        : null;
}

export interface UseChecklistAutoSaveOptions {
    packageId: number | string;
    checklistResponses: ChecklistResponseData[];
    isLocked: boolean;
    debounceMs?: number;
}

function resolveItemExplain(r: ChecklistResponseData, prev?: ChecklistState, isNa?: boolean): string {
    if (isNa || !r.checklist_item?.has_explain_field) {
        return '';
    }
    return prev?.explain || r.explain || '';
}

function resolveItemQuantity(r: ChecklistResponseData, prev?: ChecklistState, isNa?: boolean): string {
    if (isNa) {
        return '';
    }
    if (prev && prev.quantity !== '') {
        return prev.quantity;
    }
    if (r.quantity != null) {
        return String(r.quantity);
    }
    return '';
}

function mergeExistingChecklistState(r: ChecklistResponseData, prev: ChecklistState): ChecklistState {
    const allowsNa = Boolean(r.checklist_item?.allows_na);
    const isNa = allowsNa && prev.is_na;
    const answer = isNa ? null : (prev.answer ?? r.answer);

    return {
        answer,
        explain: resolveItemExplain(r, prev, isNa),
        is_na: isNa,
        quantity: resolveItemQuantity(r, prev, isNa),
    };
}

export function buildChecklistState(
    responses?: ChecklistResponseData[],
    prevState?: Record<number, ChecklistState>
): Record<number, ChecklistState> {
    const state: Record<number, ChecklistState> = {};
    for (const r of responses || []) {
        const prev = prevState?.[r.checklist_item?.id];
        if (prev) {
            state[r.checklist_item.id] = mergeExistingChecklistState(r, prev);
        } else {
            const allowsNa = Boolean(r.checklist_item?.allows_na);
            state[r.checklist_item.id] = toChecklistState(r, r.answer, allowsNa && Boolean(r.is_na));
        }
    }
    return state;
}

export function useChecklistAutoSave({
    packageId,
    checklistResponses,
    isLocked,
    debounceMs = 600,
}: Readonly<UseChecklistAutoSaveOptions>) {
    const [checklistState, setChecklistState] = useState<Record<number, ChecklistState>>(() =>
        buildChecklistState(checklistResponses)
    );
    const [checklistSaving, setChecklistSaving] = useState(false);
    const [checklistSaved, setChecklistSaved] = useState(false);

    const stateRef = useRef(checklistState);
    stateRef.current = checklistState;

    const isSavingRef = useRef(false);
    const pendingStateRef = useRef<Record<number, ChecklistState> | null>(null);
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const prevPackageIdRef = useRef(packageId);

    const buildPayload = useCallback(
        (targetState: Record<number, ChecklistState>) =>
            checklistResponses.map((r) => {
                const itemState = targetState[r.checklist_item.id];
                const isNa = Boolean(itemState?.is_na);
                return {
                    checklist_item_id: r.checklist_item.id,
                    answer: isNa ? null : (itemState?.answer ?? null),
                    explain: isNa ? null : (itemState?.explain || null),
                    is_na: isNa,
                    quantity: isNa ? null : parseQuantity(itemState?.quantity),
                };
            }),
        [checklistResponses]
    );

    const executeSave = useCallback(
        (stateToPersist: Record<number, ChecklistState>) => {
            if (isLocked) {
                return;
            }

            isSavingRef.current = true;
            setChecklistSaving(true);
            setChecklistSaved(false);

            router.put(
                route('packages.checklist.update', { package: packageId }),
                { responses: buildPayload(stateToPersist) },
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['checklist_responses'],
                    onError: () => {
                        toast.error('Could not auto-save checklist. Please check your connection.');
                    },
                    onFinish: () => {
                        isSavingRef.current = false;
                        if (pendingStateRef.current) {
                            const nextState = pendingStateRef.current;
                            pendingStateRef.current = null;
                            executeSave(nextState);
                            return;
                        }
                        setChecklistSaving(false);
                        setChecklistSaved(true);
                    },
                }
            );
        },
        [packageId, isLocked, buildPayload]
    );

    const queueSave = useCallback(
        (nextState: Record<number, ChecklistState>, immediate: boolean) => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }

            if (immediate) {
                if (isSavingRef.current) {
                    pendingStateRef.current = nextState;
                    return;
                }
                executeSave(nextState);
                return;
            }

            debounceTimerRef.current = setTimeout(() => {
                debounceTimerRef.current = null;
                if (isSavingRef.current) {
                    pendingStateRef.current = stateRef.current;
                    return;
                }
                executeSave(stateRef.current);
            }, debounceMs);
        },
        [executeSave, debounceMs]
    );

    const handleChecklistAnswer = useCallback(
        (itemId: number, answer: boolean | 'na') => {
            if (isLocked) {
                return;
            }

            setChecklistState((prev) => {
                const isNa = answer === 'na';
                const next = {
                    ...prev,
                    [itemId]: toChecklistState(prev[itemId] ?? {}, isNa ? null : answer, isNa),
                };
                queueSave(next, true);
                return next;
            });
        },
        [isLocked, queueSave]
    );

    const handleChecklistExplain = useCallback(
        (itemId: number, explain: string) => {
            if (isLocked) {
                return;
            }

            setChecklistState((prev) => {
                const current = prev[itemId];
                const next = {
                    ...prev,
                    [itemId]: {
                        answer: current?.answer ?? null,
                        explain,
                        is_na: Boolean(current?.is_na),
                        quantity: current?.quantity ?? '',
                    },
                };
                queueSave(next, false);
                return next;
            });
        },
        [isLocked, queueSave]
    );

    const handleChecklistQuantity = useCallback(
        (itemId: number, quantity: string) => {
            if (isLocked) {
                return;
            }

            setChecklistState((prev) => {
                const current = prev[itemId];
                const next = {
                    ...prev,
                    [itemId]: {
                        answer: current?.answer ?? null,
                        explain: current?.explain ?? '',
                        is_na: Boolean(current?.is_na),
                        quantity,
                    },
                };
                queueSave(next, false);
                return next;
            });
        },
        [isLocked, queueSave]
    );

    const flushAutoSave = useCallback(() => {
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
            if (isSavingRef.current) {
                pendingStateRef.current = stateRef.current;
                return;
            }
            executeSave(stateRef.current);
        }
    }, [executeSave]);

    useEffect(() => {
        if (prevPackageIdRef.current !== packageId) {
            prevPackageIdRef.current = packageId;
            setChecklistState(buildChecklistState(checklistResponses));
            pendingStateRef.current = null;
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }
            return;
        }

        if (!isSavingRef.current && !pendingStateRef.current && !debounceTimerRef.current) {
            setChecklistState((prev) => buildChecklistState(checklistResponses, prev));
        }
    }, [packageId, checklistResponses]);

    useEffect(() => {
        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    return {
        checklistState,
        checklistSaving,
        checklistSaved,
        handleChecklistAnswer,
        handleChecklistExplain,
        handleChecklistQuantity,
        flushAutoSave,
        buildChecklistPayload: () => buildPayload(stateRef.current),
    };
}
