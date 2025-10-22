import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { LoadMoreTrigger } from '@/hooks/use-infinite-scroll';
import AppLayout from '@/layouts/app-layout';
import files from '@/routes/files';
import { type BreadcrumbItem, type File } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Grid3x3, List, Upload } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FileList } from './partials/file-list';
import { FileTable } from './partials/file-table';
import { UploadFileDialog } from './partials/upload-file-dialog';

interface PaginatedFiles {
    data: File[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
}

interface FilesIndexProps {
    files: PaginatedFiles;
    filter?: string;
    counts: {
        all: number;
        unplaced: number;
        deleted: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Files',
        href: files.index().url,
    },
];

export default function FilesIndex({ files: initialFiles, filter, counts }: FilesIndexProps) {
    const [uploadDialogOpen, setUploadDialogOpen] = useState<boolean>(false);
    const [allFiles, setAllFiles] = useState<File[]>(initialFiles.data);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>(() => {
        const saved = localStorage.getItem('files_view_mode');
        return (saved === 'list' ? 'list' : 'grid') as 'grid' | 'list';
    });

    useEffect(() => {
        setAllFiles(initialFiles.data);
    }, [initialFiles.data]);

    useEffect(() => {
        localStorage.setItem('files_view_mode', viewMode);
    }, [viewMode]);

    const handleFilterChange = (newFilter: string) => {
        if (newFilter === filter) {
            router.get(files.index().url);
        } else {
            router.get(files.index().url, { filter: newFilter });
        }
    };

    const loadMore = () => {
        if (!initialFiles.links.next) return;

        router.get(
            initialFiles.links.next,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['files'],
                onSuccess: (page) => {
                    const newFiles = (page.props.files as PaginatedFiles).data;
                    setAllFiles((prev) => [...prev, ...newFiles]);
                },
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Files" />

            <div className="flex h-full flex-1 flex-col gap-3 rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Files</h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            Manage your procurement documents
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center border rounded-md">
                            <Button
                                variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('grid')}
                                className="rounded-r-none h-8"
                            >
                                <Grid3x3 className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant={viewMode === 'list' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewMode('list')}
                                className="rounded-l-none h-8"
                            >
                                <List className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                        <Button onClick={() => setUploadDialogOpen(true)} size="sm">
                            <Upload className="mr-2 h-4 w-4" />
                            Upload File
                        </Button>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        variant={!filter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilterChange('')}
                    >
                        Files
                        <Badge 
                            variant={!filter ? 'secondary' : 'outline'} 
                            className="ml-2 h-5 px-1.5"
                        >
                            {counts.all}
                        </Badge>
                    </Button>
                    <Button
                        variant={filter === 'unplaced' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilterChange('unplaced')}
                    >
                        Unplaced
                        {counts.unplaced > 0 && (
                            <Badge 
                                variant={filter === 'unplaced' ? 'secondary' : 'outline'} 
                                className="ml-2 h-5 px-1.5"
                            >
                                {counts.unplaced}
                            </Badge>
                        )}
                    </Button>
                    <Button
                        variant={filter === 'deleted' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilterChange('deleted')}
                    >
                        Deleted
                        {counts.deleted > 0 && (
                            <Badge 
                                variant={filter === 'deleted' ? 'secondary' : 'outline'} 
                                className="ml-2 h-5 px-1.5"
                            >
                                {counts.deleted}
                            </Badge>
                        )}
                    </Button>
                </div>

                {allFiles.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <Upload className="h-12 w-12 text-muted-foreground mb-3" />
                        <h3 className="text-base font-semibold mb-1">
                            {filter === 'unplaced' 
                                ? 'No unplaced files' 
                                : filter === 'deleted'
                                ? 'No deleted files'
                                : 'No files yet'
                            }
                        </h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            {filter === 'unplaced'
                                ? 'All files are organized in folders'
                                : filter === 'deleted'
                                ? 'No files have been deleted'
                                : 'Upload your first file to get started'
                            }
                        </p>
                        {!filter && (
                            <Button onClick={() => setUploadDialogOpen(true)} size="sm">
                                <Upload className="mr-2 h-4 w-4" />
                                Upload File
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        {viewMode === 'grid' ? (
                            <FileList files={allFiles} showDeleted={filter === 'deleted'} />
                        ) : (
                            <FileTable files={allFiles} showDeleted={filter === 'deleted'} />
                        )}
                        <LoadMoreTrigger
                            nextPageUrl={initialFiles.links.next}
                            onLoadMore={loadMore}
                        />
                    </>
                )}

                <UploadFileDialog
                    open={uploadDialogOpen}
                    onOpenChange={setUploadDialogOpen}
                    showFolderSelector={true}
                />
            </div>
        </AppLayout>
    );
}

