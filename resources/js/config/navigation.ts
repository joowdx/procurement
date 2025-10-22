import { dashboard } from '@/routes';
import files from '@/routes/files';
import folders from '@/routes/folders';
import { type NavItem } from '@/types';
import { BookOpen, FileText, Folder, LayoutGrid } from 'lucide-react';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Folders',
        href: folders.index(),
        icon: Folder,
    },
    {
        title: 'Files',
        href: files.index(),
        icon: FileText,
    },
];

export const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: BookOpen,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

