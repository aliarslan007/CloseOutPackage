import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import type { RequestPayload } from '@inertiajs/core';
import type {
    EntityFeedbackRoundItem,
    PhotoActionOptions,
    SaveAnnotationPayload,
} from '../types/entity-feedback.types';

export function resolveNoteStoreRoute(
    token: string | null,
    round: EntityFeedbackRoundItem | null
): string {
    if (token) {
        return route('change-orders.token.feedback.notes.store', { token });
    }
    if (round) {
        return route('entity-feedback.notes.store', { round: round.id });
    }
    return route('entity-feedback.rounds.open');
}

export function buildAnnotationFormData(payload: SaveAnnotationPayload): FormData {
    const formData = new FormData();
    formData.append('entity_type', payload.entityType);
    formData.append('entity_id', String(payload.entityId));
    if (payload.originalImageUrl) {
        formData.append('original_image_url', payload.originalImageUrl);
    }
    formData.append('body', payload.body);

    if (payload.sourcePhotoId) {
        formData.append('source_photo_id', String(payload.sourcePhotoId));
    }
    if (payload.sourcePhotoType) {
        formData.append('source_photo_type', payload.sourcePhotoType);
    }
    if (payload.replyToNoteId) {
        formData.append('reply_to_note_id', String(payload.replyToNoteId));
    }
    if (payload.requestedAction) {
        formData.append('requested_action', payload.requestedAction);
    }
    if (payload.annotatedImageBlob) {
        formData.append('annotated_image', payload.annotatedImageBlob, 'annotation.jpg');
    }
    if (payload.annotationLayerJson) {
        formData.append('annotation_layer', payload.annotationLayerJson);
    }
    if (payload.replacementImageFile) {
        formData.append('replacement_image', payload.replacementImageFile);
    }
    if (payload.replaceInChangeOrder) {
        formData.append('replace_in_change_order', '1');
    }
    if (payload.addToChangeOrderPhotos) {
        formData.append('add_to_change_order_photos', '1');
    }

    return formData;
}

export function buildResolveFormData(
    responseText: string,
    replacementFile?: File | null,
    options?: PhotoActionOptions
): FormData {
    const formData = new FormData();
    formData.append('_method', 'PATCH');
    formData.append('manager_response', responseText);
    if (replacementFile) {
        formData.append('replacement_image', replacementFile);
    }
    if (options?.replaceInChangeOrder) {
        formData.append('replace_in_change_order', '1');
    }
    if (options?.addToChangeOrderPhotos) {
        formData.append('add_to_change_order_photos', '1');
    }
    return formData;
}

export function postRoundAction(
    routeName: string,
    params: Record<string, unknown> | number,
    data: RequestPayload,
    errorMessage: string,
    onFinish: () => void,
    successMessage?: string
): void {
    const routeParams = typeof params === 'number' ? { round: params } : params;
    router.post(route(routeName, routeParams), data, {
        preserveScroll: true,
        onSuccess: () => {
            if (successMessage) {
                toast.success(successMessage);
            }
        },
        onError: () => toast.error(errorMessage),
        onFinish,
    });
}
