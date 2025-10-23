import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { LoadMoreTrigger } from '@/hooks/use-infinite-scroll';
import AppLayout from '@/layouts/app-layout';
import folders from '@/routes/folders';
import { type BreadcrumbItem, type Folder } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FolderPlus, Search } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import { CreateFolderDialog } from './partials/create-folder-dialog';
import { EditFolderDialog } from './partials/edit-folder-dialog';
import { FolderDetailsModal } from './partials/folder-details-modal';
import { FolderTable } from './partials/folder-table';

interface PaginatedFolders {
    data: Folder[];
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

interface FoldersIndexProps {
    folders: PaginatedFolders;
    filter?: string;
    search?: string;
    max_depth?: number;
    max_depth_available: number;
    counts: {
        all: number;
        empty: number;
        deleted: number;
    };
}


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Folders',
        href: folders.index().url,
    },
];

export default function FoldersIndex({ folders: initialFolders, filter, search, max_depth, max_depth_available, counts }: FoldersIndexProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState<boolean>(false);
    const [selectedParent, setSelectedParent] = useState<Folder | null>(null);
    const [editFolder, setEditFolder] = useState<Folder | null>(null);
    const [deleteFolder, setDeleteFolder] = useState<Folder | null>(null);
    const [detailsFolder, setDetailsFolder] = useState<Folder | null>(null);
    const [searchQuery, setSearchQuery] = useState<string>(search || '');
    const [maxDepth, setMaxDepth] = useState<number | undefined>(max_depth);
    const [allFolders, setAllFolders] = useState<Folder[]>(initialFolders.data);
    
    // Generate depth options from 0 to max_depth_available
    const depthOptions = Array.from({ length: max_depth_available + 1 }, (_, i) => i);

    useEffect(() => {
        setAllFolders(initialFolders.data);
    }, [initialFolders.data]);

    const handleCreateSubfolder = (folder: Folder) => {
        setSelectedParent(folder);
        setCreateDialogOpen(true);
    };

    const handleSearchSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(folders.index.url(), { 
            filter,
            search: searchQuery,
            max_depth: maxDepth,
        }, {
            preserveState: true,
        });
    };

    const handleFilterChange = (newFilter?: string) => {
        router.get(folders.index.url(), { 
            filter: newFilter,
            search: searchQuery,
            max_depth: maxDepth,
        }, {
            preserveState: true,
        });
    };

    const handleDepthChange = (value: string) => {
        const newDepth = value === 'all' ? null : parseInt(value);
        setMaxDepth(newDepth as number);
        router.get(folders.index.url(), { 
            filter,
            search: searchQuery,
            max_depth: newDepth,
        }, {
            preserveState: true,
        });
    };

    const loadMore = () => {
        if (!initialFolders.links.next) return;

        router.get(
            initialFolders.links.next,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['folders'],
                onSuccess: (page) => {
                    const newFolders = (page.props.folders as PaginatedFolders).data;
                    setAllFolders((prev) => [...prev, ...newFolders]);
                },
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Folders" />
            
            <div className="flex h-full flex-1 flex-col gap-3 rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">Folders</h1>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            Organize your procurement documents
                        </p>
                    </div>
                    <Button onClick={() => setCreateDialogOpen(true)} size="sm">
                        <FolderPlus className="mr-2 h-4 w-4" />
                        New Folder
                    </Button>
                </div>

                {/* Search and Filters */}
                <div className="flex items-center gap-3 flex-wrap">
                    <form onSubmit={handleSearchSubmit} className="flex-1 max-w-sm min-w-[200px]">
                        <div className="relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder="Search folders..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                    </form>

                    <Select
                        value={maxDepth?.toString() || 'all'}
                        onValueChange={handleDepthChange}
                    >
                        <SelectTrigger className="h-9 w-auto px-3">
                            <span>Depth: {maxDepth?.toString() || 'All'}</span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            {depthOptions.map((depth) => (
                                <SelectItem key={depth} value={depth.toString()}>
                                    {depth}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="flex items-center gap-2">
                        <Button
                            variant={!filter ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleFilterChange(undefined)}
                        >
                            Folders
                            <Badge variant={!filter ? 'secondary' : 'outline'} className="ml-2 h-5 px-1.5">
                                {counts.all}
                            </Badge>
                        </Button>
                        <Button
                            variant={filter === 'empty' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleFilterChange('empty')}
                        >
                            Empty
                            <Badge variant={filter === 'empty' ? 'secondary' : 'outline'} className="ml-2 h-5 px-1.5">
                                {counts.empty}
                            </Badge>
                        </Button>
                    <Button
                        variant={filter === 'deleted' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleFilterChange('deleted')}
                    >
                        Deleted
                        <Badge variant={filter === 'deleted' ? 'secondary' : 'outline'} className="ml-2 h-5 px-1.5">
                            {counts.deleted}
                        </Badge>
                    </Button>
                    </div>
                </div>

                {/* Folder Listing */}
                {allFolders.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <FolderPlus className="h-12 w-12 text-muted-foreground mb-3" />
                        <h3 className="text-base font-semibold mb-1">
                            {filter === 'deleted' ? 'No deleted folders' : filter === 'empty' ? 'No empty folders' : searchQuery ? 'No folders found' : 'No folders yet'}
                        </h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            {searchQuery ? 'Try adjusting your search' : 'Create your first folder to start organizing documents'}
                        </p>
                        {!searchQuery && !filter && (
                            <Button onClick={() => setCreateDialogOpen(true)} size="sm">
                                <FolderPlus className="mr-2 h-4 w-4" />
                                Create Folder
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <FolderTable 
                            folders={allFolders}
                            onCreate={handleCreateSubfolder}
                            onEdit={setEditFolder}
                            onDelete={setDeleteFolder}
                            onDetails={setDetailsFolder}
                        />
                        <LoadMoreTrigger
                            nextPageUrl={initialFolders.links.next}
                            onLoadMore={loadMore}
                        />
                    </>
                )}

                <CreateFolderDialog
                    open={createDialogOpen}
                    onOpenChange={(open) => {
                        setCreateDialogOpen(open);
                        if (!open) setSelectedParent(null);
                    }}
                    parentFolder={selectedParent}
                />

                <EditFolderDialog
                    open={!!editFolder}
                    onOpenChange={(open) => !open && setEditFolder(null)}
                    folder={editFolder}
                />

                <FolderDetailsModal
                    open={!!detailsFolder}
                    onOpenChange={(open) => !open && setDetailsFolder(null)}
                    folder={detailsFolder}
                />

                <ConfirmDialog
                    open={!!deleteFolder}
                    onOpenChange={(open) => !open && setDeleteFolder(null)}
                    onConfirm={() => {
                        if (deleteFolder) {
                            router.delete(folders.destroy.url(deleteFolder.id), {
                                data: {
                                    filter,
                                    search: searchQuery,
                                    max_depth: maxDepth,
                                },
                            });
                            setDeleteFolder(null);
                        }
                    }}
                    title="Delete Folder"
                    description={`Are you sure you want to delete "${deleteFolder?.name}"? This will also delete all subfolders.`}
                    confirmText="Delete"
                    cancelText="Cancel"
                    variant="destructive"
                />
            </div>
        </AppLayout>
    );
}
