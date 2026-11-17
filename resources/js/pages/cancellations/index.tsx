import { Btn, ErrorBanner, Field, inputCls, Modal, MoneyInput, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface C {
    id: number;
    order_id: number;
    number: string;
    customer: string;
    total: number;
    paid: boolean;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    requested_by: string;
    requested_at: string;
    decided_by: string | null;
    decided_at: string | null;
    decision_note: string | null;
    refund_amount: number | null;
    refund_method: string | null;
    has_proof: boolean;
}

export default function Cancellations({ items }: { items: C[] }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [approving, setApproving] = useState<C | null>(null);
    const [rejecting, setRejecting] = useState<C | null>(null);
    const ap = useForm<{ refund_amount: number | ''; refund_method: string; proof: File | null }>({ refund_amount: '', refund_method: '', proof: null });
    const rj = useForm({ note: '' });
    const pending = items.filter((i) => i.status === 'pending');
    const done = items.filter((i) => i.status !== 'pending');

    return (
        <ErpLayout>
            <Head title="Pembatalan" />
            <PageHeader title="Pembatalan" subtitle="Ajukan dari halaman order. Di sini diputuskan: setujui atau tolak. Pengajuan yang ditolak boleh diajukan lagi." />
            <ErrorBanner message={errors.decision} />

            <h2 className="font-display mb-3 text-lg font-semibold">Menunggu keputusan ({pending.length})</h2>
            {pending.length === 0 ? (
                <p className="card text-ink-3 mb-6 text-sm">Tidak ada pengajuan.</p>
            ) : (
                <div className="mb-6 grid gap-3 lg:grid-cols-2">
                    {pending.map((c) => (
                        <article key={c.id} className="card !border-gold">
                            <div className="mb-1 flex items-baseline gap-2">
                                <Link href={`/orders/${c.order_id}`} className="num font-semibold">
                                    {c.number}
                                </Link>
                                <span className="text-ink-2">· {c.customer}</span>
                                <span className="num ml-auto font-medium">{rupiah(c.total)}</span>
                            </div>
                            <p className="text-ink-3 mb-3 text-[13px]">
                                Diajukan {c.requested_by} · {c.requested_at} · {c.paid ? 'sudah dibayar' : 'belum dibayar'}
                            </p>
                            <p className="bg-soft mb-4 rounded-lg p-3 text-sm">“{c.reason}”</p>
                            <div className="flex justify-end gap-2">
                                <Btn
                                    onClick={() => {
                                        rj.reset();
                                        setRejecting(c);
                                    }}
                                >
                                    Tolak
                                </Btn>
                                <Btn
                                    variant="primary"
                                    onClick={() => {
                                        ap.clearErrors();
                                        ap.setData({ refund_amount: c.paid ? c.total : 0, refund_method: '', proof: null });
                                        setApproving(c);
                                    }}
                                >
                                    Setujui
                                </Btn>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            {done.length > 0 && (
                <>
                    <h2 className="font-display text-ink-2 mb-3 text-lg font-semibold">Riwayat</h2>
                    <div className="card overflow-x-auto !p-0">
                        <table className="num w-full min-w-[640px] text-sm">
                            <tbody>
                                {done.map((c) => (
                                    <tr key={c.id} className="border-line border-b last:border-0 [&>td]:px-4 [&>td]:py-3">
                                        <td>
                                            <Link href={`/orders/${c.order_id}`} className="font-medium">
                                                {c.number}
                                            </Link>
                                            <small className="text-ink-3 block">{c.customer}</small>
                                        </td>
                                        <td className="text-ink-2 max-w-xs">{c.reason}</td>
                                        <td>{c.status === 'approved' ? <span className="text-bad font-medium">Disetujui</span> : <span className="text-ink-2 font-medium">Ditolak</span>}</td>
                                        <td className="text-ink-2">
                                            {c.decided_by} · {c.decided_at}
                                            {c.decision_note && <small className="block">{c.decision_note}</small>}
                                        </td>
                                        <td className="text-right">
                                            {c.refund_amount ? rupiah(c.refund_amount) : '—'}
                                            {c.refund_method && <small className="text-ink-3 block">{c.refund_method}</small>}
                                            {c.has_proof && (
                                                <a href={`/cancellations/${c.id}/proof`} target="_blank" rel="noopener" className="text-brand text-[12.5px] font-medium">
                                                    bukti
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <Modal open={approving !== null} onClose={() => setApproving(null)} title={`Setujui pembatalan ${approving?.number ?? ''}?`} description="Pesanan jadi Dibatalkan. Kitab yang sudah diambil dari gudang atau sudah dibeli di Mesir otomatis kembali ke stok Indonesia. Uang belanja Mesir tidak kembali.">
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        approving && ap.post(`/cancellations/${approving.id}/approve`, { forceFormData: true, preserveScroll: true, onSuccess: () => setApproving(null) });
                    }}
                >
                    <Field label="Jumlah refund" error={ap.errors.refund_amount} hint={approving ? `Total tagihan ${rupiah(approving.total)}. Isi 0 bila belum ada uang masuk.` : ''}>
                        <MoneyInput value={ap.data.refund_amount} onChange={(v) => ap.setData('refund_amount', v)} />
                    </Field>
                    <Field label="Metode refund" error={ap.errors.refund_method}>
                        <input className={inputCls} value={ap.data.refund_method} onChange={(e) => ap.setData('refund_method', e.target.value)} placeholder="mis. transfer BSI" />
                    </Field>
                    <Field label="Bukti transfer (opsional)" error={ap.errors.proof}>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="text-sm" onChange={(e) => ap.setData('proof', e.target.files?.[0] ?? null)} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setApproving(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" className="!bg-bad" disabled={ap.processing}>
                            Ya, batalkan pesanan
                        </Btn>
                    </div>
                </form>
            </Modal>

            <Modal open={rejecting !== null} onClose={() => setRejecting(null)} title="Tolak pengajuan?" description="Pesanan kembali ke tahap sebelumnya dan berjalan normal. Bila pembeli tetap ingin batal, pengajuan bisa dibuat lagi.">
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        rejecting && rj.post(`/cancellations/${rejecting.id}/reject`, { preserveScroll: true, onSuccess: () => setRejecting(null) });
                    }}
                >
                    <Field label="Alasan menolak (opsional)">
                        <input className={inputCls} value={rj.data.note} onChange={(e) => rj.setData('note', e.target.value)} />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setRejecting(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" disabled={rj.processing}>
                            Tolak pengajuan
                        </Btn>
                    </div>
                </form>
            </Modal>
        </ErpLayout>
    );
}
