import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    Boxes,
    CircleX,
    History,
    Home,
    Layers,
    LogOut,
    Menu,
    Plane,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Store,
    Users,
    Wallet,
    X,
    type LucideIcon,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';

const ICONS: Record<string, LucideIcon> = {
    home: Home,
    cart: ShoppingCart,
    users: Users,
    x: CircleX,
    book: BookOpen,
    store: Store,
    bag: ShoppingBag,
    plane: Plane,
    box: Boxes,
    layers: Layers,
    chart: BarChart3,
    money: Wallet,
    shield: ShieldCheck,
    history: History,
};

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { auth, nav, unread } = usePage<SharedData>().props;
    const current = typeof window !== 'undefined' ? window.location.pathname : '';

    const groups: { name: string; items: typeof nav }[] = [];
    for (const item of nav) {
        let g = groups.find((x) => x.name === item.group);
        if (!g) {
            g = { name: item.group, items: [] };
            groups.push(g);
        }
        g.items.push(item);
    }

    return (
        <div className="flex h-full flex-col gap-0.5 px-3.5 py-5">
            <Link href="/dashboard" className="flex items-center gap-2.5 px-2 pb-5" onClick={onNavigate}>
                <img src="/logo.png" alt="" className="size-10" />
                <span>
                    <b className="font-display block text-[15px] leading-tight font-semibold">Asyrun Syumus</b>
                    <small className="text-ink-3 text-xs">ERP Maktabah</small>
                </span>
            </Link>

            <nav className="flex flex-col gap-0.5">
                <Link
                    href="/notifications"
                    onClick={onNavigate}
                    className={
                        'flex items-center gap-3 rounded-[10px] px-3 py-2.5 font-medium transition-colors ' +
                        (current === '/notifications' ? 'bg-brand-soft text-brand' : 'text-ink-2 hover:bg-soft hover:text-ink')
                    }
                >
                    <Bell className="size-5 shrink-0" strokeWidth={1.7} />
                    Notifikasi
                    {unread > 0 && <span className="bg-bad ml-auto rounded-full px-2 py-px text-xs font-semibold text-white">{unread}</span>}
                </Link>
                {groups.map((g) => (
                    <div key={g.name || 'root'} className="flex flex-col gap-0.5">
                        {g.name && <div className="text-ink-3 px-2.5 pt-4 pb-1.5 text-xs font-medium">{g.name}</div>}
                        {g.items.map((item) => {
                            const Icon = ICONS[item.icon] ?? Home;
                            const active = current === item.path || current.startsWith(item.path + '/');
                            return (
                                <Link
                                    key={item.key}
                                    href={item.path}
                                    onClick={onNavigate}
                                    className={
                                        'flex items-center gap-3 rounded-[10px] px-3 py-2.5 font-medium transition-colors ' +
                                        (active ? 'bg-brand-soft text-brand' : 'text-ink-2 hover:bg-soft hover:text-ink')
                                    }
                                >
                                    <Icon className="size-5 shrink-0" strokeWidth={1.7} />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="border-line mt-auto flex items-center gap-2.5 border-t px-2.5 pt-3">
                <span className="bg-brand grid size-9 shrink-0 place-items-center rounded-full font-semibold text-white">
                    {auth.user.name.charAt(0).toUpperCase()}
                </span>
                <div className="min-w-0 flex-1">
                    <b className="block truncate text-sm font-semibold">{auth.user.name}</b>
                    <small className="text-ink-3 block truncate text-[12.5px]">{auth.user.role_label}</small>
                </div>
                <Link href="/settings/profile" className="text-ink-3 hover:bg-soft hover:text-ink rounded-lg p-1.5" title="Akun saya" onClick={onNavigate}>
                    <Settings className="size-[18px]" />
                </Link>
                <button
                    type="button"
                    className="text-ink-3 hover:bg-soft hover:text-ink rounded-lg p-1.5"
                    title="Keluar"
                    onClick={() => router.post(route('logout'))}
                >
                    <LogOut className="size-[18px]" />
                </button>
            </div>
        </div>
    );
}

function MobileBell() {
    const { unread } = usePage<SharedData>().props;
    return (
        <Link href="/notifications" className="hover:bg-soft relative rounded-lg p-2" aria-label="Notifikasi">
            <Bell className="size-6" />
            {unread > 0 && <span className="bg-bad absolute -top-0.5 -right-0.5 rounded-full px-1.5 text-[11px] font-semibold text-white">{unread}</span>}
        </Link>
    );
}

export default function ErpLayout({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const { flash } = usePage<SharedData>().props;

    return (
        <div className="min-h-screen lg:grid lg:grid-cols-[248px_1fr]">
            <aside className="bg-surface border-line sticky top-0 hidden h-screen overflow-y-auto border-r lg:block">
                <Sidebar />
            </aside>

            {/* Mobile: Tim Mesir & Gudang bekerja dari HP */}
            <div className="bg-surface border-line sticky top-0 z-30 flex items-center gap-3 border-b px-4 py-3 lg:hidden">
                <button type="button" onClick={() => setOpen(true)} className="hover:bg-soft -ml-1 rounded-lg p-2" aria-label="Buka menu">
                    <Menu className="size-6" />
                </button>
                <img src="/logo.png" alt="" className="size-8" />
                <b className="font-display flex-1 text-[15px] font-semibold">Asyrun Syumus</b>
                <MobileBell />
            </div>
            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/30" onClick={() => setOpen(false)} />
                    <aside className="bg-surface absolute inset-y-0 left-0 w-[280px] overflow-y-auto shadow-xl">
                        <button type="button" onClick={() => setOpen(false)} className="hover:bg-soft absolute top-4 right-3 rounded-lg p-2" aria-label="Tutup menu">
                            <X className="size-5" />
                        </button>
                        <Sidebar onNavigate={() => setOpen(false)} />
                    </aside>
                </div>
            )}

            <main className="erp-enter min-w-0 px-4 pt-5 pb-12 lg:max-w-[1280px] lg:px-8 lg:pt-7">
                {flash?.success && (
                    <div className="bg-ok-soft text-ok mb-4 rounded-xl px-4 py-3 text-sm font-medium" role="status">
                        {flash.success}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
