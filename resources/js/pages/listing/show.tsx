import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import PublicLayout, { formatBytes } from '@/layouts/public-layout';
import { type Folder } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Download, Hash } from 'lucide-react';

interface ListingShowProps {
    folder: Folder;
    descendants: Folder[];
    rootFolders: Folder[];
}

export default function ListingShow({ folder, descendants, rootFolders }: ListingShowProps) {
    return (
        <PublicLayout rootFolders={rootFolders} activeFolder={folder}>
            <Head title={folder.name} />

            <div className="space-y-6">
                {/* Breadcrumbs */}
                <nav className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Link href="/" className="hover:text-foreground transition-colors">
                        Home
                    </Link>
                    {folder.ancestors && folder.ancestors.map((ancestor) => (
                        <div key={ancestor.id} className="flex items-center gap-2">
                            <ChevronRight className="h-4 w-4" />
                            <Link 
                                href={`/browse/${ancestor.id}`}
                                className="hover:text-foreground transition-colors"
                            >
                                {ancestor.name}
                            </Link>
                        </div>
                    ))}
                    <ChevronRight className="h-4 w-4" />
                    <span className="text-foreground font-medium">{folder.name}</span>
                </nav>

                {/* Folder Header */}
                {folder.description && (
                    <div>
                        <h1 className="text-2xl font-bold">{folder.name}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">{folder.description}</p>
                    </div>
                )}

                {/* Flat Hierarchical List */}
                <div>
                    {/* Current folder's files first */}
                    {folder.files && folder.files.length > 0 && folder.files.map((file) => (
                        <FileItem key={file.id} file={file} level={folder.level} />
                    ))}

                    {/* Then descendants with their files */}
                    {descendants.length > 0 ? (
                        descendants.map((descendant) => (
                            <div key={descendant.id}>
                                {/* Folder */}
                                <FolderItem folder={descendant} baseLevel={folder.level} />
                                
                                {/* Folder's files immediately after */}
                                {descendant.files && descendant.files.length > 0 && descendant.files.map((file) => (
                                    <FileItem key={file.id} file={file} level={descendant.level} />
                                ))}
                            </div>
                        ))
                    ) : (
                        !folder.files?.length && (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <p className="text-sm text-muted-foreground">
                                    No contents in this folder
                                </p>
                            </div>
                        )
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}

interface FolderItemProps {
    folder: Folder;
    baseLevel: number;
}

function FolderItem({ folder, baseLevel }: FolderItemProps) {
    // Calculate indentation: relative to base folder (first child = 0, no padding)
    const relativeLevel = folder.level - baseLevel;
    const indentPx = relativeLevel === 1 ? 8 : (relativeLevel - 1) * 20 + 8; // Add 8px base padding
    
    // Determine heading size based on relative level (h1, h2, h3, h4 minimum)
    const headingLevel = Math.min(relativeLevel, 4);
    const HeadingTag = `h${headingLevel}` as keyof JSX.IntrinsicElements;
    
    // Size classes for each heading level
    const sizeClasses = {
        h1: 'text-xl',
        h2: 'text-lg',
        h3: 'text-base',
        h4: 'text-sm',
    };

    return (
        <div
            className="py-1.5"
            style={{ paddingLeft: `${indentPx}px` }}
        >
            <HeadingTag className={`font-bold ${sizeClasses[HeadingTag as keyof typeof sizeClasses]}`}>
                {folder.name}
            </HeadingTag>
        </div>
    );
}

interface FileItemProps {
    file: any;
    level: number;
}

function FileItem({ file, level }: FileItemProps) {
    // Files are indented based on parent folder level
    const indentPx = level * 20 + 8; // 20px per level + 8px base padding

    return (
        <a
            href={`/files/${file.id}/download`}
            className="flex items-center gap-2 py-1 pr-2 hover:bg-accent/50 rounded transition-colors cursor-pointer group"
            style={{ paddingLeft: `${indentPx}px` }}
        >
            <div className="shrink-0">
                <span className="text-sm font-mono group-hover:text-foreground transition-colors">{file.name}</span>
            </div>

            {/* Dotted leader */}
            <div className="flex-1 min-w-0 border-b border-dotted border-muted-foreground/30 group-hover:border-muted-foreground/50 transition-colors" />

            <div className="flex items-center gap-2 text-xs text-muted-foreground shrink-0">
                <span>{file.extension.toUpperCase()}</span>
                <span>•</span>
                <span>{formatBytes(file.size)}</span>
            </div>

            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <div className="shrink-0 text-muted-foreground group-hover:text-foreground transition-colors">
                            <Hash className="h-3 w-3" />
                        </div>
                    </TooltipTrigger>
                    <TooltipContent side="left" className="max-w-xs">
                        <p className="text-xs font-mono break-all">{file.hash}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>

            <div className="shrink-0 text-muted-foreground group-hover:text-foreground transition-colors">
                <Download className="h-3 w-3" />
            </div>
        </a>
    );
}
