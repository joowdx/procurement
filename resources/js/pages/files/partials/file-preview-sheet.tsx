import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import fileRoutes from '@/routes/files';
import { type File } from '@/types';
import { Download } from 'lucide-react';

interface FilePreviewSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    file: File | null;
}

export function FilePreviewSheet({
    open,
    onOpenChange,
    file,
}: FilePreviewSheetProps) {
    if (!file) return null;

    const isPdf = file.type === 'application/pdf' || file.extension === 'pdf';
    const isImage = file.type?.startsWith('image/');
    const isText = file.type?.startsWith('text/');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="w-full sm:max-w-2xl p-0 flex flex-col">
                <SheetHeader className="px-6 py-4 border-b">
                    <SheetTitle className="truncate">{file.name}</SheetTitle>
                    <SheetDescription className="truncate">
                        {file.description || 'File preview'}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-auto p-6">
                    {!file.url ? (
                        <div className="flex items-center justify-center h-full text-muted-foreground">
                            <p>File URL not available</p>
                        </div>
                    ) : isPdf ? (
                        <iframe
                            src={file.url}
                            className="w-full h-full min-h-[600px] border rounded"
                            title={file.name}
                        />
                    ) : isImage ? (
                        <div className="flex items-center justify-center">
                            <img
                                src={file.url}
                                alt={file.name}
                                className="max-w-full h-auto rounded"
                            />
                        </div>
                    ) : isText ? (
                        <iframe
                            src={file.url}
                            className="w-full h-full min-h-[600px] border rounded bg-white dark:bg-gray-900"
                            title={file.name}
                        />
                    ) : (
                        <div className="flex flex-col items-center justify-center h-full text-muted-foreground space-y-2">
                            <p>Preview not available for this file type</p>
                            <p className="text-sm">Type: {file.type}</p>
                        </div>
                    )}
                </div>

                <div className="px-6 py-4 border-t flex items-center justify-end gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <a href={fileRoutes.download.url(file.id)}>
                            <Download className="mr-2 h-4 w-4" />
                            Download
                        </a>
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

