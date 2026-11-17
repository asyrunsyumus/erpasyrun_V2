import { Btn, ConfirmDelete, Field, inputCls, Modal, MoneyInput, PageHeader, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface E {
    id: number;
    category: 'operasional' | 'lain';
    amount: number;
    spent_at: string;
    note: string;
    by: string;
}
interface Props {
    month: string;
    monthLabel: string;
    items: E[];
    totals: { operasional: number; lain: number };
}

const shift = (m: string, d: number) => {
    const [y, mo] = m.split('-').map(Number);
    const dt = new Date(y, mo - 1 + d, 1);
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
};
const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function Expenses({ month, monthLabel, items, totals }: Props) {
    const [editing, setEditing] = useState<E | 'new' | null>(null);
    const [deleting, setDeleting] = useState<E | null>(null);
    const form = useForm<{ category: 'operasional' | 'lain'; amount: number | ''; spent_at: string; note: string }>({ category: 'operasional', amount: '', spent_at: today(), note: '' });

    const open = (e: E | 'new') => {
        form.clearErrors();
        form.setData(e === 'new' ? { category: 'operasional', amount: '', spent_at: today(), note: '' } : { category: e.category, amount: e.amount, spent_at: e.spent_at, note: e.note });
        setEditing(e);
    };

    return (
        <ErpLayout>
            <Head title="Biaya" />
            <PageHeader title="Biaya" subtitle="Pengeluaran yang mengurangi untung bersih. Ongkos bagasi tidak dicatat di sini — isi di Jadwal Bagasi agar masuk modal per kitab.">
                <Btn variant="primary" onClick={() => open('new')}>
                    <Plus className="size-[18px]" /> Catat biaya
                </Btn>
            </PageHeader>

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <div className="bg-surface border-line flex items-center rounded-[10px] border">
                    <button className="hover:bg-soft grid size-[42px] place-items-center rounded-l-[10px]" onClick={() => router.get('/expenses', { month: shift(month, -1) })} aria-label="Bulan sebelumnya">
                        <ChevronLeft className="size-5" />
                    </button>
                    <span className="min-w-36 text-center font-medium">{monthLabel}</span>
                    <button className="hover:bg-soft grid size-[42px] place-items-center rounded-r-[10px]" onClick={() => router.get('/expenses', { month: shift(month, 1) })} aria-label="Bulan berikutnya">
                        <ChevronRight className="size-5" />
                    </button>
                </div>
                <span className="text-ink-2 num text-sm">
                    Operasional <b className="text-ink">{rupiah(totals.operasional)}</b> · Lain-lain <b className="text-ink">{rupiah(totals.lain)}</b> · Total{' '}
                    <b className="text-ink">{rupiah(totals.operasional + totals.lain)}</b>
                </span>
            </div>

            <Table
                head={
                    <>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Kategori</th>
                        <th className="text-right">Jumlah</th>
                        <th className="w-24" />
                    </>
                }
            >
                {items.map((e) => (
                    <tr key={e.id}>
                        <td className="text-ink-2">{e.spent_at}</td>
                        <td>
                            {e.note}
                            <small className="text-ink-3 block">oleh {e.by}</small>
                        </td>
                        <td className="text-ink-2">{e.category === 'operasional' ? 'Operasional' : 'Lain-lain'}</td>
                        <td className="text-right font-medium">{rupiah(e.amount)}</td>
                        <td className="text-right whitespace-nowrap">
                            <Btn size="sm" variant="ghost" onClick={() => open(e)} aria-label="Ubah">
                                <Pencil className="size-4" />
                            </Btn>
                            <Btn size="sm" variant="ghost" onClick={() => setDeleting(e)} aria-label="Hapus">
                                <Trash2 className="size-4" />
                            </Btn>
                        </td>
                    </tr>
                ))}
            </Table>
            {items.length === 0 && <p className="text-ink-3 py-10 text-center text-sm">Belum ada biaya di bulan ini.</p>}

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Catat biaya' : 'Ubah biaya'}>
                <form
                    className="grid gap-4"
                    onSubmit={(ev) => {
                        ev.preventDefault();
                        const o = { preserveScroll: true, onSuccess: () => setEditing(null) };
                        editing === 'new' ? form.post('/expenses', o) : form.put(`/expenses/${(editing as E).id}`, o);
                    }}
                >
                    <Field label="Kategori" error={form.errors.category}>
                        <div className="grid grid-cols-2 gap-2">
                            {(['operasional', 'lain'] as const).map((c) => (
                                <button
                                    type="button"
                                    key={c}
                                    onClick={() => form.setData('category', c)}
                                    className={'h-11 rounded-[10px] border font-medium ' + (form.data.category === c ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2')}
                                >
                                    {c === 'operasional' ? 'Operasional' : 'Lain-lain'}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <Field label="Keterangan" error={form.errors.note}>
                        <input className={inputCls} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} placeholder="mis. gaji admin November, listrik, kemasan" required />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Jumlah" error={form.errors.amount}>
                            <MoneyInput value={form.data.amount} onChange={(v) => form.setData('amount', v)} />
                        </Field>
                        <Field label="Tanggal" error={form.errors.spent_at}>
                            <input type="date" className={inputCls} max={today()} value={form.data.spent_at} onChange={(e) => form.setData('spent_at', e.target.value)} required />
                        </Field>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setEditing(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" disabled={form.processing}>
                            Simpan
                        </Btn>
                    </div>
                </form>
            </Modal>

            <ConfirmDelete
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title="Hapus biaya?"
                message={<>Biaya “{deleting?.note}” sebesar {deleting && rupiah(deleting.amount)} akan dihapus. Penghapusan tercatat di Log Aktivitas.</>}
                onConfirm={() => deleting && router.delete(`/expenses/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
