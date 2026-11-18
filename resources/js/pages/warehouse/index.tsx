import { Btn, ErrorBanner, PageHeader, Segmented } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { BookOpen, Check, Package, Plane, Printer, User } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Inbound {
    id: number;
    schedule_id: number;
    qty: number;
    product_id: number;
    title_latin: string;
    title_ar: string | null;
    has_photo: boolean;
    maktabah: string | null;
    schedule: string;
    carrier: string;
}
interface PackItem {
    id: number;
    qty: number;
    product_id: number;
    title_latin: string;
    title_ar: string | null;
    has_photo: boolean;
    checked: boolean;
}
interface Group {
    customer: string;
    city: string | null;
    orders: { id: number; number: string; channel: string; has_label: boolean; items: PackItem[] }[];
}

function Photo({ id, has, cls }: { id: number; has: boolean; cls: string }) {
    return has ? (
        <img src={`/products/${id}/photo`} alt="" loading="lazy" className={`${cls} bg-soft shrink-0 rounded-md object-cover`} />
    ) : (
        <span className={`${cls} bg-soft text-ink-3 grid shrink-0 place-items-center rounded-md`}>
            <BookOpen className="size-4" />
        </span>
    );
}

/** Checkbox besar yang bisa disentuh dari HP. */
function Box({ checked, busy, onClick, label }: { checked: boolean; busy?: boolean; onClick?: () => void; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={busy || !onClick}
            aria-label={label}
            className={
                'grid size-8 shrink-0 place-items-center rounded-lg border-2 transition duration-150 ' +
                (checked ? 'border-ok bg-ok text-white' : 'border-input bg-surface hover:border-brand active:scale-95') +
                (busy ? ' opacity-50' : '')
            }
        >
            {checked && <Check className="size-[18px]" strokeWidth={2.5} />}
        </button>
    );
}

interface Arrival {
    id: number;
    qty: number;
    product_id: number;
    title_latin: string;
    title_ar: string | null;
    has_photo: boolean;
    source: string;
    schedule: string | null;
}

