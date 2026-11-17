import { Btn, Field, inputCls, Modal } from '@/components/erp';
import { PaymentBadge, StatusBadge } from '@/components/order-bits';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, Check, ChevronLeft, FileText, Info, Printer, Upload } from 'lucide-react';
import { useState } from 'react';

interface Item {
    id: number;
    product_id: number;
    title_latin: string;
    title_ar: string | null;
    has_photo: boolean;
    weight_grams: number;
    qty: number;
    unit_price: number;
    line_total: number;
    qty_from_stock: number;
    qty_to_buy: number;
}
interface Props {
    order: {
        id: number;
        number: string;
        channel: string;
        payment_status: string;
        status: string;
        status_label: string;
        waiting_for: string;
        subtotal: number;
        discount: number;
        shipping_fee: number;
        total: number;
        notes: string | null;
        paid_at: string | null;
        created_at: string;
        created_by: string | null;
        customer: { id: number; code: string; name: string; type: string; phone: string | null; address: string | null; city: string | null };
        invoice: { number: string } | null;
        label: { name: string; by: string | null; at: string } | null;
        items: Item[];
        histories: { at: string; note: string; by: string | null }[];
    };
    steps: { key: string; label: string; state: 'done' | 'now' | 'todo' | 'skipped' }[];
    cost: { cogs: number; profit: number; margin: number } | null;
    can: { mark_paid: boolean; upload_label: boolean; cancel: boolean };
}

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

