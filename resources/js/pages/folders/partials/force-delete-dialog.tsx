import { AlertError } from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import folders from '@/routes/folders';
import { type Folder } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ForceDeleteDialogProps {
    folder: Folder | null;
    onOpenChange: (open: boolean) => void;
}

export function ForceDeleteDialog({ folder, onOpenChange }: ForceDeleteDialogProps) {
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











