export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export interface MenuItem {
    id: number;
    label: string;
    icon: string | null;
    route: string | null;
    children: MenuItem[];
}

export interface Flash {
    success?: string | null;
    error?: string | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
        roles: string[];
    };
    menu: MenuItem[];
    flash: Flash;
};
