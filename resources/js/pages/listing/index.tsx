import PublicLayout from '@/layouts/public-layout';
import { type Folder } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FileText, Folder as FolderIcon } from 'lucide-react';

interface ListingIndexProps {
    rootFolders: Folder[];
}

export default function ListingIndex({ rootFolders }: ListingIndexProps) {
    return (
        <PublicLayout rootFolders={rootFolders}>
            <Head title="Procurement Documents" />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="text-center space-y-2">
                    <h1 className="text-3xl font-bold">Procurement Documents</h1>
                    <p className="text-muted-foreground">
                        Browse procurement plans, notices, and documents organized by category
                    </p>
                </div>

                {rootFolders.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center border-2 border-dashed rounded-lg">
                        <FolderIcon className="h-12 w-12 text-muted-foreground mb-3" />
                        <h3 className="text-base font-semibold mb-1">No folders available</h3>
                        <p className="text-sm text-muted-foreground">
                            Check back later for procurement documents
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {rootFolders.map((folder) => (
                            <Link
                                key={folder.id}
                                href={`/browse/${folder.id}`}
                                className="group relative overflow-hidden rounded-lg border bg-card p-6 hover:shadow-md transition-all"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                            <FolderIcon className="h-5 w-5 text-primary" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold group-hover:text-primary transition-colors">
                                                {folder.name}
                                            </h3>
                                            {folder.description && (
                                                <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">
                                                    {folder.description}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <ChevronRight className="h-4 w-4 text-muted-foreground group-hover:translate-x-1 transition-transform" />
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}

