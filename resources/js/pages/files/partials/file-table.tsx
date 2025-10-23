import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Clock, Download, Eye, Lock, MoreVertical, RefreshCw, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { FileHistoryModal } from './file-history-modal';
import { FilePreviewSheet } from './file-preview-sheet';
import { UploadFileDialog } from './upload-file-dialog';

interface FileTableProps {
    files: File[];
    showDeleted?: boolean;
}

export function FileTable({ files: fileList, showDeleted = false }: FileTableProps) {
    const [replaceFile, setReplaceFile] = useState<any | null>(null);
    const [deleteFile, setDeleteFile] = useState<{ id: string; name: string } | null>(null);
    const [previewFile, setPreviewFile] = useState<File | null>(null);
    const [historyFile, setHistoryFile] = useState<File | null>(null);

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
        <>
            <div className="border rounded-lg overflow-hidden">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/50">
                    <tr>
                        <th className="text-left font-medium px-3 py-2">Name</th>
                        <th className="text-left font-medium px-3 py-2">Type</th>
                        <th className="text-left font-medium px-3 py-2">Size</th>
                        <th className="text-left font-medium px-3 py-2">Folders</th>
                        <th className="text-left font-medium px-3 py-2">Date</th>
                        <th className="text-right font-medium px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    {fileList.map((file) => (
                        <tr
                            key={file.id}
                            className="border-b last:border-0 hover:bg-muted/30 transition-colors"
                        >
                            <td className="px-3 py-2">
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => setHistoryFile(file)}
                                        className="font-medium hover:underline truncate max-w-xs text-left"
                                    >
                                        {file.name}
                                    </button>
                                    {file.locked && (
                                        <Lock className="h-3 w-3 text-destructive shrink-0" />
                                    )}
                                </div>
                                {file.description && (
                                    <p className="text-xs text-muted-foreground truncate max-w-xs mt-0.5">
                                        {file.description}
                                    </p>
                                )}
                            </td>
                            <td className="px-3 py-2">
                                <Badge variant="secondary" className="text-xs h-5">
                                    .{file.extension}
                                </Badge>
                            </td>
                            <td className="px-3 py-2 text-muted-foreground">
                                {formatFileSize(file.size)}
                            </td>
                            <td className="px-3 py-2">
                                {file.folders && file.folders.length > 0 ? (
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
                                ) : (
                                    <span className="text-xs text-muted-foreground">—</span>
                                )}
                            </td>
                            <td className="px-3 py-2 text-xs text-muted-foreground">
                                {formatDate(file.created_at)}
                            </td>
                            <td className="px-3 py-2">
                                <div className="flex items-center justify-end gap-1">
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
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-7 w-7 p-0"
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
                                                        <DropdownMenuItem onClick={() => setPreviewFile(file)}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            Preview
                                                        </DropdownMenuItem>
                                                    )}
                                                    <DropdownMenuItem onClick={() => setHistoryFile(file)}>
                                                        <Clock className="mr-2 h-4 w-4" />
                                                        History
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => setReplaceFile(file)}
                                                    >
                                                        <RefreshCw className="mr-2 h-4 w-4" />
                                                        Replace
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onClick={() => setDeleteFile({ id: file.id, name: file.name })}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
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

