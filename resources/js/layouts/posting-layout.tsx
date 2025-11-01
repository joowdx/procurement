import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { login } from '@/routes';
import { type Folder, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { FileText, Folder as FolderIcon } from 'lucide-react';
import { type ReactNode } from 'react';

interface PostingLayoutProps {
    children: ReactNode;
    rootFolders: Folder[];
    activeFolder?: Folder | null;
}

export default function PostingLayout({ children, rootFolders, activeFolder }: PostingLayoutProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen bg-background">
            {/* Sidebar Navigation */}
            <aside className="w-64 border-r bg-card flex flex-col">
                {/* Header */}
                <div className="p-4 border-b">
                    <Link href="/" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <FileText className="h-4 w-4" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">Postings</p>
                            <p className="text-xs text-muted-foreground">Hub</p>
                        </div>
                    </Link>
                </div>

                {/* Level 0 Folders Navigation */}
                <nav className="flex-1 overflow-y-auto p-4">
                    <div className="space-y-1">
                        {rootFolders.map((folder) => {
                            const isActive = activeFolder?.id === folder.id || 
                                (activeFolder?.route && activeFolder.route.startsWith(folder.route + '/'));
                            
                            return (
                                <Link
                                    key={folder.id}
                                    href={`/browse/${folder.id}`}
                                    className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors ${
                                        isActive
                                            ? 'bg-accent text-accent-foreground font-medium'
                                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground'
                                    }`}
                                >
                                    <FileText className="h-4 w-4 shrink-0" />
                                    <span className="truncate">{folder.name}</span>
                                </Link>
                            );
                        })}
                    </div>
                </nav>

                {/* Footer with Theme Switcher */}
                <div className="p-4 border-t">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-muted-foreground">Theme</span>
                        <AppearanceToggleDropdown />
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="flex-1 flex flex-col">
                <main className="flex-1 p-6 overflow-y-auto">
                    {children}
                </main>

                {/* Footer */}
                <footer className="border-t py-4 px-6">
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <p>&copy; {new Date().getFullYear()} Posting Management System</p>
                        {auth.user ? (
                            <Link href="/dashboard" className="hover:text-foreground transition-colors">
                                Dashboard
                            </Link>
                        ) : (
                            <Link href={login()} className="hover:text-foreground transition-colors">
                                Login
                            </Link>
                        )}
                    </div>
                </footer>
            </div>
        </div>
    );
}

export function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

