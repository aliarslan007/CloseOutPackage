export type DriftConflictType = 'bucket_decreased';

export interface DriftConflict {
    readonly type: DriftConflictType;
    readonly stageId: number;
    readonly stageName: string;
    readonly categoryId: number;
    readonly categoryName: string;
    readonly newValue: number;
    readonly currentPhotoCount: number;
}

export interface DriftReport {
    readonly hasUnsafeDrift: boolean;
    readonly conflicts: readonly DriftConflict[];
}
