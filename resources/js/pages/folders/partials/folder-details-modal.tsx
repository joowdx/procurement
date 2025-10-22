import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { type Folder } from '@/types';
import { Calendar, FolderIcon, User } from 'lucide-react';

interface FolderDetailsModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    folder: Folder | null;
}

export function FolderDetailsModal({
    open,
    onOpenChange,
    folder,
}: FolderDetailsModalProps) {
    if (!folder) return null;

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FolderIcon className="h-5 w-5 text-blue-500 dark:text-blue-400" />
                        {folder.name}
                    </DialogTitle>
                    <DialogDescription>
                        Folder information and metadata
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {folder.description && (
                        <div>
                            <h4 className="text-sm font-medium mb-1">Description</h4>
                            <p className="text-sm text-muted-foreground">{folder.description}</p>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <h4 className="text-sm font-medium mb-1">Depth</h4>
                            <Badge variant="secondary">{folder.depth}</Badge>
                        </div>
                        <div>
                            <h4 className="text-sm font-medium mb-1">Order</h4>
                            <Badge variant="secondary">{folder.order}</Badge>
                        </div>
                    </div>

                    <div>
                        <h4 className="text-sm font-medium mb-2 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            Timeline
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Created</span>
                                <span>{formatDate(folder.created_at)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Updated</span>
                                <span>{formatDate(folder.updated_at)}</span>
                            </div>
                            {folder.deleted_at && (
                                <div className="flex justify-between text-destructive">
                                    <span>Deleted</span>
                                    <span>{formatDate(folder.deleted_at)}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div>
                        <h4 className="text-sm font-medium mb-2 flex items-center gap-2">
                            <User className="h-4 w-4" />
                            User Information
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Created by</span>
                                <span className="font-mono text-xs">{folder.created_by}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Updated by</span>
                                <span className="font-mono text-xs">{folder.updated_by}</span>
                            </div>
                            {folder.deleted_by && (
                                <div className="flex justify-between text-destructive">
                                    <span>Deleted by</span>
                                    <span className="font-mono text-xs">{folder.deleted_by}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div>
                        <h4 className="text-sm font-medium mb-1">Path</h4>
                        <code className="text-xs bg-muted dark:bg-muted/50 px-2 py-1 rounded">{folder.path}</code>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

