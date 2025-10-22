import {
    DndContext,
    closestCenter,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { GripVertical, MoreVertical } from 'lucide-react';
import { Folder } from '@/types';
import { useState, useEffect } from 'react';
import folders from '@/routes/folders';
import { router } from '@inertiajs/react';

interface SortableItemProps {
    folder: Folder;
    onEdit: (folder: Folder) => void;
    onDelete: (folder: Folder) => void;
    onDetails: (folder: Folder) => void;
    onCreateSubfolder: (folder: Folder) => void;
}

function SortableItem({ folder, onEdit, onDelete, onDetails, onCreateSubfolder }: SortableItemProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: folder.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="flex items-center gap-2 rounded-md border bg-card p-2 hover:bg-accent/50"
        >
            <div
                {...attributes}
                {...listeners}
                className="cursor-grab active:cursor-grabbing"
            >
                <GripVertical className="h-4 w-4 text-muted-foreground" />
            </div>

            <Tooltip delayDuration={300}>
                <TooltipTrigger asChild>
                    <a
                        href={folders.show.url(folder.id)}
                        className="flex-1 text-sm hover:underline min-w-0"
                    >
                        <span className="block truncate">{folder.name}</span>
                    </a>
                </TooltipTrigger>
                <TooltipContent>
                    <p>{folder.name}</p>
                </TooltipContent>
            </Tooltip>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                        <MoreVertical className="h-4 w-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => onCreateSubfolder(folder)}>
                        Create
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onEdit(folder)}>
                        Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onDetails(folder)}>
                        Details
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => onDelete(folder)}>
                        Delete
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

interface ReorderableFolderListProps {
    initialFolders: Folder[];
    onEdit: (folder: Folder) => void;
    onDelete: (folder: Folder) => void;
    onDetails: (folder: Folder) => void;
    onCreateSubfolder: (folder: Folder) => void;
}

export function ReorderableFolderList({
    initialFolders,
    onEdit,
    onDelete,
    onDetails,
    onCreateSubfolder,
}: ReorderableFolderListProps) {
    const [foldersList, setFoldersList] = useState(initialFolders);
    const [isReordering, setIsReordering] = useState(false);

    // Update state when initialFolders prop changes (after page reload)
    useEffect(() => {
        setFoldersList(initialFolders);
    }, [initialFolders]);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        })
    );

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (over && active.id !== over.id) {
            setFoldersList((items) => {
                const oldIndex = items.findIndex((item) => item.id === active.id);
                const newIndex = items.findIndex((item) => item.id === over.id);

                const newItems = arrayMove(items, oldIndex, newIndex);

                // Update order values
                const foldersData = newItems.map((folder, index) => ({
                    id: folder.id,
                    order: index + 1,
                }));

                // Submit reorder to backend
                setIsReordering(true);
                router.post(
                    folders.reorder.url(),
                    { folders: foldersData },
                    {
                        preserveScroll: true,
                        onFinish: () => setIsReordering(false),
                    }
                );

                return newItems;
            });
        }
    };

    return (
        <TooltipProvider>
            <div className="space-y-2">
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={foldersList.map((f) => f.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        {foldersList.map((folder) => (
                            <SortableItem
                                key={folder.id}
                                folder={folder}
                                onEdit={onEdit}
                                onDelete={onDelete}
                                onDetails={onDetails}
                                onCreateSubfolder={onCreateSubfolder}
                            />
                        ))}
                    </SortableContext>
                </DndContext>

                {isReordering && (
                    <div className="text-center text-sm text-muted-foreground">
                        Saving order...
                    </div>
                )}
            </div>
        </TooltipProvider>
    );
}

