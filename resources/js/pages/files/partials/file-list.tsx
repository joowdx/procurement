import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import fileRoutes from '@/routes/files';
import { type File } from '@/types';
import { isPreviewable } from '@/lib/file-helpers';
import { Link, router } from '@inertiajs/react';
import { Clock, Download, Eye, FileText, Lock, MoreVertical, RefreshCw, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FileHistoryModal } from './file-history-modal';
import { FilePreviewSheet } from './file-preview-sheet';
import { UploadFileDialog } from './upload-file-dialog';

interface FileListProps {
    files: File[];
    showDeleted?: boolean;
}

export function FileList({ files, showDeleted = false }: FileListProps) {
    const [replaceFile, setReplaceFile] = useState<{ id: string; name: string } | null>(null);
    const [deleteFile, setDeleteFile] = useState<{ id: string; name: string } | null>(null);
    const [previewFile, setPreviewFile] = useState<File | null>(null);
    const [historyFile, setHistoryFile] = useState<File | null>(null);

    return (
        <>
            <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {files.map((file) => (
                    <FileCard 
                        key={file.id} 
                        file={file} 
                        showDeleted={showDeleted}
                        onPreview={() => setPreviewFile(file)}
                        onHistory={() => setHistoryFile(file)}
                        onReplace={() => setReplaceFile({ id: file.id, name: file.name })}
                        onDelete={() => setDeleteFile({ id: file.id, name: file.name })}
                    />
                ))}
            </div>

            <FilePreviewSheet
                open={!!previewFile}
                onOpenChange={(open) => !open && setPreviewFile(null)}
                file={previewFile}
            />

            <FileHistoryModal
                open={!!historyFile}
                onOpenChange={(open) => !open && setHistoryFile(null)}
                file={historyFile}
                onPreview={(file) => setPreviewFile(file)}
            />

            <UploadFileDialog
                open={!!replaceFile}
                onOpenChange={(open) => !open && setReplaceFile(null)}
                fileToReplace={replaceFile}
            />

            <ConfirmDialog
                open={!!deleteFile}
                onOpenChange={(open) => !open && setDeleteFile(null)}
                onConfirm={() => {
                    if (deleteFile) {
                        router.delete(fileRoutes.destroy.url(deleteFile.id), {
                            preserveScroll: true,
                            onSuccess: () => setDeleteFile(null),
                        });
                    }
                }}
                title="Delete File"
                description={`Are you sure you want to delete "${deleteFile?.name}"? This action can be undone from the Deleted tab.`}
                confirmText="Delete"
                cancelText="Cancel"
                variant="destructive"
            />
        </>
    );
}

interface FileCardProps {
    file: File;
    showDeleted?: boolean;
    onPreview?: () => void;
    onHistory?: () => void;
    onReplace?: () => void;
    onDelete?: () => void;
}

function FileCard({ file, showDeleted = false, onPreview, onHistory, onReplace, onDelete }: FileCardProps) {
    const formatFileSize = (bytes?: number | null): string => {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    const formatDate = (dateString: string): string => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <Card className="hover:shadow-sm transition-shadow">
            <CardHeader className="py-3 px-3">
                <div className="flex items-start gap-2">
                    <div className="p-1 bg-blue-50 dark:bg-blue-950 rounded shrink-0">
                        <FileText className="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div className="flex-1 min-w-0">
                            <CardTitle className="text-sm truncate leading-tight">
                                <button
                                    onClick={onHistory}
                                    className="hover:underline text-left"
                                >
                                    {file.name}
                                </button>
                            </CardTitle>
                        {file.description && (
                            <CardDescription className="line-clamp-1 mt-0.5 text-xs leading-tight">
                                {file.description}
                            </CardDescription>
                        )}
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-6 w-6 p-0 shrink-0 -mt-0.5"
                            >
                                <MoreVertical className="h-3 w-3" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {showDeleted ? (
                                <DropdownMenuItem
                                    onClick={() => {
                                        router.patch(fileRoutes.update.url(file.id), {
                                            restore: true,
                                        }, {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Restore
                                </DropdownMenuItem>
                            ) : (
                                <>
                                    {isPreviewable(file) && file.url && (
                                        <DropdownMenuItem onClick={onPreview}>
                                            <Eye className="mr-2 h-4 w-4" />
                                            Preview
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem onClick={onHistory}>
                                        <Clock className="mr-2 h-4 w-4" />
                                        History
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={onReplace}>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Replace
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="text-destructive focus:text-destructive"
                                        onClick={onDelete}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardHeader>

            <CardContent className="py-2 px-3">
                <div className="space-y-2">
                    <div className="flex flex-wrap gap-1">
                        <Badge variant="secondary" className="text-xs h-5">
                            .{file.extension}
                        </Badge>
                        <Badge variant="outline" className="text-xs h-5">
                            {formatFileSize(file.size)}
                        </Badge>
                        {file.locked && (
                            <Badge variant="destructive" className="text-xs h-5">
                                <Lock className="h-2.5 w-2.5 mr-1" />
                                Locked
                            </Badge>
                        )}
                    </div>

                    {file.folders && file.folders.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {file.folders.slice(0, 2).map((folder) => (
                                <Badge
                                    key={folder.id}
                                    variant="outline"
                                    className="text-xs h-5"
                                >
                                    {folder.name}
                                </Badge>
                            ))}
                            {file.folders.length > 2 && (
                                <Badge variant="outline" className="text-xs h-5">
                                    +{file.folders.length - 2}
                                </Badge>
                            )}
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-2 pt-1.5 border-t">
                        <span className="text-xs text-muted-foreground truncate">
                            {formatDate(file.created_at)}
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-6 text-xs px-2 shrink-0"
                            asChild
                        >
                            <a href={fileRoutes.download.url(file.id)}>
                                <Download className="h-3 w-3 mr-1" />
                                Download
                            </a>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

