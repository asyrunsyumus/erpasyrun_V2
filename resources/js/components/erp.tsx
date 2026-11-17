import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { type ReactNode, useEffect, useRef, useState } from 'react';

/* ---------- Tipe paginasi Laravel ---------- */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

/* ---------- Judul halaman ---------- */
export function PageHeader({ title, subtitle, children }: { title: string; subtitle?: ReactNode; children?: ReactNode }) {
    return (
        <div className="mb-6 flex flex-wrap items-center gap-3">
            <div className="min-w-[220px] flex-1">
                <h1 className="text-[26px] font-semibold">{title}</h1>
                {subtitle && <p className="text-ink-2 mt-1">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

/* ---------- Tombol ---------- */
type BtnProps = { children: ReactNode; variant?: 'primary' | 'default' | 'danger' | 'ghost'; size?: 'md' | 'sm' } & React.ButtonHTMLAttributes<HTMLButtonElement>;
export function Btn({ children, variant = 'default', size = 'md', className = '', ...rest }: BtnProps) {
    const base = 'inline-flex items-center justify-center gap-2 rounded-[10px] font-medium whitespace-nowrap transition-colors disabled:opacity-50 disabled:cursor-not-allowed';
    const sz = size === 'sm' ? 'h-[34px] px-3 text-sm' : 'h-[42px] px-[18px]';
    const v = {
        primary: 'bg-brand text-white hover:opacity-95',
        default: 'bg-surface border border-line hover:border-ink-3',
        danger: 'bg-surface border border-line text-bad hover:border-bad',
        ghost: 'text-ink-2 hover:bg-soft',
    }[variant];
    return (
        <button className={`${base} ${sz} ${v} ${className}`} {...rest}>
            {children}
        </button>
    );
}

/* ---------- Pencarian (otomatis, jeda 350 ms) ---------- */
export function SearchBox({ value, placeholder, onSearch }: { value: string; placeholder: string; onSearch: (q: string) => void }) {
    const [q, setQ] = useState(value);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => onSearch(q), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);
    return (
        <label className="bg-surface border-line focus-within:border-brand flex h-[42px] min-w-[240px] flex-1 items-center gap-2 rounded-[10px] border px-3 sm:max-w-sm">
            <Search className="text-ink-3 size-[18px]" />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={placeholder} className="w-full bg-transparent outline-none placeholder:text-[var(--ink-3)]" />
        </label>
    );
}

/** Pindah halaman dengan filter baru, tanpa reload penuh. */
export function applyFilters(url: string, filters: Record<string, string | null | undefined>) {
    const clean = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v != null));
    router.get(url, clean, { preserveState: true, preserveScroll: true, replace: true });
}

/* ---------- Pilihan berbentuk pil ---------- */
export function Segmented<T extends string>({ value, options, onChange }: { value: T; options: { value: T; label: string }[]; onChange: (v: T) => void }) {
    return (
        <div className="bg-surface border-line flex h-[42px] items-center gap-0.5 rounded-[10px] border p-1">
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    onClick={() => onChange(o.value)}
                    className={'h-full rounded-[7px] px-3 text-sm font-medium ' + (value === o.value ? 'bg-brand-soft text-brand' : 'text-ink-2 hover:bg-soft')}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

/* ---------- Tabel ---------- */
export function Table({ head, children, empty }: { head: ReactNode; children: ReactNode; empty?: ReactNode }) {
    return (
        <div className="card overflow-x-auto !p-0">
            <table className="num w-full min-w-[640px]">
                <thead>
                    <tr className="text-ink-3 border-line border-b text-left text-[13px] [&>th]:px-4 [&>th]:py-3 [&>th]:font-medium">{head}</tr>
                </thead>
                <tbody className="[&>tr]:border-line [&>tr]:border-b [&>tr:last-child]:border-0 [&_td]:px-4 [&_td]:py-3 [&_td]:align-middle">{children}</tbody>
            </table>
            {empty}
        </div>
    );
}

export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center gap-2 px-6 py-14 text-center">
            <p className="font-medium">{title}</p>
            {children && <div className="text-ink-3 max-w-md text-sm">{children}</div>}
        </div>
    );
}

