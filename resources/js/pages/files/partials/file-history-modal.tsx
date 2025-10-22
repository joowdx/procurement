import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { isPreviewable } from '@/lib/file-helpers';
import fileRoutes from '@/routes/files';
import { type File, type Version } from '@/types';
import { Download, Eye } from 'lucide-react';

interface FileHistoryModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    file: File | null;
    onPreview?: (file: File) => void;
}

export function FileHistoryModal({
    open,
    onOpenChange,
    file,
    onPreview,
}: FileHistoryModalProps) {
    if (!file) return null;

    const formatFileSize = (bytes?: number | null): string => {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl max-h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle className="truncate">{file.name}</DialogTitle>
                    <DialogDescription className="truncate">
                        Version history and details
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto">
                    {file.versions && file.versions.length > 0 ? (
                        <div className="space-y-2">
                            {file.versions.map((version: Version, index: number) => (
                                <div
                                    key={version.id}
                                    className="flex items-start gap-3 p-3 border rounded-lg hover:bg-accent/50 transition-colors"
                                >
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 mb-1">
                                            <Badge variant="secondary" className="text-xs h-5">
                                                v{version.number}
                                            </Badge>
                                            {index === 0 && (
                                                <Badge className="text-xs h-5">Current</Badge>
                                            )}
                                        </div>
                                        <div className="text-sm space-y-0.5">
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <span>{formatFileSize(version.size)}</span>
                                                <span>•</span>
                                                <span>{formatDate(version.created_at)}</span>
                                            </div>
                                            <div className="text-xs text-muted-foreground font-mono truncate">
                                                {version.hash.substring(0, 16)}...
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-1 shrink-0">
                                        {isPreviewable(file) && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 px-2 text-xs"
                                                onClick={() => {
                                                    onPreview?.(file);
                                                    onOpenChange(false);
                                                }}
                                            >
                                                <Eye className="h-3 w-3 mr-1" />
                                                Preview
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 px-2 text-xs"
                                            asChild
                                        >
                                            <a href={fileRoutes.download.url(file.id)}>
                                                <Download className="h-3 w-3 mr-1" />
                                                Download
                                            </a>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8 text-sm text-muted-foreground">
                            No version history available
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

