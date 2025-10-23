import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { LoadMoreTrigger } from '@/hooks/use-infinite-scroll';
import AppLayout from '@/layouts/app-layout';
import folders from '@/routes/folders';
import { type BreadcrumbItem, type Folder } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FolderPlus, Search } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import { CreateFolderDialog } from './partials/create-folder-dialog';
import { EditFolderDialog } from './partials/edit-folder-dialog';
import { FolderDetailsModal } from './partials/folder-details-modal';
import { FolderTable } from './partials/folder-table';
import { AlertError } from '@/components/alert-error';

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
    max_level?: number;
    max_level_available: number;
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

export default function FoldersIndex({ folders: initialFolders, filter, search, max_level, max_level_available, counts }: FoldersIndexProps) {
    const [createDialogOpen, setCreateDialogOpen] = useState<boolean>(false);
    const [selectedParent, setSelectedParent] = useState<Folder | null>(null);
    const [editFolder, setEditFolder] = useState<Folder | null>(null);
    const [deleteFolder, setDeleteFolder] = useState<Folder | null>(null);
    const [forceDeleteFolder, setForceDeleteFolder] = useState<Folder | null>(null);
    const [restoreFolder, setRestoreFolder] = useState<Folder | null>(null);
    const [detailsFolder, setDetailsFolder] = useState<Folder | null>(null);
    const [searchQuery, setSearchQuery] = useState<string>(search || '');
    const [maxLevel, setMaxLevel] = useState<number | undefined>(max_level);
    const [allFolders, setAllFolders] = useState<Folder[]>(initialFolders.data);

    // Generate level options from 0 to max_level_available
    const levelOptions = Array.from({ length: max_level_available + 1 }, (_, i) => i);

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
            max_level: maxLevel,
        }, {
            preserveState: true,
        });
    };

    const handleFilterChange = (newFilter?: string) => {
        router.get(folders.index.url(), { 
            filter: newFilter,
            search: searchQuery,
            max_level: maxLevel,
        }, {
            preserveState: true,
        });
    };

    const handleLevelChange = (value: string) => {
        const newLevel = value === 'all' ? null : parseInt(value);
        setMaxLevel(newLevel as number);
        router.get(folders.index.url(), { 
            filter,
            search: searchQuery,
            max_level: newLevel,
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
                        value={maxLevel?.toString() || 'all'}
                        onValueChange={handleLevelChange}
                    >
                        <SelectTrigger className="h-9 w-auto px-3">
                            <span>Level: {maxLevel?.toString() || 'All'}</span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            {levelOptions.map((level) => (
                                <SelectItem key={level} value={level.toString()}>
                                    {level}
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
                            isDeleted={filter === 'deleted'}
                            onCreate={filter !== 'deleted' ? handleCreateSubfolder : undefined}
                            onEdit={filter !== 'deleted' ? setEditFolder : undefined}
                            onDelete={filter !== 'deleted' ? setDeleteFolder : undefined}
                            onForceDelete={filter === 'deleted' ? setForceDeleteFolder : undefined}
                            onRestore={filter === 'deleted' ? setRestoreFolder : undefined}
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
                            router.delete(folders.destroy.url(deleteFolder.id));
                            setDeleteFolder(null);
                        }
                    }}
                    title="Delete Folder"
                    description={`Are you sure you want to delete "${deleteFolder?.name}"? This will hide all subfolders.`}
                    confirmText="Delete"
                    cancelText="Cancel"
                    variant="destructive"
                />

                <ForceDeleteDialog 
                    folder={forceDeleteFolder}
                    onOpenChange={(open) => !open && setForceDeleteFolder(null)}
                />

                <ConfirmDialog
                    open={!!restoreFolder}
                    onOpenChange={(open) => !open && setRestoreFolder(null)}
                    onConfirm={() => {
                        if (restoreFolder) {
                            router.post(`/folders/${restoreFolder.id}/restore`);
                            setRestoreFolder(null);
                        }
                    }}
                    title="Restore Folder"
                    description={`Restore "${restoreFolder?.name}"?`}
                    confirmText="Restore"
                    cancelText="Cancel"
                />
            </div>
        </AppLayout>
    );
}

interface ForceDeleteDialogProps {
    folder: Folder | null;
    onOpenChange: (open: boolean) => void;
}

function ForceDeleteDialog({ folder, onOpenChange }: ForceDeleteDialogProps) {
    const { data, setData, delete: destroy, processing, errors, reset, clearErrors } = useForm({
        current_password: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (folder) {
            destroy(folders.destroy.url(folder.id), {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    onOpenChange(false);
                },
            });
        }
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            reset();
            clearErrors();
        }
        onOpenChange(open);
    };

    return (
        <Dialog open={!!folder} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Permanently Delete Folder</DialogTitle>
                    <DialogDescription>
                        This action cannot be undone. This will permanently delete "{folder?.name}".
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-4 py-4">
                        <AlertError errors={errors} />

                        <div className="space-y-2">
                            <Label htmlFor="current_password">
                                Confirm Password <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="current_password"
                                type="password"
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                placeholder="Enter your password to confirm"
                                required
                                autoFocus
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={processing}>
                            {processing && <Spinner className="mr-2 h-4 w-4" />}
                            Delete
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