export function Pager<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) return page.total > 0 ? <p className="text-ink-3 mt-3 text-sm">{page.total} data</p> : null;
    return (
        <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
            <span className="text-ink-3 flex-1">
                {page.from}–{page.to} dari {page.total}
            </span>
            {page.prev_page_url ? (
                <Link href={page.prev_page_url} preserveScroll className="border-line hover:border-ink-3 rounded-[10px] border px-3 py-1.5 font-medium">
                    ← Sebelumnya
                </Link>
            ) : null}
            <span className="text-ink-2">
                Hal {page.current_page} / {page.last_page}
            </span>
            {page.next_page_url ? (
                <Link href={page.next_page_url} preserveScroll className="border-line hover:border-ink-3 rounded-[10px] border px-3 py-1.5 font-medium">
                    Berikutnya →
                </Link>
            ) : null}
        </div>
    );
}

/* ---------- Form ---------- */
export function Modal({ open, onClose, title, description, children, wide }: { open: boolean; onClose: () => void; title: string; description?: string; children: ReactNode; wide?: boolean }) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className={'bg-surface max-h-[92vh] overflow-y-auto ' + (wide ? 'sm:max-w-2xl' : 'sm:max-w-lg')}>
                <DialogHeader>
                    <DialogTitle className="font-display text-lg">{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

export function Field({ label, error, hint, children, className = '' }: { label: string; error?: string; hint?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <label className={'grid gap-1.5 ' + className}>
            <span className="text-sm font-medium">{label}</span>
            {children}
            {hint && !error && <span className="text-ink-3 text-xs">{hint}</span>}
            {error && <span className="text-bad text-[13px]">{error}</span>}
        </label>
    );
}

export const inputCls =
    'bg-surface border-input focus:border-brand h-11 w-full rounded-[10px] border px-3 outline-none transition-colors placeholder:text-[var(--ink-3)] disabled:bg-soft disabled:text-ink-3';

/** Input angka dengan pemisah ribuan (150.000) tapi nilainya tetap integer. */
export function MoneyInput({ value, onChange, prefix = 'Rp', disabled }: { value: number | ''; onChange: (v: number | '') => void; prefix?: string; disabled?: boolean }) {
    const shown = value === '' ? '' : Number(value).toLocaleString('id-ID');
    return (
        <div className={inputCls + ' flex items-center gap-2 ' + (disabled ? 'bg-soft' : '')}>
            <span className="text-ink-3 text-sm">{prefix}</span>
            <input
                inputMode="numeric"
                disabled={disabled}
                className="num w-full bg-transparent outline-none"
                value={shown}
                onChange={(e) => {
                    const digits = e.target.value.replace(/\D/g, '');
                    onChange(digits === '' ? '' : Number(digits));
                }}
            />
        </div>
    );
}

/* ---------- Hapus dengan konfirmasi ---------- */
export function ConfirmDelete({ open, onClose, title, message, onConfirm, processing }: { open: boolean; onClose: () => void; title: string; message: ReactNode; onConfirm: () => void; processing?: boolean }) {
    return (
        <Modal open={open} onClose={onClose} title={title}>
            <div className="text-ink-2 text-[15px]">{message}</div>
            <div className="mt-2 flex justify-end gap-2">
                <Btn onClick={onClose}>Batal</Btn>
                <Btn variant="primary" className="!bg-bad" onClick={onConfirm} disabled={processing}>
                    Ya, hapus
                </Btn>
            </div>
        </Modal>
    );
}

/* ---------- Pesan error umum dari server ---------- */
export function ErrorBanner({ message }: { message?: string }) {
    if (!message) return null;
    return (
        <div className="bg-bad-soft text-bad mb-4 rounded-xl px-4 py-3 text-sm font-medium" role="alert">
            {message}
        </div>
    );
}
