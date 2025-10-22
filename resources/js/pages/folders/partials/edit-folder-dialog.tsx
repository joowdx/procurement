import { AlertError } from '@/components/alert-error';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import folders from '@/routes/folders';
import { type Folder } from '@/types';
import { useForm, router } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';
import { ReorderableFolderList } from './reorderable-folder-list';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FolderDetailsModal } from './folder-details-modal';

interface EditFolderDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    folder: Folder | null;
}

interface EditFolderFormData {
    name: string;
    description: string;
}

export function EditFolderDialog({
    open,
    onOpenChange,
    folder,
}: EditFolderDialogProps) {
    const { data, setData, put, processing, errors, reset, clearErrors } =
        useForm<EditFolderFormData>({
            name: '',
            description: '',
        });

    const [children, setChildren] = useState<Folder[]>([]);
    const [loadingChildren, setLoadingChildren] = useState(false);
    const [deleteFolder, setDeleteFolder] = useState<Folder | null>(null);
    const [detailsFolder, setDetailsFolder] = useState<Folder | null>(null);

    // Update form data and load children when folder changes
    useEffect(() => {
        if (open && folder) {
            setData({
                name: folder.name,
                description: folder.description || '',
            });

            // Load children if folder has any
            if (folder.children_count && folder.children_count > 0) {
                setLoadingChildren(true);
                fetch(folders.show.url(folder.id) + '?children=true')
                    .then((res) => res.json())
                    .then((data) => {
                        setChildren(data);
                        setLoadingChildren(false);
                    })
                    .catch(() => {
                        setLoadingChildren(false);
                    });
            } else {
                setChildren([]);
            }
        }
    }, [open, folder]);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        if (!folder) return;

        put(folders.update.url(folder.id), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            reset();
            clearErrors();
            setChildren([]);
        }
        onOpenChange(open);
    };

    const handleCreateSubfolder = (parentFolder: Folder) => {
        // Navigate to that folder to create subfolder
        router.visit(folders.show.url(parentFolder.id));
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
                onSuccess: () => {
                    setDeleteFolder(null);
                    // Remove from local state
                    setChildren((prev) => prev.filter((f) => f.id !== deleteFolder.id));
                },
            });
        }
    };

    if (!folder) return null;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Folder</DialogTitle>
                    <DialogDescription>
                        Update folder information
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit}>
                    <div className="space-y-4 py-4">
                        <AlertError errors={errors} />

                        <div className="space-y-2">
                            <Label htmlFor="name">
                                Name <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Folder name"
                                required
                                autoFocus
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Optional description"
                                rows={3}
                            />
                        </div>

                        {/* Children reordering section */}
                        {folder.children_count && folder.children_count > 0 && (
                            <div className="space-y-2">
                                <Label>Subfolders</Label>
                                {loadingChildren ? (
                                    <div className="flex items-center justify-center py-4">
                                        <Spinner className="h-6 w-6" />
                                    </div>
                                ) : (
                                    <div className="max-h-60 overflow-y-auto rounded-md border p-2">
                                        <ReorderableFolderList
                                            initialFolders={children}
                                            onEdit={() => {}}
                                            onDelete={handleDeleteFolder}
                                            onDetails={handleDetailsFolder}
                                            onCreateSubfolder={handleCreateSubfolder}
                                        />
                                    </div>
                                )}
                            </div>
                        )}
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
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <Spinner className="mr-2 h-4 w-4" />
                            )}
                            Update Folder
                        </Button>
                    </DialogFooter>
                </form>

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
            </DialogContent>
        </Dialog>
    );
}