export default function Warehouse({ inbound, arrivals, groups, packCount }: { inbound: Inbound[]; arrivals: Arrival[]; groups: Group[]; packCount: number }) {
    const receiveArrival = (id: number) => router.post(`/warehouse/arrivals/${id}/receive`, {}, { preserveScroll: true });
    const receiveArrivalsAll = () => router.post('/warehouse/arrivals/receive-all', {}, { preserveScroll: true });
    const { errors } = usePage().props as unknown as { errors: Record<string, string> };
    const [tab, setTab] = useState<'terima' | 'packing'>(inbound.length === 0 && packCount > 0 ? 'packing' : 'terima');
    const [busy, setBusy] = useState<string | null>(null);

    // Kiriman dikelompokkan per jadwal bagasi (satu jadwal = satu rombongan).
    const bySchedule = useMemo(() => {
        const m = new Map<number, { title: string; carrier: string; lines: Inbound[] }>();
        for (const l of inbound) {
            const cur = m.get(l.schedule_id) ?? { title: l.schedule, carrier: l.carrier, lines: [] };
            cur.lines.push(l);
            m.set(l.schedule_id, cur);
        }
        return [...m.entries()];
    }, [inbound]);

    const receive = (id: number) => {
        setBusy('l' + id);
        router.post(`/warehouse/inbound/${id}/receive`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };
    const receiveAll = (scheduleId: number) => {
        setBusy('s' + scheduleId);
        router.post(`/warehouse/schedules/${scheduleId}/receive-all`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };
    const toggle = (item: PackItem) => router.post(`/warehouse/items/${item.id}/check`, { checked: !item.checked }, { preserveScroll: true, preserveState: true });

    return (
        <ErpLayout>
            <Head title="Gudang & Packing" />
            <PageHeader title="Gudang & Packing" subtitle="Kerjakan dari kiri ke kanan: centang kiriman dari Mesir, lalu packing per pembeli." />

            <div className="mb-4 overflow-x-auto">
                <Segmented
                    value={tab}
                    options={[
                        { value: 'terima', label: `1. Dari Mesir (${inbound.length})` },
                        { value: 'packing', label: `2. Siap packing (${packCount})` },
                    ]}
                    onChange={setTab}
                />
            </div>
            <ErrorBanner message={errors.finish || errors.line} />

            {tab === 'terima' && arrivals.length > 0 && (
                <section className="card mb-4">
                    <div className="mb-3 flex flex-wrap items-center gap-2.5">
                        <div className="min-w-0 flex-1">
                            <h2 className="font-display text-lg leading-tight font-semibold">Restok & kelebihan belanja</h2>
                            <p className="text-ink-3 text-[13px]">Stok di data produk bertambah setelah dicentang di sini.</p>
                        </div>
                        <Btn variant="primary" size="sm" onClick={receiveArrivalsAll}>
                            <Check className="size-4" /> Centang semua
                        </Btn>
                    </div>
                    <ul className="grid gap-2 md:grid-cols-2">
                        {arrivals.map((a) => (
                            <li key={a.id} className="border-line flex items-center gap-3 rounded-xl border p-2.5">
                                <Box label={`Terima ${a.title_latin}`} onClick={() => receiveArrival(a.id)} checked={false} />
                                <Photo id={a.product_id} has={a.has_photo} cls="h-12 w-9" />
                                <div className="min-w-0 flex-1">
                                    {a.title_ar && <span className="ar block text-left text-[16px]">{a.title_ar}</span>}
                                    <span className="text-ink-2 text-[13.5px]">{a.title_latin}</span>
                                    <span className="text-ink-3 block text-[12px]">
                                        {a.source}
                                        {a.schedule ? ` · bagasi ${a.schedule}` : ' · belum ada jadwal bagasi'}
                                    </span>
                                </div>
                                <span className="font-display num text-lg font-semibold">×{a.qty}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {tab === 'terima' &&
                (bySchedule.length === 0 ? (
                    <div className="card py-14 text-center">
                        <p className="font-medium">Tidak ada kiriman yang menunggu dicentang.</p>
                        <p className="text-ink-3 mt-1 text-sm">Kiriman muncul di sini begitu jadwal bagasinya berisi kitab dari Mesir.</p>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {bySchedule.map(([sid, s]) => {
                            return (
                                <section key={sid} className="card">
                                    <div className="mb-3 flex flex-wrap items-center gap-2.5">
                                        <span className="bg-brand-soft text-brand grid size-9 place-items-center rounded-full">
                                            <Plane className="size-[18px]" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <h2 className="font-display text-lg leading-tight font-semibold">Bagasi {s.title}</h2>
                                            <p className="text-ink-3 text-[13px]">{s.carrier}</p>
                                        </div>
                                        <Btn variant="primary" size="sm" disabled={busy === 's' + sid} onClick={() => receiveAll(sid)}>
                                            <Check className="size-4" /> Centang semua
                                        </Btn>
                                    </div>
                                    <ul className="grid gap-2">
                                        {s.lines.map((l) => (
                                            <li key={l.id} className="border-line flex items-center gap-3 rounded-xl border p-2.5">
                                                <Box label={`Terima ${l.title_latin}`} busy={busy === 'l' + l.id || busy === 's' + sid} onClick={() => receive(l.id)} checked={false} />
                                                <Photo id={l.product_id} has={l.has_photo} cls="h-14 w-10" />
                                                <div className="min-w-0 flex-1">
                                                    {l.title_ar && <span className="ar block text-left text-[16px]">{l.title_ar}</span>}
                                                    <span className="text-ink-2 text-[13.5px]">{l.title_latin}</span>
                                                    {l.maktabah && <span className="text-ink-3 block text-[12px]">{l.maktabah}</span>}
                                                </div>
                                                <span className="font-display num text-lg font-semibold">×{l.qty}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            );
                        })}
                    </div>
                ))}

            {tab === 'packing' &&
                (groups.length === 0 ? (
                    <div className="card py-14 text-center">
                        <p className="font-medium">Tidak ada pesanan yang siap dipacking.</p>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {groups.map((g) => (
                            <section key={g.customer} className="card">
                                <div className="mb-3 flex items-center gap-2.5">
                                    <span className="bg-brand-soft text-brand grid size-9 place-items-center rounded-full">
                                        <User className="size-[18px]" />
                                    </span>
                                    <div>
                                        <h2 className="font-display text-lg leading-tight font-semibold">{g.customer}</h2>
                                        <p className="text-ink-3 text-[13px]">
                                            {g.city ?? '—'} · {g.orders.length} pesanan
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-3">
                                    {g.orders.map((o) => {
                                        const done = o.items.filter((i) => i.checked).length;
                                        const all = done === o.items.length;
                                        return (
                                            <div key={o.id} className="border-line rounded-xl border p-3">
                                                <div className="mb-2 flex flex-wrap items-center gap-2">
                                                    <b className="num font-medium">{o.number}</b>
                                                    <span className="text-ink-3 text-[13px]">{o.channel === 'shopee' ? 'Shopee' : 'WhatsApp'}</span>
                                                    <span className="num text-ink-2 ml-auto text-[13px]">
                                                        {done}/{o.items.length} tercentang
                                                    </span>
                                                </div>
                                                <div className="grid gap-1.5">
                                                    {o.items.map((i) => (
                                                        <div key={i.id} className={'flex items-center gap-3 rounded-lg border p-2 ' + (i.checked ? 'border-ok bg-ok-soft' : 'border-line')}>
                                                            <Box label={`Packing ${i.title_latin}`} checked={i.checked} onClick={() => toggle(i)} />
                                                            <Photo id={i.product_id} has={i.has_photo} cls="h-11 w-8" />
                                                            <span className="min-w-0 flex-1">
                                                                {i.title_ar && <span className="ar block text-left">{i.title_ar}</span>}
                                                                <span className="text-ink-2 text-[13px]">{i.title_latin}</span>
                                                            </span>
                                                            <span className="font-display num text-lg font-semibold">×{i.qty}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    {o.has_label ? (
                                                        <a href={`/orders/${o.id}/label`} target="_blank" rel="noopener" className="border-line hover:border-ink-3 inline-flex h-11 items-center gap-2 rounded-[10px] border px-4 font-medium">
                                                            <Printer className="size-[18px]" /> Lihat / cetak resi
                                                        </a>
                                                    ) : (
                                                        <span className="bg-warn-soft text-warn inline-flex h-11 items-center rounded-[10px] px-4 text-sm font-medium">Resi belum diunggah Admin</span>
                                                    )}
                                                    <Btn
                                                        variant="primary"
                                                        className="ml-auto !h-11 !px-6"
                                                        disabled={!all || !o.has_label || busy === 'o' + o.id}
                                                        onClick={() => {
                                                            setBusy('o' + o.id);
                                                            router.post(`/warehouse/orders/${o.id}/finish`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
                                                        }}
                                                        title={!all ? 'Centang semua kitab dulu' : !o.has_label ? 'Resi belum ada' : ''}
                                                    >
                                                        <Package className="size-4" /> Selesai
                                                    </Btn>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>
                ))}
        </ErpLayout>
    );
}
