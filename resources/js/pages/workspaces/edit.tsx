import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface WorkspaceMember {
    id: string;
    name: string;
    email: string;
    pivot: {
        role: string;
        permissions: Record<string, boolean>;
        joined_at: string;
    };
}

interface Workspace {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    active: boolean;
    settings: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    members: WorkspaceMember[];
    owner: {
        id: string;
        name: string;
        email: string;
    };
}

interface Stats {
    member_count: number;
    folder_count: number;
    file_count: number;
    active_folders: number;
    active_files: number;
    total_size: number;
}

interface WorkspaceEditProps {
    workspace: Workspace;
    stats: Stats;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace Settings',
        href: '#',
    },
];

export default function WorkspaceEdit({ workspace, stats }: WorkspaceEditProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-xl font-semibold">{workspace.name}</h1>
                    <p className="text-sm text-muted-foreground">{workspace.description}</p>
                </div>
                
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border p-4">
                        <div className="text-2xl font-bold">{stats.member_count}</div>
                        <div className="text-sm text-muted-foreground">Members</div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="text-2xl font-bold">{stats.folder_count}</div>
                        <div className="text-sm text-muted-foreground">Folders</div>
                    </div>
                    <div className="rounded-lg border p-4">
                        <div className="text-2xl font-bold">{stats.file_count}</div>
                        <div className="text-sm text-muted-foreground">Files</div>
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <h2 className="mb-4 text-base font-semibold">Members</h2>
                    {workspace.members && workspace.members.length > 0 ? (
                        <ul className="space-y-2">
                            {workspace.members.map((member) => (
                                <li key={member.id} className="flex items-center justify-between">
                                    <div>
                                        <div className="font-medium">{member.name}</div>
                                        <div className="text-sm text-muted-foreground">{member.email}</div>
                                    </div>
                                    <div className="text-sm text-muted-foreground">{member.pivot.role}</div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">No members yet</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

