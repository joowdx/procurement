import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: string; // ULID
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Folder {
    id: string; // ULID
    parent_id: string | null;
    name: string;
    description: string | null;
    route: string; // Hierarchical route (e.g., "Parent/Child/Grandchild")
    level: number;
    order: number;
    created_by: string;
    updated_by: string;
    deactivated_by: string | null;
    deleted_by: string | null;
    created_at: string;
    updated_at: string;
    deactivated_at: string | null;
    deleted_at: string | null;
    parent?: Folder;
    children?: Folder[];
    children_count?: number;
    placements_count?: number;
    files?: File[];
}

export interface File {
    id: string; // ULID
    name: string;
    description: string | null;
    type: string; // MIME type
    extension: string;
    locked: boolean;
    metadata: Record<string, unknown> | null;
    created_by: string;
    updated_by: string;
    deleted_by: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    url: string | null; // Computed accessor
    size: number | null; // Computed accessor
    hash: string | null; // Computed accessor
    versions_sum_downloads?: number; // Sum of all version downloads via withSum
    version?: Version;
    versions?: Version[];
    folders?: Folder[];
    tags?: Tag[];
    comments?: Comment[];
}

export interface Version {
    id: string; // ULID
    file_id: string;
    number: number;
    hash: string;
    disk: 'local' | 's3' | 'external';
    path: string;
    size: number;
    downloads: number; // Download count for this version
    metadata: Record<string, unknown> | null;
    created_by: string;
    created_at: string;
}

export interface Tag {
    id: string; // ULID
    name: string;
    slug: string;
    color: string | null;
    description: string | null;
    created_by: string;
    created_at: string;
    updated_at: string;
    files?: File[];
}

export interface Comment {
    id: string; // ULID
    file_id: string;
    content: string;
    created_by: string;
    updated_by: string;
    created_at: string;
    updated_at: string;
    creator?: User;
}
