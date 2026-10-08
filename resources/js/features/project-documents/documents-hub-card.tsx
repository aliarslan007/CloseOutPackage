import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { FolderOpen, Upload } from 'lucide-react';
import * as React from 'react';
import { DOCUMENT_CATEGORIES } from './constants';
import { DocumentSection } from './document-section';
import type { ProjectDocumentsMap } from './types';

export interface DocumentsHubCardProps {
    /** Project route key (hash_id || id). */
    projectKey: number | string;
    documents: ProjectDocumentsMap;
    /** Admin or the assigned manager — controls upload/remove. Viewing is page-level. */
    canManage: boolean;
}

export function DocumentsHubCard({
    projectKey,
    documents,
    canManage,
}: Readonly<DocumentsHubCardProps>) {
    const total = Object.values(documents).reduce((sum, list) => sum + list.length, 0);
    const [activeCollection, setActiveCollection] = React.useState(
        DOCUMENT_CATEGORIES[0]?.collection ?? ''
    );
    const uploadInputRefs = React.useRef(new Map<string, HTMLInputElement>());
    const [uploadStatuses, setUploadStatuses] = React.useState<
        Record<string, { isUploading: boolean; progress: number }>
    >({});

    const registerUploadInput = React.useCallback(
        (collection: string, input: HTMLInputElement | null) => {
            if (input) {
                uploadInputRefs.current.set(collection, input);
            } else {
                uploadInputRefs.current.delete(collection);
            }
        },
        []
    );

    const updateUploadStatus = React.useCallback(
        (collection: string, isUploading: boolean, progress: number) => {
            setUploadStatuses((current) => {
                const previous = current[collection];
                if (previous?.isUploading === isUploading && previous.progress === progress) {
                    return current;
                }

                return { ...current, [collection]: { isUploading, progress } };
            });
        },
        []
    );

    const onTabKeyDown = (event: React.KeyboardEvent<HTMLButtonElement>, currentIndex: number) => {
        let nextIndex: number | undefined;

        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % DOCUMENT_CATEGORIES.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex =
                (currentIndex - 1 + DOCUMENT_CATEGORIES.length) % DOCUMENT_CATEGORIES.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = DOCUMENT_CATEGORIES.length - 1;
        }

        if (nextIndex !== undefined) {
            event.preventDefault();
            const nextCategory = DOCUMENT_CATEGORIES[nextIndex];
            setActiveCollection(nextCategory.collection);
            const tabs =
                event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>(
                    '[role="tab"]'
                );
            tabs?.[nextIndex]?.focus();
        }
    };

    return (
        <Card>
            <CardHeader className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 p-3 pb-0">
                <CardTitle className="flex items-center gap-2 text-sm font-bold">
                    <FolderOpen className="size-4 text-primary" /> Project Documents
                    {total > 0 && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
                            {total}
                        </span>
                    )}
                </CardTitle>
                {canManage && (
                    <Button
                        type="button"
                        size="sm"
                        className="h-8 justify-self-center gap-1.5 px-3 text-xs font-semibold shadow-sm"
                        onClick={() => uploadInputRefs.current.get(activeCollection)?.click()}
                        disabled={uploadStatuses[activeCollection]?.isUploading ?? false}
                    >
                        <Upload className="size-3.5" />
                        {uploadStatuses[activeCollection]?.isUploading
                            ? `${uploadStatuses[activeCollection].progress}%`
                            : 'Upload'}
                    </Button>
                )}
                <span aria-hidden="true" />
            </CardHeader>

            <CardContent className="space-y-3 p-3">
                <div
                    role="tablist"
                    aria-label="Project document categories"
                    className="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1"
                >
                    {DOCUMENT_CATEGORIES.map((category, index) => {
                        const isActive = activeCollection === category.collection;
                        const tabId = `project-document-${category.collection}-tab`;

                        return (
                            <button
                                key={category.collection}
                                id={tabId}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                aria-controls={`project-document-${category.collection}-panel`}
                                tabIndex={isActive ? 0 : -1}
                                onClick={() => setActiveCollection(category.collection)}
                                onKeyDown={(event) => onTabKeyDown(event, index)}
                                className={`flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 py-1.5 text-xs font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                    isActive
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {category.label}
                                <span
                                    className={`rounded-full px-1.5 py-0.5 text-[10px] leading-none ${
                                        isActive
                                            ? 'bg-primary-foreground/20 text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {documents[category.collection]?.length ?? 0}
                                </span>
                            </button>
                        );
                    })}
                </div>

                {DOCUMENT_CATEGORIES.map((category) => {
                    const isActive = activeCollection === category.collection;

                    return (
                        <div
                            key={category.collection}
                            id={`project-document-${category.collection}-panel`}
                            role="tabpanel"
                            aria-labelledby={`project-document-${category.collection}-tab`}
                            tabIndex={0}
                            hidden={!isActive}
                        >
                            <DocumentSection
                                projectKey={projectKey}
                                category={category}
                                documents={documents[category.collection] ?? []}
                                canManage={canManage}
                                registerUploadInput={registerUploadInput}
                                onUploadStatusChange={updateUploadStatus}
                            />
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
