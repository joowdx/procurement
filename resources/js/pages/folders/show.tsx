import { AlertError } from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import folders from '@/routes/folders';
import { type BreadcrumbItem, type Folder } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FolderPlus, RotateCcw, Trash2, Upload } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { FileList } from '../files/partials/file-list';
import { UploadFileDialog } from '../files/partials/upload-file-dialog';
import { CreateFolderDialog } from './partials/create-folder-dialog';
import { EditFolderDialog } from './partials/edit-folder-dialog';
import { FolderDetailsModal } from './partials/folder-details-modal';
import { ReorderableFolderList } from './partials/reorderable-folder-list';

interface FolderShowProps {
    folder: Folder;
}

export default function FolderShow({ folder }: FolderShowProps) {
    const [uploadDialogOpen, setUploadDialogOpen] = useState<boolean>(false);
    const [createDialogOpen, setCreateDialogOpen] = useState<boolean>(false);
    const [selectedParent, setSelectedParent] = useState<Folder | null>(null);
    const [editFolder, setEditFolder] = useState<Folder | null>(null);
    const [deleteFolder, setDeleteFolder] = useState<Folder | null>(null);
    const [forceDeleteFolder, setForceDeleteFolder] = useState<Folder | null>(null);
    const [restoreFolder, setRestoreFolder] = useState<Folder | null>(null);
    const [detailsFolder, setDetailsFolder] = useState<Folder | null>(null);
    
    const isDeleted = !!folder.deleted_at;

    const handleCreateSubfolder = (parentFolder: Folder) => {
        setSelectedParent(parentFolder);
        setCreateDialogOpen(true);
    };

    const handleEditFolder = (folderToEdit: Folder) => {
        setEditFolder(folderToEdit);
    };

    const handleDeleteFolder = (folderToDelete: Folder) => {
        setDeleteFolder(folderToDelete);
    };

    const handleDetailsFolder = (folderToView: Folder) => {
        setDetailsFolder(folderToView);
    };

    const confirmDelete = () => {
        if (deleteFolder) {
            router.delete(folders.destroy(deleteFolder.id), {
                preserveScroll: true,
                onSuccess: () => setDeleteFolder(null),
            });
        }
    };

    // Build breadcrumbs from folder hierarchy
    const buildBreadcrumbs = (): BreadcrumbItem[] => {
        const crumbs: BreadcrumbItem[] = [
            {
                title: 'Folders',
                href: folders.index().url,
            },
        ];

        // Add all ancestors as breadcrumbs (already loaded by backend)
        if (folder.ancestors && folder.ancestors.length > 0) {
            // Ancestors are ordered from root to closest parent
            folder.ancestors.forEach(ancestor => {
                crumbs.push({
                    title: ancestor.name,
                    href: folders.show.url(ancestor.id),
                });
            });
        }

        // Add current folder
        crumbs.push({
            title: folder.name,
            href: folders.show.url(folder.id),
        });

        return crumbs;
    };

    const breadcrumbs = buildBreadcrumbs();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={folder.name} />

            <div className="flex h-full flex-1 flex-col gap-3 rounded-xl p-4">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex-1 min-w-0">
                        <h1 className="text-xl font-semibold truncate">
                            {folder.name}
                        </h1>
                        {folder.description && (
                            <p className="text-sm text-muted-foreground mt-0.5 truncate">
                                {folder.description}
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {isDeleted ? (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setRestoreFolder(folder)}
                                >
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Restore
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setForceDeleteFolder(folder)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCreateDialogOpen(true)}
                                >
                                    <FolderPlus className="mr-2 h-4 w-4" />
                                    New Subfolder
                                </Button>
                                <Button size="sm" onClick={() => setUploadDialogOpen(true)}>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload File
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setDeleteFolder(folder)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <div className="space-y-4">
                    {/* Subfolders */}
                    {folder.children && folder.children.length > 0 && (
                        <div>
                            <h2 className="text-base font-semibold mb-2">
                                Subfolders
                            </h2>
                            <ReorderableFolderList
                                initialFolders={folder.children}
                                onEdit={handleEditFolder}
                                onDelete={handleDeleteFolder}
                                onDetails={handleDetailsFolder}
                                onCreateSubfolder={handleCreateSubfolder}
                            />
                        </div>
                    )}

                    {/* Files */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <h2 className="text-base font-semibold">Files</h2>
                            {folder.files && folder.files.length > 0 && (
                                <span className="text-xs text-muted-foreground">
                                    {folder.files.length}{' '}
                                    {folder.files.length === 1 ? 'file' : 'files'}
                                </span>
                            )}
                        </div>

                        {folder.files && folder.files.length > 0 ? (
                            <FileList files={folder.files} />
                        ) : (
                            <div className="flex flex-col items-center justify-center py-10 text-center border-2 border-dashed rounded-lg">
                                <Upload className="h-10 w-10 text-muted-foreground mb-3" />
                                <h3 className="text-base font-semibold mb-1">
                                    No files in this folder
                                </h3>
                                <p className="text-sm text-muted-foreground mb-4">
                                    Upload files to organize your documents
                                </p>
                                <Button onClick={() => setUploadDialogOpen(true)} size="sm">
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload File
                                </Button>
                            </div>
                        )}
                    </div>
                </div>

                <CreateFolderDialog
                    open={createDialogOpen}
                    onOpenChange={setCreateDialogOpen}
                    parentFolder={selectedParent || folder}
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
                    onConfirm={confirmDelete}
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
                            router.post(`/folders/${restoreFolder.id}/restore`, {}, {
                                preserveScroll: true,
                                onSuccess: () => setRestoreFolder(null),
                            });
                        }
                    }}
                    title="Restore Folder"
                    description={`Restore "${restoreFolder?.name}"?`}
                    confirmText="Restore"
                    cancelText="Cancel"
                />

                <UploadFileDialog
                    open={uploadDialogOpen}
                    onOpenChange={setUploadDialogOpen}
                    folderId={folder.id}
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
                    // Redirect to folders index after force delete
                    router.visit(folders.index().url);
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

