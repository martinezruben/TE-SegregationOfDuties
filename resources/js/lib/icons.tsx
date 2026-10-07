import {
    AppWindow,
    Badge,
    Circle,
    FileCheck,
    FlaskConical,
    Folder,
    Grid3x3,
    KeyRound,
    LayoutDashboard,
    ListTree,
    Map,
    ScrollText,
    Settings,
    ShieldAlert,
    Table2,
    Upload,
    UserCheck,
    UserCog,
    Users,
    type LucideIcon,
} from 'lucide-react';

/** Íconos disponibles para los menús (nombres kebab-case de la BD). */
const map: Record<string, LucideIcon> = {
    'layout-dashboard': LayoutDashboard,
    folder: Folder,
    map: Map,
    badge: Badge,
    users: Users,
    'app-window': AppWindow,
    'shield-alert': ShieldAlert,
    'grid-3x3': Grid3x3,
    'flask-conical': FlaskConical,
    'file-check': FileCheck,
    settings: Settings,
    'user-cog': UserCog,
    'key-round': KeyRound,
    'list-tree': ListTree,
    'scroll-text': ScrollText,
    upload: Upload,
    'user-check': UserCheck,
    'table-2': Table2,
};

export function menuIcon(name: string | null | undefined): LucideIcon {
    return (name && map[name]) || Circle;
}
