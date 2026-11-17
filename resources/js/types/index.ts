export type Role = 'owner' | 'admin' | 'mesir' | 'gudang';

export interface User {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
}

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavEntry {
    key: string;
    group: string;
    label: string;
    icon: string;
    path: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    nav: NavEntry[];
    flash: { success?: string | null; status?: string | null; temp_password?: { name: string; email: string; password: string } | null };
    unread: number;
    [key: string]: unknown;
}
