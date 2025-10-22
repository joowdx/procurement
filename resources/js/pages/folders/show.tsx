import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import folders from '@/routes/folders';
import { type BreadcrumbItem, type Folder } from '@/types';
import { Head } from '@inertiajs/react';
import { FolderPlus, Upload } from 'lucide-react';
import { useState } from 'react';
import { FileList } from '../files/partials/file-list';
import { UploadFileDialog } from '../files/partials/upload-file-dialog';
import { CreateFolderDialog } from './partials/create-folder-dialog';
import { EditFolderDialog } from './partials/edit-folder-dialog';
import { FolderDetailsModal } from './partials/folder-details-modal';
import { ReorderableFolderList } from './partials/reorderable-folder-list';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { router } from '@inertiajs/react';

interface FolderShowProps {
    folder: Folder;
}

export default function FolderShow({ folder }: FolderShowProps) {
    const [uploadDialogOpen, setUploadDialogOpen] = useState<boolean>(false);
    const [createDialogOpen, setCreateDialogOpen] = useState<boolean>(false);
    const [selectedParent, setSelectedParent] = useState<Folder | null>(null);
    const [editFolder, setEditFolder] = useState<Folder | null>(null);
    const [deleteFolder, setDeleteFolder] = useState<Folder | null>(null);
    const [detailsFolder, setDetailsFolder] = useState<Folder | null>(null);

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

        // Build ancestor chain
        const ancestors: Folder[] = [];
        let current = folder.parent;
        while (current) {
            ancestors.unshift(current);
            current = current.parent;
        }

        // Add all ancestors as breadcrumbs
        ancestors.forEach(ancestor => {
            crumbs.push({
                title: ancestor.name,
                href: folders.show.url(ancestor.id),
            });
        });

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
                    description={`Are you sure you want to delete "${deleteFolder?.name}"? This action cannot be undone.`}
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

