import { Btn, ErrorBanner, Modal, PageHeader, Segmented } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { BookOpen, Check, Plane, Printer, User } from 'lucide-react';
import { useState } from 'react';

interface Inbound {
    id: number;
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
            <BookOpen className="size-5" />
        </span>
    );
}

export default function Warehouse({ inbound, groups, packCount }: { inbound: Inbound[]; groups: Group[]; packCount: number }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [tab, setTab] = useState<'terima' | 'packing'>(inbound.length === 0 && packCount > 0 ? 'packing' : 'terima');
    const [confirming, setConfirming] = useState<Inbound | null>(null);
    const [busy, setBusy] = useState<number | null>(null);

    const bySchedule = inbound.reduce<Record<string, Inbound[]>>((acc, l) => {
        (acc[l.schedule] ??= []).push(l);
        return acc;
    }, {});

    const toggle = (item: PackItem) => router.post(`/warehouse/items/${item.id}/check`, { checked: !item.checked }, { preserveScroll: true, preserveState: true });

    return (
        <ErpLayout>
            <Head title="Gudang & Packing" />
            <PageHeader title="Gudang & Packing" subtitle="Kerjakan dari kiri ke kanan: terima barang dari Mesir, lalu packing per pembeli." />

            <div className="mb-4">
                <Segmented
                    value={tab}
                    options={[
                        { value: 'terima', label: `1. Barang dari Mesir (${inbound.length})` },
                        { value: 'packing', label: `2. Siap packing (${packCount})` },
                    ]}
                    onChange={setTab}
                />
            </div>
            <ErrorBanner message={errors.finish || errors.line} />

            {tab === 'terima' &&
                (inbound.length === 0 ? (
                    <div className="card py-14 text-center">
                        <p className="font-medium">Tidak ada kiriman yang menunggu konfirmasi.</p>
                    </div>
                ) : (
                    <div className="grid gap-5">
                        {Object.entries(bySchedule).map(([schedule, lines]) => (
                            <section key={schedule}>
                                <div className="mb-2.5 flex items-center gap-2">
                                    <Plane className="text-brand size-5" />
                                    <h2 className="font-display text-lg font-semibold">Bagasi {schedule}</h2>
                                    <span className="text-ink-3 text-sm">· {lines[0].carrier}</span>
                                </div>
                                <div className="grid gap-2.5 md:grid-cols-2">
                                    {lines.map((l) => (
                                        <article key={l.id} className="card flex gap-3.5 !p-3.5">
                                            <Photo id={l.product_id} has={l.has_photo} cls="h-[92px] w-[68px]" />
                                            <div className="flex min-w-0 flex-1 flex-col">
                                                {l.title_ar && <span className="ar block text-left text-[17px]">{l.title_ar}</span>}
                                                <span className="text-ink-2 text-[14px]">{l.title_latin}</span>
                                                {l.maktabah && <span className="text-ink-3 text-[12.5px]">{l.maktabah}</span>}
                                                <div className="mt-auto flex items-end gap-3 pt-2">
                                                    <div>
                                                        <span className="font-display num text-[26px] leading-none font-semibold">{l.qty}</span>
                                                        <span className="text-ink-3 ml-1 text-sm">buah</span>
                                                    </div>
                                                    <Btn variant="primary" className="ml-auto !h-11" onClick={() => setConfirming(l)}>
                                                        Konfirmasi diterima
                                                    </Btn>
                                                </div>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </section>
                        ))}
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
                                                        <button
                                                            type="button"
                                                            key={i.id}
                                                            onClick={() => toggle(i)}
                                                            className={'flex items-center gap-3 rounded-lg border p-2 text-left ' + (i.checked ? 'border-ok bg-ok-soft' : 'border-line hover:border-ink-3')}
                                                        >
                                                            <span className={'grid size-7 shrink-0 place-items-center rounded-md border-2 ' + (i.checked ? 'border-ok bg-ok text-white' : 'border-input')}>
                                                                {i.checked && <Check className="size-4" />}
                                                            </span>
                                                            <Photo id={i.product_id} has={i.has_photo} cls="h-11 w-8" />
                                                            <span className="min-w-0 flex-1">
                                                                {i.title_ar && <span className="ar block text-left">{i.title_ar}</span>}
                                                                <span className="text-ink-2 text-[13px]">{i.title_latin}</span>
                                                            </span>
                                                            <span className="font-display num text-lg font-semibold">×{i.qty}</span>
                                                        </button>
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
                                                        disabled={!all || !o.has_label || busy === o.id}
                                                        onClick={() => {
                                                            setBusy(o.id);
                                                            router.post(`/warehouse/orders/${o.id}/finish`, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
                                                        }}
                                                        title={!all ? 'Centang semua kitab dulu' : !o.has_label ? 'Resi belum ada' : ''}
                                                    >
                                                        Selesai
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

            <Modal open={confirming !== null} onClose={() => setConfirming(null)} title="Barang sudah di tangan Anda?" description="Tandai hanya bila kitabnya benar-benar sudah Anda terima dan cocok dengan foto.">
                {confirming && (
                    <div className="grid gap-4">
                        <div className="bg-soft flex items-center gap-3 rounded-xl p-3">
                            <Photo id={confirming.product_id} has={confirming.has_photo} cls="h-16 w-12" />
                            <div className="flex-1">
                                {confirming.title_ar && <span className="ar block text-left">{confirming.title_ar}</span>}
                                <span className="text-ink-2 text-sm">{confirming.title_latin}</span>
                                <span className="text-ink-3 block text-[12.5px]">Bagasi {confirming.schedule}</span>
                            </div>
                            <span className="font-display num text-2xl font-semibold">×{confirming.qty}</span>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Btn onClick={() => setConfirming(null)}>Batal</Btn>
                            <Btn variant="primary" onClick={() => router.post(`/warehouse/inbound/${confirming.id}/receive`, {}, { preserveScroll: true, onFinish: () => setConfirming(null) })}>
                                Ya, sudah diterima
                            </Btn>
                        </div>
                    </div>
                )}
            </Modal>
        </ErpLayout>
    );
}
