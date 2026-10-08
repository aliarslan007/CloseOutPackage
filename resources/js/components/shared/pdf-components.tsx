import * as React from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { FileText, Download, Mail } from 'lucide-react';
import { RevisionBadge } from '@/components/shared/revision-badge';

export interface PDFRevisionData {
    id: number;
    revisionNumber: string;
    generatedAt: string | null;
    generatedBy: string;
    type: 'partial' | 'final';
    downloadUrl: string;
}

export interface PDFGenerateModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    currentRevision: string;
    nextRevision: string;
    defaultEmail?: string;
    onGenerate: (type: 'partial' | 'final', recipientEmail?: string) => void;
    loading?: boolean;
}

export function PDFGenerateModal({
    open,
    onOpenChange,
    currentRevision,
    nextRevision,
    defaultEmail = '',
    onGenerate,
    loading = false,
}: PDFGenerateModalProps) {
    const [mode, setMode] = React.useState<'partial' | 'final'>('final');
    const [email, setEmail] = React.useState(defaultEmail);

    React.useEffect(() => {
        setEmail(defaultEmail);
    }, [defaultEmail]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onGenerate(mode, mode === 'final' ? email : undefined);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileText className="size-5 text-primary" />
                            Generate Package PDF
                        </DialogTitle>
                        <DialogDescription>
                            Select generation mode and review revision information.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {/* Revision Info */}
                        <div className="flex items-center justify-between p-3 rounded-lg border bg-muted/30 text-xs">
                            <div>
                                <span className="text-muted-foreground">Current Revision:</span>
                                <div className="mt-0.5"><RevisionBadge revisionNumber={currentRevision} /></div>
                            </div>
                            <div className="text-right">
                                <span className="text-muted-foreground">Will Become:</span>
                                <div className="mt-0.5"><RevisionBadge revisionNumber={nextRevision} /></div>
                            </div>
                        </div>

                        {/* Mode Selector */}
                        <div className="space-y-2">
                            <Label className="text-xs font-semibold">PDF Type</Label>
                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onClick={() => setMode('partial')}
                                    className={`p-3 rounded-lg border text-left text-xs transition-all ${
                                        mode === 'partial'
                                            ? 'border-primary bg-primary/10 ring-1 ring-primary font-semibold'
                                            : 'hover:bg-accent'
                                    }`}
                                >
                                    <div className="font-bold">Progress PDF</div>
                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                        Includes all uploaded photos (approved + pending).
                                    </p>
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setMode('final')}
                                    className={`p-3 rounded-lg border text-left text-xs transition-all ${
                                        mode === 'final'
                                            ? 'border-primary bg-primary/10 ring-1 ring-primary font-semibold'
                                            : 'hover:bg-accent'
                                    }`}
                                >
                                    <div className="font-bold">Final Approved PDF</div>
                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                        Approved photos only. Permanently logged.
                                    </p>
                                </button>
                            </div>
                        </div>

                        {/* Recipient Email for Final PDF */}
                        {mode === 'final' && (
                            <div className="space-y-1.5 pt-2">
                                <Label htmlFor="vzw-email" className="text-xs font-medium flex items-center gap-1.5">
                                    <Mail className="size-3.5" /> Verizon Contact Email
                                </Label>
                                <Input
                                    id="vzw-email"
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="verizon.engineer@verizon.com"
                                    className="h-9 text-xs"
                                    required
                                />
                            </div>
                        )}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={loading}>
                            Generate PDF
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export interface PDFRevisionTableProps {
    revisions: PDFRevisionData[];
}

export function PDFRevisionTable({ revisions }: PDFRevisionTableProps) {
    if (revisions.length === 0) {
        return (
            <div className="p-6 border border-dashed rounded-lg text-center text-xs text-muted-foreground">
                No PDF revisions generated yet.
            </div>
        );
    }

    return (
        <div className="rounded-md border border-border/80 bg-card shadow-2xs overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Revision</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Generated By</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead className="text-right">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {revisions.map((rev) => (
                        <TableRow key={rev.id}>
                            <TableCell>
                                <RevisionBadge revisionNumber={rev.revisionNumber} />
                            </TableCell>
                            <TableCell>
                                <Badge variant={rev.type === 'final' ? 'success' : 'outline'} className="capitalize">
                                    {rev.type}
                                </Badge>
                            </TableCell>
                            <TableCell className="text-xs">{rev.generatedBy}</TableCell>
                            <TableCell className="text-xs text-muted-foreground">{rev.generatedAt}</TableCell>
                            <TableCell className="text-right">
                                <a href={rev.downloadUrl} download target="_blank" rel="noreferrer">
                                    <Button size="sm" variant="ghost" className="h-7 text-xs gap-1">
                                        <Download className="size-3.5" /> Download
                                    </Button>
                                </a>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
