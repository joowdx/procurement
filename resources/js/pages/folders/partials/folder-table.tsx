import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import folders from '@/routes/folders';
import { type Folder } from '@/types';
import { Link } from '@inertiajs/react';
import { Edit, Info, MoreVertical, Plus, Trash2 } from 'lucide-react';

interface FolderTableProps {
    folders: Folder[];
    onCreate?: (folder: Folder) => void;
    onEdit?: (folder: Folder) => void;
    onDelete?: (folder: Folder) => void;
    onDetails?: (folder: Folder) => void;
}

export function FolderTable({ folders: folderList, onCreate, onEdit, onDelete, onDetails }: FolderTableProps) {
    return (
        <TooltipProvider>
            <div className="border rounded-lg overflow-hidden">
                <table className="w-full">
                    <thead className="bg-muted/50 dark:bg-muted">
                        <tr className="border-b">
                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground w-[40%]">
                                Name
                            </th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground w-[15%]">
                                Files
                            </th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground w-[15%]">
                                Folders
                            </th>
                            <th className="px-3 py-2 text-left text-xs font-medium text-muted-foreground w-[20%]">
                                Created
                            </th>
                            <th className="px-3 py-2 w-[10%]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {folderList.map((folder) => (
                            <tr
                                key={folder.id}
                                className="border-b last:border-0 hover:bg-muted/30 transition-colors"
                            >
                                <td className="px-3 py-2">
                                    <Tooltip delayDuration={300}>
                                        <TooltipTrigger asChild>
                                            <Link
                                                href={folders.show.url(folder.id)}
                                                className="block hover:underline"
                                            >
                                                <span className="block truncate">{folder.path}</span>
                                            </Link>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{folder.path}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                    {folder.description && (
                                        <Tooltip delayDuration={300}>
                                            <TooltipTrigger asChild>
                                                <p className="text-xs text-muted-foreground truncate mt-0.5 cursor-default">
                                                    {folder.description}
                                                </p>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{folder.description}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-sm">
                                    {folder.placements_count || 0}
                                </td>
                                <td className="px-3 py-2 text-sm">
                                    {folder.children_count || 0}
                                </td>
                                <td className="px-3 py-2 text-sm text-muted-foreground">
                                    {new Date(folder.created_at).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'short',
                                        day: 'numeric',
                                    })}
                                </td>
                                <td className="px-3 py-2">
                                    <div className="flex items-center justify-end">
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 w-7 p-0"
                                                >
                                                    <MoreVertical className="h-3.5 w-3.5" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                {onCreate && (
                                                    <DropdownMenuItem onClick={() => onCreate(folder)}>
                                                        <Plus className="mr-2 h-4 w-4" />
                                                        Create
                                                    </DropdownMenuItem>
                                                )}
                                                {onEdit && (
                                                    <DropdownMenuItem onClick={() => onEdit(folder)}>
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                )}
                                                {onDetails && (
                                                    <DropdownMenuItem onClick={() => onDetails(folder)}>
                                                        <Info className="mr-2 h-4 w-4" />
                                                        Details
                                                    </DropdownMenuItem>
                                                )}
                                                {onDelete && (
                                                    <DropdownMenuItem
                                                        className="text-destructive focus:text-destructive"
                                                        onClick={() => onDelete(folder)}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TooltipProvider>
    );
}

