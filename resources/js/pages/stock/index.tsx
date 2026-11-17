import { applyFilters, Btn, Field, inputCls, Modal, PageHeader, Pager, type Paginated, SearchBox, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { BookOpen, ClipboardList, FileDown, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Row {
    id: number;
    sku: string;
    title_latin: string;
    title_ar: string | null;
    author: string | null;
    publisher: string | null;
    category: string | null;
    weight_grams: number | null;
    has_photo: boolean;
    qty: number;
}
interface Count {
    id: number;
    status: 'submitted' | 'approved' | 'rejected';
    note: string | null;
    by: string;
    at: string;
    lines: { title_latin: string; title_ar: string | null; system_qty: number; counted_qty: number }[];
}
interface Props {
    items: Paginated<Row>;
    filters: { q: string; all: boolean };
    counts: Count[];
    canReport: boolean;
    canDecide: boolean;
    totals: { titles: number; units: number };
}

const COLS = [
    { key: 'sku', label: 'SKU' },
    { key: 'judul', label: 'Judul Latin' },
    { key: 'arab', label: 'Judul Arab' },
    { key: 'penulis', label: 'Penulis' },
    { key: 'penerbit', label: 'Penerbit' },
    { key: 'kategori', label: 'Kategori' },
    { key: 'berat', label: 'Berat' },
    { key: 'qty', label: 'Stok' },
    { key: 'hitung', label: 'Kolom hitung fisik' },
];

export default function Stock({ items, filters, counts, canReport, canDecide, totals }: Props) {
    const [cols, setCols] = useState<string[]>(['sku', 'judul', 'arab', 'qty', 'hitung']);
    const [pdfOpen, setPdfOpen] = useState(false);
    const [reportOpen, setReportOpen] = useState(false);
    const [lines, setLines] = useState<{ product_id: number; title: string; system: number; counted: number | '' }[]>([]);
    const report = useForm<{ note: string }>({ note: '' });

    const addLine = (r: Row) => setLines((ls) => (ls.some((l) => l.product_id === r.id) ? ls : [...ls, { product_id: r.id, title: r.title_latin, system: r.qty, counted: '' }]));
    const pending = counts.filter((c) => c.status === 'submitted');

    return (
        <ErpLayout>
            <Head title="Stok Ready" />
            <PageHeader title="Stok Ready" subtitle={`${totals.titles} judul · ${totals.units} buah di gudang Indonesia. Angka stok tidak bisa diketik — berubah hanya lewat pesanan, belanja, atau stok opname yang disetujui Owner.`}>
                <Btn onClick={() => setPdfOpen(true)}>
                    <FileDown className="size-[18px]" /> Unduh PDF
                </Btn>
                {canReport && (
                    <Btn variant="primary" onClick={() => setReportOpen(true)}>
                        <ClipboardList className="size-[18px]" /> Laporkan selisih{lines.length > 0 && ` (${lines.length})`}
                    </Btn>
                )}
            </PageHeader>

            {pending.length > 0 && (
                <section className="card !border-gold !bg-gold-soft mb-4">
                    <h3 className="mb-2 text-base font-semibold">Laporan selisih menunggu keputusan Owner ({pending.length})</h3>
                    <div className="grid gap-3">
                        {pending.map((c) => (
                            <div key={c.id} className="bg-surface rounded-xl p-3">
                                <p className="text-ink-2 text-sm">
                                    Dilaporkan {c.by} · {c.at}
                                    {c.note && ` · "${c.note}"`}
                                </p>
                                <table className="num mt-2 w-full text-sm">
                                    <thead>
                                        <tr className="text-ink-3 text-left text-[12.5px]">
                                            <th className="py-1 font-medium">Kitab</th>
                                            <th className="py-1 text-right font-medium">Sistem</th>
                                            <th className="py-1 text-right font-medium">Fisik</th>
                                            <th className="py-1 text-right font-medium">Selisih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {c.lines.map((l, i) => {
                                            const d = l.counted_qty - l.system_qty;
                                            return (
                                                <tr key={i} className="border-line border-t">
                                                    <td className="py-1.5">{l.title_latin}</td>
                                                    <td className="py-1.5 text-right">{l.system_qty}</td>
                                                    <td className="py-1.5 text-right">{l.counted_qty}</td>
                                                    <td className={'py-1.5 text-right font-medium ' + (d < 0 ? 'text-bad' : d > 0 ? 'text-ok' : 'text-ink-3')}>{d > 0 ? `+${d}` : d}</td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                                {canDecide && (
                                    <div className="mt-3 flex justify-end gap-2">
                                        <Btn size="sm" onClick={() => router.post(`/stock/counts/${c.id}/decide`, { approve: 0 }, { preserveScroll: true })}>
                                            Tolak
                                        </Btn>
                                        <Btn size="sm" variant="primary" onClick={() => router.post(`/stock/counts/${c.id}/decide`, { approve: 1 }, { preserveScroll: true })}>
                                            Setujui — samakan stok dengan fisik
                                        </Btn>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <SearchBox value={filters.q} placeholder="Cari judul atau SKU…" onSearch={(q) => applyFilters('/stock', { q, all: filters.all ? '1' : null })} />
                <label className="text-ink-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" className="size-4 accent-[var(--brand)]" checked={filters.all} onChange={(e) => applyFilters('/stock', { q: filters.q, all: e.target.checked ? '1' : null })} />
                    Tampilkan juga yang stoknya 0
                </label>
            </div>

            <Table
                head={
                    <>
                        <th>Kitab</th>
                        <th>Kategori</th>
                        <th className="text-right">Berat</th>
                        <th className="text-right">Stok</th>
                        {canReport && <th className="w-36" />}
                    </>
                }
            >
                {items.data.map((r) => (
                    <tr key={r.id}>
                        <td>
                            <div className="flex items-center gap-3">
                                {r.has_photo ? (
                                    <img src={`/products/${r.id}/photo`} alt="" loading="lazy" className="h-11 w-8 rounded object-cover" />
                                ) : (
                                    <span className="bg-soft text-ink-3 grid h-11 w-8 place-items-center rounded">
                                        <BookOpen className="size-4" />
                                    </span>
                                )}
                                <div>
                                    {r.title_ar && <span className="ar block text-left">{r.title_ar}</span>}
                                    <span className="text-ink-2 text-[13.5px]">{r.title_latin}</span>
                                    <small className="text-ink-3 block text-[12px]">{r.sku}</small>
                                </div>
                            </div>
                        </td>
                        <td className="text-ink-2">{r.category ?? '—'}</td>
                        <td className="text-right">{r.weight_grams ? `${r.weight_grams} g` : <span className="text-ink-3">—</span>}</td>
                        <td className={'font-display text-right text-lg font-semibold ' + (r.qty < 0 ? 'text-bad' : r.qty === 0 ? 'text-ink-3' : '')}>{r.qty}</td>
                        {canReport && (
                            <td className="text-right">
                                <Btn size="sm" variant="ghost" onClick={() => addLine(r)} disabled={lines.some((l) => l.product_id === r.id)}>
                                    {lines.some((l) => l.product_id === r.id) ? 'Ditambahkan' : 'Hitungan beda'}
                                </Btn>
                            </td>
                        )}
                    </tr>
                ))}
            </Table>
            {items.data.length === 0 && <p className="text-ink-3 py-10 text-center text-sm">Tidak ada kitab dengan stok.</p>}
            <Pager page={items} />

            <Modal open={pdfOpen} onClose={() => setPdfOpen(false)} title="Unduh daftar stok (PDF)" description="Pilih kolom yang ingin dicetak. Cocok dibawa saat menghitung fisik.">
                <div className="grid grid-cols-2 gap-2">
                    {COLS.map((c) => (
                        <label key={c.key} className="border-line flex items-center gap-2 rounded-lg border p-2.5 text-sm">
                            <input type="checkbox" className="size-4 accent-[var(--brand)]" checked={cols.includes(c.key)} onChange={(e) => setCols(e.target.checked ? [...cols, c.key] : cols.filter((x) => x !== c.key))} />
                            {c.label}
                        </label>
                    ))}
                </div>
                <div className="flex justify-end gap-2">
                    <Btn onClick={() => setPdfOpen(false)}>Batal</Btn>
                    <a
                        className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white"
                        href={`/stock/print?${new URLSearchParams([...(filters.q ? [['q', filters.q]] : []), ...cols.map((c) => ['cols[]', c])]).toString()}`}
                        target="_blank"
                        rel="noopener"
                    >
                        Buka versi cetak
                    </a>
                </div>
            </Modal>

            <Modal open={reportOpen} onClose={() => setReportOpen(false)} title="Laporkan selisih stok" description="Isi hasil hitung fisik untuk kitab yang angkanya beda. Owner yang memutuskan; stok tidak berubah sebelum disetujui.">
                {lines.length === 0 ? (
                    <p className="text-ink-2 text-sm">Tekan “Hitungan beda” di baris kitab pada tabel untuk menambahkannya ke laporan.</p>
                ) : (
                    <div className="grid gap-2">
                        {lines.map((l) => (
                            <div key={l.product_id} className="border-line flex items-center gap-3 rounded-lg border p-2.5">
                                <span className="flex-1 text-sm">
                                    {l.title}
                                    <small className="text-ink-3 block">Sistem: {l.system}</small>
                                </span>
                                <input
                                    className={inputCls + ' num !h-10 w-24 text-center'}
                                    inputMode="numeric"
                                    placeholder="fisik"
                                    value={l.counted}
                                    onChange={(e) => setLines((ls) => ls.map((x) => (x.product_id === l.product_id ? { ...x, counted: e.target.value.replace(/\D/g, '') === '' ? '' : Number(e.target.value.replace(/\D/g, '')) } : x)))}
                                />
                                <Btn size="sm" variant="ghost" onClick={() => setLines((ls) => ls.filter((x) => x.product_id !== l.product_id))} aria-label="Hapus">
                                    <Trash2 className="size-4" />
                                </Btn>
                            </div>
                        ))}
                    </div>
                )}
                <Field label="Catatan (opsional)">
                    <input className={inputCls} value={report.data.note} onChange={(e) => report.setData('note', e.target.value)} placeholder="mis. dihitung ulang dua kali" />
                </Field>
                {Object.values(report.errors)[0] && <p className="text-bad text-[13px]">{Object.values(report.errors)[0]}</p>}
                <div className="flex justify-end gap-2">
                    <Btn onClick={() => setReportOpen(false)}>Tutup</Btn>
                    <Btn
                        variant="primary"
                        disabled={lines.length === 0 || lines.some((l) => l.counted === '') || report.processing}
                        onClick={() => {
                            report.transform((d) => ({ ...d, lines: lines.map((l) => ({ product_id: l.product_id, counted_qty: l.counted })) }));
                            report.post('/stock/counts', {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setLines([]);
                                    report.reset();
                                    setReportOpen(false);
                                },
                            });
                        }}
                    >
                        Kirim ke Owner
                    </Btn>
                </div>
            </Modal>
        </ErpLayout>
    );
}
