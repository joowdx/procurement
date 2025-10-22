import { AlertError } from '@/components/alert-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import files from '@/routes/files';
import folders from '@/routes/folders';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Check, FolderIcon, Upload, X } from 'lucide-react';
import { FormEventHandler, useEffect, useRef, useState } from 'react';

interface UploadFileDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    folderId?: string | null;
    fileToReplace?: { id: string; name: string } | null;
    showFolderSelector?: boolean;
}

interface UploadFileFormData {
    name: string;
    description: string;
    disk: 'local' | 'external';
    file: File | null;
    path: string;
    folder_id: string | null;
    folder_ids?: string[];
}

interface FolderOption {
    id: string;
    name: string;
    path: string;
}

export function UploadFileDialog({
    open,
    onOpenChange,
    folderId,
    fileToReplace,
    showFolderSelector = false,
}: UploadFileDialogProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [selectedFileName, setSelectedFileName] = useState<string>('');
    const [folderOptions, setFolderOptions] = useState<FolderOption[]>([]);
    const [selectedFolders, setSelectedFolders] = useState<FolderOption[]>([]);
    const [folderSearch, setFolderSearch] = useState('');
    const [openFolderPopover, setOpenFolderPopover] = useState(false);
    const [isDragging, setIsDragging] = useState<boolean>(false);
    const isReplaceMode = !!fileToReplace;

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<UploadFileFormData>({
            name: fileToReplace?.name || '',
            description: '',
            disk: 'local',
            file: null,
            path: '',
            folder_id: folderId || null,
            folder_ids: [],
        });

    // Fetch folders for selection with debounce
    useEffect(() => {
        if (!showFolderSelector || !open) return;

        const timer = setTimeout(() => {
            fetchFolders();
        }, 300); // 300ms debounce

        return () => clearTimeout(timer);
    }, [showFolderSelector, open, folderSearch]);

    const fetchFolders = async () => {
        try {
            const response = await axios.get(folders.index.url(), {
                params: { search: folderSearch },
                headers: { Accept: 'application/json' },
            });
            setFolderOptions(response.data);
        } catch (error) {
            console.error('Error fetching folders:', error);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            processFile(file);
        }
    };

    const processFile = (file: File) => {
        setData('file', file);
        setSelectedFileName(file.name);
        // Auto-fill name from filename if empty
        if (!data.name) {
            const nameWithoutExt = file.name.replace(/\.[^/.]+$/, '');
            setData('name', nameWithoutExt);
        }
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const file = e.dataTransfer.files?.[0];
        if (file && data.disk === 'local') {
            processFile(file);
        }
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        // Set folder_ids if folders were selected
        if (showFolderSelector && selectedFolders.length > 0) {
            setData('folder_ids', selectedFolders.map(f => f.id));
        }

        if (isReplaceMode && fileToReplace) {
            put(files.update.url(fileToReplace.id), {
                forceFormData: true,
                onSuccess: () => {
                    reset();
                    setSelectedFileName('');
                    setSelectedFolders([]);
                    onOpenChange(false);
                },
            });
        } else {
            post(files.store.url(), {
                forceFormData: true,
                onSuccess: () => {
                    reset();
                    setSelectedFileName('');
                    setSelectedFolders([]);
                    onOpenChange(false);
                },
            });
        }
    };

    const toggleFolder = (folder: FolderOption) => {
        setSelectedFolders(prev => {
            const exists = prev.find(f => f.id === folder.id);
            if (exists) {
                return prev.filter(f => f.id !== folder.id);
            }
            return [...prev, folder];
        });
    };

    const removeFolder = (folderId: string) => {
        setSelectedFolders(prev => prev.filter(f => f.id !== folderId));
    };

    const handleOpenChange = (open: boolean) => {
        if (!open) {
            reset();
            clearErrors();
            setSelectedFileName('');
            setSelectedFolders([]);
            setFolderSearch('');
            setIsDragging(false);
        }
        onOpenChange(open);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {isReplaceMode ? 'Replace File' : 'Upload File'}
                    </DialogTitle>
                    <DialogDescription>
                        {isReplaceMode
                            ? `Upload a new version to replace "${fileToReplace?.name}"`
                            : 'Upload a new procurement document'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="overflow-hidden">
                    <div className="space-y-4 py-4 overflow-hidden">
                        <AlertError errors={errors} />

                        <div className="space-y-2">
                            <Label htmlFor="disk">
                                Disk <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.disk}
                                onValueChange={(value) => setData('disk', value as 'local' | 'external')}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select disk" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="local">Local</SelectItem>
                                    <SelectItem value="external">External</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                {data.disk === 'external'
                                    ? 'External files are referenced by URL'
                                    : 'Local files are uploaded to server'}
                            </p>
                        </div>

                        {data.disk === 'local' ? (
                            <div className="space-y-2">
                                <Label htmlFor="file">
                                    File <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="file"
                                    type="file"
                                    ref={fileInputRef}
                                    onChange={handleFileChange}
                                    className="hidden"
                                    required
                                />
                                <div 
                                    className={`flex flex-col items-center justify-center gap-2 w-full rounded-md border-2 border-dashed px-6 py-8 text-sm cursor-pointer transition-colors ${
                                        isDragging 
                                            ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' 
                                            : 'border-input bg-background hover:border-blue-500/50 hover:bg-accent/50'
                                    }`}
                                    onClick={() => fileInputRef.current?.click()}
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                >
                                    <Upload className={`h-8 w-8 ${isDragging ? 'text-blue-500 dark:text-blue-400' : 'text-muted-foreground'}`} />
                                    <div className="text-center">
                                        <p className="font-medium">
                                            {selectedFileName || 'Drop file here or click to browse'}
                                        </p>
                                        <p className="text-xs text-muted-foreground mt-1">
                                            Maximum file size: 100MB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Label htmlFor="path">
                                    URL <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="path"
                                    type="url"
                                    placeholder="https://example.com/document.pdf"
                                    value={data.path}
                                    onChange={(e) => setData('path', e.target.value)}
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    Enter the full URL of the external file
                                </p>
                            </div>
                        )}

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
                                placeholder="Document name"
                                required
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

                        {showFolderSelector && (
                            <div className="space-y-2">
                                <Label>Folders</Label>
                                <Popover open={openFolderPopover} onOpenChange={setOpenFolderPopover}>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className="w-full justify-start text-left font-normal"
                                        >
                                            <FolderIcon className="mr-2 h-4 w-4 shrink-0" />
                                            <span className="truncate">
                                                {selectedFolders.length > 0
                                                    ? `${selectedFolders.length} folder(s) selected`
                                                    : 'Select folders...'}
                                            </span>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-full p-0" align="start">
                                        <Command>
                                            <CommandInput
                                                placeholder="Search folders..."
                                                value={folderSearch}
                                                onValueChange={setFolderSearch}
                                            />
                                            <CommandList>
                                                <CommandEmpty>No folders found.</CommandEmpty>
                                                <CommandGroup>
                                                    {folderOptions.map((folder) => (
                                                        <CommandItem
                                                            key={folder.id}
                                                            value={folder.id}
                                                            onSelect={() => toggleFolder(folder)}
                                                        >
                                                            <Check
                                                                className={`mr-2 h-4 w-4 ${
                                                                    selectedFolders.find((f) => f.id === folder.id)
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0'
                                                                }`}
                                                            />
                                                            <span className="truncate">{folder.path}</span>
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                {selectedFolders.length > 0 && (
                                    <div className="flex flex-wrap gap-1 mt-2">
                                        {selectedFolders.map((folder) => (
                                            <Badge
                                                key={folder.id}
                                                variant="secondary"
                                                className="text-xs gap-1"
                                            >
                                                <span className="truncate max-w-[200px]">
                                                    {folder.path}
                                                </span>
                                                <X
                                                    className="h-3 w-3 cursor-pointer"
                                                    onClick={() => removeFolder(folder.id)}
                                                />
                                            </Badge>
                                        ))}
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
                            {isReplaceMode ? 'Replace' : 'Upload'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