export default function OrderShow({ order, steps, cost, can }: Props) {
    const [payOpen, setPayOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const pay = useForm({ paid_at: today() });
    const cancel = useForm({ reason: '' });
    const label = useForm<{ file: File | null }>({ file: null });
    const qty = order.items.reduce((s, i) => s + i.qty, 0);

    return (
        <ErpLayout>
            <Head title={`Order ${order.number}`} />

            <Link href="/orders" className="text-ink-3 mb-2.5 inline-flex items-center gap-1 text-sm">
                <ChevronLeft className="size-4" /> Kembali ke daftar order
            </Link>
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <div className="min-w-[240px] flex-1">
                    <h1 className="num text-[26px] font-semibold">Order {order.number}</h1>
                    <p className="text-ink-2 mt-1">
                        {order.customer.name} ({order.customer.type === 'reseller' ? 'Reseller' : 'Reguler'}) · lewat {order.channel === 'shopee' ? 'Shopee' : 'WhatsApp'} · {order.created_at}
                    </p>
                </div>
                <StatusBadge status={order.status} label={order.status_label} />
                {order.invoice && (
                    <a href={`/orders/${order.id}/invoice`} target="_blank" rel="noopener" className="border-line hover:border-ink-3 inline-flex h-[42px] items-center gap-2 rounded-[10px] border px-[18px] font-medium">
                        <Printer className="size-[18px]" /> Cetak invoice
                    </a>
                )}
            </div>

            <div className="bg-brand-soft text-brand mb-4 flex items-start gap-2.5 rounded-xl px-4 py-3.5 text-[14.5px]">
                <Info className="mt-0.5 size-5 shrink-0" />
                <span>{order.waiting_for}</span>
            </div>

            {order.status !== 'dibatalkan' && (
                <div className="card mb-4 overflow-x-auto">
                    <ol className="flex min-w-[620px] items-center">
                        {steps.map((s, i) => (
                            <li key={s.key} className="flex flex-1 items-center gap-2.5 pr-2 last:flex-none">
                                <span
                                    className={
                                        'grid size-[30px] shrink-0 place-items-center rounded-full border-2 text-[13px] font-semibold ' +
                                        (s.state === 'done'
                                            ? 'bg-brand border-brand text-white'
                                            : s.state === 'now'
                                              ? 'border-brand text-brand bg-surface'
                                              : s.state === 'skipped'
                                                ? 'border-line text-ink-3 bg-soft'
                                                : 'border-line text-ink-3 bg-surface')
                                    }
                                >
                                    {s.state === 'done' ? <Check className="size-4" /> : s.state === 'skipped' ? '–' : i + 1}
                                </span>
                                <span className={'text-sm font-medium whitespace-nowrap ' + (s.state === 'now' ? 'text-brand' : s.state === 'todo' || s.state === 'skipped' ? 'text-ink-3' : '')}>
                                    {s.label}
                                    {s.state === 'skipped' && <small className="block text-[11.5px] font-normal">dilewati</small>}
                                </span>
                                {i < steps.length - 1 && <span className={'h-0.5 min-w-3 flex-1 ' + (s.state === 'done' || s.state === 'skipped' ? 'bg-brand' : 'bg-line')} />}
                            </li>
                        ))}
                    </ol>
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                <div className="grid content-start gap-4">
                    <section className="card overflow-x-auto">
                        <div className="mb-3 flex items-baseline gap-2.5">
                            <h3 className="text-base font-semibold">Kitab yang dipesan</h3>
                            <span className="text-ink-3 text-[13.5px]">
                                {order.items.length} judul · {qty} buah
                            </span>
                        </div>
                        <table className="num w-full min-w-[520px]">
                            <thead>
                                <tr className="text-ink-3 border-line border-b text-left text-[13px] [&>th]:py-2.5 [&>th]:font-medium">
                                    <th>Kitab</th>
                                    <th className="text-right">Jumlah</th>
                                    <th className="pl-4">Diambil dari</th>
                                    <th className="text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                {order.items.map((i) => (
                                    <tr key={i.id} className="border-line border-b last:border-0 [&>td]:py-3">
                                        <td>
                                            <div className="flex items-center gap-3">
                                                {i.has_photo ? (
                                                    <img src={`/products/${i.product_id}/photo`} className="h-11 w-8 rounded object-cover" alt="" />
                                                ) : (
                                                    <span className="bg-soft text-ink-3 grid h-11 w-8 place-items-center rounded">
                                                        <BookOpen className="size-4" />
                                                    </span>
                                                )}
                                                <div>
                                                    {i.title_ar && <span className="ar block text-left">{i.title_ar}</span>}
                                                    <small className="text-ink-3 text-[13px]">
                                                        {i.title_latin} · {rupiah(i.unit_price)}
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="text-right">{i.qty}</td>
                                        <td className="pl-4">
                                            <div className="flex flex-wrap gap-1.5 text-[12.5px]">
                                                {i.qty_from_stock > 0 && <span className="bg-ok-soft text-ok rounded-md px-2 py-0.5">{i.qty_to_buy ? `${i.qty_from_stock} gudang` : 'gudang'}</span>}
                                                {i.qty_to_buy > 0 && <span className="bg-gold-soft text-gold-ink rounded-md px-2 py-0.5">{i.qty_from_stock ? `${i.qty_to_buy} dari Mesir` : 'dari Mesir'}</span>}
                                            </div>
                                        </td>
                                        <td className="text-right">{rupiah(i.line_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="num mt-3 ml-auto max-w-[320px]">
                            <div className="text-ink-2 flex justify-between py-1.5">
                                <span>Subtotal</span>
                                <b className="text-ink font-medium">{rupiah(order.subtotal)}</b>
                            </div>
                            {order.discount > 0 && (
                                <div className="text-ink-2 flex justify-between py-1.5">
                                    <span>Diskon</span>
                                    <b className="text-bad font-medium">− {rupiah(order.discount)}</b>
                                </div>
                            )}
                            <div className="text-ink-2 flex justify-between py-1.5">
                                <span>Ongkir</span>
                                <b className="text-ink font-medium">{rupiah(order.shipping_fee)}</b>
                            </div>
                            <div className="border-line mt-1 flex justify-between border-t pt-3">
                                <span className="font-semibold">Total</span>
                                <b className="font-display text-[19px] font-semibold">{rupiah(order.total)}</b>
                            </div>
                        </div>
                        {order.notes && <p className="bg-soft text-ink-2 mt-4 rounded-lg p-3 text-sm">Catatan: {order.notes}</p>}
                    </section>

                    <section className="card">
                        <h3 className="mb-2 text-base font-semibold">Riwayat pesanan</h3>
                        <div className="num">
                            {order.histories.map((h, i) => (
                                <div key={i} className="border-line flex gap-3 border-b py-2.5 last:border-0">
                                    <time className="text-ink-3 w-[110px] shrink-0 text-[13px]">{h.at}</time>
                                    <span>
                                        {h.note}
                                        {h.by && <small className="text-ink-3 block text-[13px]">oleh {h.by}</small>}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <div className="grid content-start gap-4">
                    <section className="card">
                        <div className="mb-3 flex items-center gap-2">
                            <h3 className="flex-1 text-base font-semibold">Pembayaran</h3>
                            <PaymentBadge status={order.payment_status} />
                        </div>
                        {order.invoice && (
                            <div className="text-ink-2 num flex justify-between py-1.5">
                                <span>No. invoice</span>
                                <b className="text-ink font-medium">{order.invoice.number}</b>
                            </div>
                        )}
                        {order.paid_at && (
                            <div className="text-ink-2 flex justify-between py-1.5">
                                <span>Tanggal bayar</span>
                                <b className="text-ink font-medium">{order.paid_at}</b>
                            </div>
                        )}
                        {can.mark_paid && (
                            <Btn variant="primary" className="mt-3 w-full" onClick={() => setPayOpen(true)}>
                                Tandai lunas
                            </Btn>
                        )}
                    </section>

                    <section className="card">
                        <div className="mb-3 flex items-center gap-2">
                            <h3 className="flex-1 text-base font-semibold">Resi pengiriman</h3>
                            {order.label ? <span className="text-ok text-[13px] font-medium">Sudah ada</span> : <span className="text-warn text-[13px] font-medium">Belum ada</span>}
                        </div>
                        {order.label && (
                            <div className="border-line mb-3 flex items-center gap-3 rounded-xl border p-3">
                                <span className="bg-brand-soft text-brand grid size-9 place-items-center rounded-lg">
                                    <FileText className="size-[18px]" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{order.label.name}</p>
                                    <p className="text-ink-3 text-[12.5px]">
                                        {order.label.by} · {order.label.at}
                                    </p>
                                </div>
                                <a href={`/orders/${order.id}/label`} target="_blank" rel="noopener" className="text-brand text-sm font-medium">
                                    Lihat
                                </a>
                            </div>
                        )}
                        {can.upload_label && (
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    label.post(`/orders/${order.id}/label`, { forceFormData: true, preserveScroll: true, onSuccess: () => label.reset() });
                                }}
                                className="grid gap-2"
                            >
                                <label className="border-line hover:border-brand text-ink-2 flex cursor-pointer items-center gap-2 rounded-[10px] border border-dashed px-3 py-2.5 text-sm">
                                    <Upload className="size-4" />
                                    <span className="truncate">{label.data.file ? label.data.file.name : order.label ? 'Ganti file resi (PDF/JPG)' : 'Pilih file resi (PDF/JPG)'}</span>
                                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" onChange={(e) => label.setData('file', e.target.files?.[0] ?? null)} />
                                </label>
                                {label.errors.file && <p className="text-bad text-[13px]">{label.errors.file}</p>}
                                {label.data.file && (
                                    <Btn variant="primary" size="sm" disabled={label.processing}>
                                        Unggah resi
                                    </Btn>
                                )}
                            </form>
                        )}
                    </section>

                    <section className="card">
                        <h3 className="mb-2 text-base font-semibold">Dikirim ke</h3>
                        <p className="font-medium">
                            {order.customer.name}
                            {order.customer.phone && <span className="num"> · {order.customer.phone}</span>}
                        </p>
                        <p className="text-ink-3 mt-1 text-sm">{[order.customer.address, order.customer.city].filter(Boolean).join(', ') || 'Alamat belum diisi di data customer.'}</p>
                    </section>

                    {can.cancel && (
                        <button type="button" onClick={() => setCancelOpen(true)} className="border-line text-bad hover:border-bad h-[42px] rounded-[10px] border bg-[var(--surface)] font-medium">
                            Ajukan pembatalan
                        </button>
                    )}

                    {cost && (
                        <section className="card !bg-soft num">
                            <div className="mb-2 flex items-baseline gap-2">
                                <h3 className="flex-1 text-base font-semibold">Untung pesanan ini</h3>
                                <span className="text-ink-3 text-[12.5px]">hanya Owner</span>
                            </div>
                            <div className="text-ink-2 flex justify-between py-1.5">
                                <span>Modal (termasuk bagasi)</span>
                                <b className="text-ink font-medium">{rupiah(cost.cogs)}</b>
                            </div>
                            <div className="text-ink-2 flex justify-between py-1.5">
                                <span>Untung</span>
                                <b className={'font-medium ' + (cost.profit < 0 ? 'text-bad' : 'text-ok')}>
                                    {rupiah(cost.profit)} ({cost.margin}%)
                                </b>
                            </div>
                        </section>
                    )}
                </div>
            </div>

            <Modal open={cancelOpen} onClose={() => setCancelOpen(false)} title="Ajukan pembatalan?" description="Pesanan berhenti di tahap ini sampai diputuskan di menu Pembatalan. Bila ditolak, pesanan kembali berjalan dan boleh diajukan lagi.">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        cancel.post(`/orders/${order.id}/cancel`, { preserveScroll: true, onSuccess: () => setCancelOpen(false) });
                    }}
                    className="grid gap-4"
                >
                    <Field label="Alasan" error={cancel.errors.reason}>
                        <textarea className={inputCls + ' h-24 py-2'} value={cancel.data.reason} onChange={(e) => cancel.setData('reason', e.target.value)} required placeholder="mis. pembeli membatalkan karena dobel pesan" />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setCancelOpen(false)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" className="!bg-bad" disabled={cancel.processing}>
                            Ajukan
                        </Btn>
                    </div>
                </form>
            </Modal>

            <Modal open={payOpen} onClose={() => setPayOpen(false)} title="Tandai lunas?" description="Lakukan hanya bila uangnya sudah benar-benar masuk. Langkah ini tidak bisa dibatalkan.">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        pay.post(`/orders/${order.id}/paid`, { preserveScroll: true, onSuccess: () => setPayOpen(false) });
                    }}
                    className="grid gap-4"
                >
                    <Field label="Tanggal uang masuk" error={pay.errors.paid_at}>
                        <input type="date" className={inputCls} max={today()} value={pay.data.paid_at} onChange={(e) => pay.setData('paid_at', e.target.value)} required />
                    </Field>
                    {order.payment_status === 'unpaid' && order.items.some((i) => i.qty_to_buy > 0) && (
                        <p className="bg-gold-soft text-gold-ink rounded-lg p-3 text-sm">Setelah disimpan, kitab yang perlu dibeli langsung masuk daftar belanja Tim Mesir.</p>
                    )}
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setPayOpen(false)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" disabled={pay.processing}>
                            Ya, sudah lunas
                        </Btn>
                    </div>
                </form>
            </Modal>
        </ErpLayout>
    );
}
