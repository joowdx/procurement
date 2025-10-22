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
import folders from '@/routes/folders';
import { type Folder } from '@/types';
import { useForm, router } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface CreateFolderDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    parentFolder?: Folder | null;
}

interface CreateFolderFormData {
    name: string;
    description: string;
    parent_id: string | null;
}

export function CreateFolderDialog({
    open,
    onOpenChange,
    parentFolder,
}: CreateFolderDialogProps) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<CreateFolderFormData>({
            name: '',
            description: '',
            parent_id: null,
        });

    // Update parent_id when parentFolder changes
    useEffect(() => {
        if (open) {
            setData('parent_id', parentFolder?.id || null);
        }
    }, [open, parentFolder]);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        post(folders.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
                // Backend handles redirect with fresh data
            },
        });
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            reset();
            clearErrors();
        }
        onOpenChange(open);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Folder</DialogTitle>
                    <DialogDescription>
                        {parentFolder
                            ? `Create a new subfolder inside "${parentFolder.name}"`
                            : 'Create a new root folder'}
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
                                placeholder="e.g., Invitation to Bid"
                                required
                                autoFocus
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Optional description"
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
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <Spinner className="mr-2 h-4 w-4" />
                            )}
                            Create Folder
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

