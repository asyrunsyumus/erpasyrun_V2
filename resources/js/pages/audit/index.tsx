import { applyFilters, PageHeader, Pager, type Paginated } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface Row {
    id: number;
    at: string;
    user: string;
    action: string;
    label: string;
    subject: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip: string | null;
}
interface Props {
    items: Paginated<Row>;
    actions: { value: string; label: string }[];
    users: { id: number; name: string }[];
    filters: { action: string | null; user: number | null };
}

function Diff({ before, after }: { before: Record<string, unknown> | null; after: Record<string, unknown> | null }) {
    const keys = Array.from(new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})]));
    if (keys.length === 0) return null;
    const show = (v: unknown) => (v === null || v === undefined ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));
    return (
        <div className="bg-soft num mt-2 grid gap-0.5 rounded-lg p-2.5 text-[12.5px]">
            {keys.map((k) => (
                <div key={k} className="flex flex-wrap gap-2">
                    <span className="text-ink-3 min-w-28">{k}</span>
                    {before && k in before && <span className="text-bad line-through">{show(before[k])}</span>}
                    {after && k in after && <span className="text-ok">{show(after[k])}</span>}
                </div>
            ))}
        </div>
    );
}

export default function Audit({ items, actions, users, filters }: Props) {
    const [open, setOpen] = useState<number | null>(null);
    return (
        <ErpLayout>
            <Head title="Log Aktivitas" />
            <PageHeader title="Log Aktivitas" subtitle="Catatan setiap tindakan penting: siapa, kapan, dan apa yang berubah. Tidak bisa diubah atau dihapus dari aplikasi." />
            <div className="mb-4 flex flex-wrap gap-3">
                <select className="bg-surface border-line h-[42px] rounded-[10px] border px-3 text-sm" value={filters.action ?? ''} onChange={(e) => applyFilters('/audit', { action: e.target.value, user: filters.user ? String(filters.user) : null })}>
                    <option value="">Semua tindakan</option>
                    {actions.map((a) => (
                        <option key={a.value} value={a.value}>
                            {a.label}
                        </option>
                    ))}
                </select>
                <select className="bg-surface border-line h-[42px] rounded-[10px] border px-3 text-sm" value={filters.user ?? ''} onChange={(e) => applyFilters('/audit', { action: filters.action, user: e.target.value })}>
                    <option value="">Semua orang</option>
                    {users.map((u) => (
                        <option key={u.id} value={u.id}>
                            {u.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="card !p-0">
                {items.data.length === 0 && <p className="text-ink-3 py-12 text-center text-sm">Belum ada catatan.</p>}
                {items.data.map((r) => (
                    <button key={r.id} type="button" onClick={() => setOpen(open === r.id ? null : r.id)} className="border-line hover:bg-soft block w-full border-b px-5 py-3 text-left last:border-0">
                        <div className="flex flex-wrap items-baseline gap-x-3">
                            <span className="font-medium">{r.label}</span>
                            <span className="text-ink-2 text-sm">oleh {r.user}</span>
                            {r.subject && <span className="text-ink-3 text-[12.5px]">{r.subject}</span>}
                            <span className="text-ink-3 num ml-auto text-[12.5px]">{r.at}</span>
                        </div>
                        {open === r.id && (
                            <>
                                <Diff before={r.before} after={r.after} />
                                {r.ip && <p className="text-ink-3 mt-1 text-[12px]">IP {r.ip}</p>}
                            </>
                        )}
                    </button>
                ))}
            </div>
            <Pager page={items} />
        </ErpLayout>
    );
}
