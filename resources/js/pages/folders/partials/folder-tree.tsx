import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import folders from '@/routes/folders';
import { type Folder } from '@/types';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import {
    ChevronDown,
    ChevronRight,
    Edit,
    Folder as FolderIcon,
    FolderOpen,
    FolderPlus,
    Info,
    MoreVertical,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface FolderTreeProps {
    folders: Folder[];
    onCreateSubfolder?: (folder: Folder) => void;
    onEditFolder?: (folder: Folder) => void;
    onDeleteFolder?: (folder: Folder) => void;
    onDetailsFolder?: (folder: Folder) => void;
    level?: number;
}

export function FolderTree({
    folders,
    onCreateSubfolder,
    onEditFolder,
    onDeleteFolder,
    onDetailsFolder,
    level = 0,
}: FolderTreeProps) {
    return (
        <div className="space-y-0.5">
            {folders.map((folder) => (
                <FolderTreeItem
                    key={folder.id}
                    folder={folder}
                    onCreateSubfolder={onCreateSubfolder}
                    onEditFolder={onEditFolder}
                    onDeleteFolder={onDeleteFolder}
                    onDetailsFolder={onDetailsFolder}
                    level={level}
                />
            ))}
        </div>
    );
}

interface FolderTreeItemProps {
    folder: Folder;
    onCreateSubfolder?: (folder: Folder) => void;
    onEditFolder?: (folder: Folder) => void;
    onDeleteFolder?: (folder: Folder) => void;
    onDetailsFolder?: (folder: Folder) => void;
    level: number;
}

function FolderTreeItem({
    folder,
    onCreateSubfolder,
    onEditFolder,
    onDeleteFolder,
    onDetailsFolder,
    level,
}: FolderTreeItemProps) {
    const [isOpen, setIsOpen] = useState<boolean>(false);
    const [children, setChildren] = useState<Folder[]>(folder.children || []);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const hasChildren = folder.children_count ? folder.children_count > 0 : false;

    // Fetch children when expanding
    useEffect(() => {
        if (isOpen && hasChildren && children.length === 0 && !isLoading) {
            fetchChildren();
        }
    }, [isOpen]);

    const fetchChildren = async () => {
        setIsLoading(true);
        try {
            const response = await axios.get(folders.show.url(folder.id), {
                params: { children: true },
                headers: { Accept: 'application/json' },
            });
            setChildren(response.data);
        } catch (error) {
            console.error('Error fetching children:', error);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
            <div 
                className="flex items-center gap-1.5 group py-1 hover:bg-accent/50 dark:hover:bg-accent rounded-md px-2"
                style={{ marginLeft: `${level * 20}px` }}
            >
                {/* Expand/collapse button - always show */}
                <CollapsibleTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0 shrink-0"
                    >
                        {isOpen ? (
                            <ChevronDown className="h-3.5 w-3.5" />
                        ) : (
                            <ChevronRight className="h-3.5 w-3.5" />
                        )}
                    </Button>
                </CollapsibleTrigger>

                {/* Folder link */}
                <Link
                    href={folders.show.url(folder.id)}
                    className="flex items-center gap-2 flex-1 min-w-0 py-1"
                >
                    {isOpen ? (
                        <FolderOpen className="h-5 w-5 text-blue-500 dark:text-blue-400 shrink-0" />
                    ) : (
                        <FolderIcon className="h-5 w-5 text-blue-500 dark:text-blue-400 shrink-0" />
                    )}
                    <span className="text-sm truncate">{folder.name}</span>
                    {folder.description && (
                        <span className="text-xs text-muted-foreground truncate">
                            · {folder.description}
                        </span>
                    )}
                </Link>

                {/* Action buttons */}
                <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                    {onCreateSubfolder && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 p-0"
                            onClick={() => onCreateSubfolder(folder)}
                            title="Create subfolder"
                        >
                            <FolderPlus className="h-4 w-4" />
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 p-0"
                            >
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {onCreateSubfolder && (
                                <DropdownMenuItem onClick={() => onCreateSubfolder(folder)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create
                                </DropdownMenuItem>
                            )}
                            {onEditFolder && (
                                <DropdownMenuItem onClick={() => onEditFolder(folder)}>
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                </DropdownMenuItem>
                            )}
                            {onDetailsFolder && (
                                <DropdownMenuItem onClick={() => onDetailsFolder(folder)}>
                                    <Info className="mr-2 h-4 w-4" />
                                    Details
                                </DropdownMenuItem>
                            )}
                            {onDeleteFolder && (
                                <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => onDeleteFolder(folder)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <CollapsibleContent>
                {isLoading ? (
                    <div 
                        className="text-xs text-muted-foreground py-1"
                        style={{ marginLeft: `${(level + 1) * 20}px` }}
                    >
                        Loading...
                    </div>
                    ) : children.length > 0 ? (
                        <FolderTree
                            folders={children}
                            onCreateSubfolder={onCreateSubfolder}
                            onEditFolder={onEditFolder}
                            onDeleteFolder={onDeleteFolder}
                            onDetailsFolder={onDetailsFolder}
                            level={level + 1}
                        />
                    ) : (
                    <div 
                        className="text-xs text-muted-foreground/60 dark:text-muted-foreground/50 py-1 italic"
                        style={{ marginLeft: `${(level + 1) * 20}px` }}
                    >
                        Empty
                    </div>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

