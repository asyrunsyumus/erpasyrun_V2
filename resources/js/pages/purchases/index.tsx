import { Btn, Field, inputCls, Modal, PageHeader, Segmented } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { BookOpen, FileDown, Pencil, Store } from 'lucide-react';
import { useState } from 'react';

interface NeedItem {
    product_id: number;
    sku: string;
    title_latin: string;
    title_ar: string | null;
    author: string | null;
    has_photo: boolean;
    price_egp: number;
    weight_grams: number;
    maktabah_id: number | null;
    maktabah: string | null;
    alternates: string[];
    qty: number;
    orders: number;
    waiting_days: number;
}
interface Group {
    maktabah: string;
    items: NeedItem[];
    total_qty: number;
}
interface HistoryRow {
    id: number;
    product_id: number;
    title_latin: string;
    title_ar: string | null;
    maktabah_id: number;
    maktabah: string;
    qty: number;
    price_egp: number;
    rate: number;
    cost_idr: number | null;
    purchased_at: string;
    by: string;
    corrected: boolean;
}
interface Props {
    groups: Group[];
    history: HistoryRow[];
    maktabahs: { id: number; name: string }[];
    defaultRate: number;
    isOwner: boolean;
    today: string;
}

/* Satuan: harga EGP disimpan dalam piaster (×100), kurs disimpan ×10.000. */
const egpToText = (p: number) => (p ? (p / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 }) : '');
const rateToText = (r: number) => (r ? (r / 10000).toLocaleString('id-ID', { maximumFractionDigits: 4 }) : '');
const parseDec = (t: string) => {
    // "1.234,5" atau "1234.5" → 1234.5
    const s = t.trim();
    if (!s) return NaN;
    const normalized = s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s;
    return parseFloat(normalized);
};

function Photo({ id, has, size = 'lg' }: { id: number; has: boolean; size?: 'lg' | 'sm' }) {
    const cls = size === 'lg' ? 'h-[92px] w-[68px]' : 'h-11 w-8';
    return has ? (
        <img src={`/products/${id}/photo`} alt="" loading="lazy" className={`${cls} bg-soft shrink-0 rounded-md object-cover`} />
    ) : (
        <span className={`${cls} bg-soft text-ink-3 grid shrink-0 place-items-center rounded-md`}>
            <BookOpen className="size-5" />
        </span>
    );
}

type BuyForm = { product_id: number; maktabah_id: number | ''; qty: number | ''; price_egp: number | ''; rate: number | ''; purchased_at: string };

export default function Purchases({ groups, history, maktabahs, defaultRate, isOwner, today }: Props) {
    const [tab, setTab] = useState<'daftar' | 'riwayat'>('daftar');
    const [buying, setBuying] = useState<{ item?: NeedItem; history?: HistoryRow } | null>(null);
    const [egpText, setEgpText] = useState('');
    const [rateText, setRateText] = useState('');
    const form = useForm<BuyForm>({ product_id: 0, maktabah_id: '', qty: '', price_egp: '', rate: '', purchased_at: today });
    const rateForm = useForm({ rate: defaultRate });
    const [rateEdit, setRateEdit] = useState(rateToText(defaultRate));

    const openBuy = (item: NeedItem) => {
        form.clearErrors();
        form.setData({ product_id: item.product_id, maktabah_id: item.maktabah_id ?? '', qty: item.qty, price_egp: item.price_egp || '', rate: defaultRate || '', purchased_at: today });
        setEgpText(egpToText(item.price_egp));
        setRateText(rateToText(defaultRate));
        setBuying({ item });
    };
    const openCorrect = (h: HistoryRow) => {
        form.clearErrors();
        form.setData({ product_id: h.product_id, maktabah_id: h.maktabah_id, qty: h.qty, price_egp: h.price_egp, rate: h.rate, purchased_at: h.purchased_at });
        setEgpText(egpToText(h.price_egp));
        setRateText(rateToText(h.rate));
        setBuying({ history: h });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setBuying(null) };
        buying?.history ? form.put(`/purchases/${buying.history.id}`, opts) : form.post('/purchases', opts);
    };

    const estIdr = Number(form.data.qty || 0) * (Number(form.data.price_egp || 0) / 100) * (Number(form.data.rate || 0) / 10000);
    const totalItems = groups.reduce((s, g) => s + g.items.length, 0);
    const title = buying?.item ?? buying?.history;
    const priceDiffers = buying?.item && buying.item.price_egp > 0 && form.data.price_egp !== '' && form.data.price_egp !== buying.item.price_egp;

    return (
        <ErpLayout>
            <Head title="Belanja Mesir" />
            <PageHeader
                title="Belanja Mesir"
                subtitle={isOwner ? 'Kitab yang perlu dibeli di Mesir, dikelompokkan per maktabah.' : 'Belanja sesuai daftar, lalu catat yang benar-benar Anda beli — satu per satu.'}
            >
                <a href="/purchases/print" target="_blank" rel="noopener" className="border-line hover:border-ink-3 inline-flex h-[42px] items-center gap-2 rounded-[10px] border px-[18px] font-medium">
                    <FileDown className="size-[18px]" /> Export PDF
                </a>
            </PageHeader>

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Segmented
                    value={tab}
                    options={[
                        { value: 'daftar', label: `Daftar belanja (${totalItems})` },
                        { value: 'riwayat', label: 'Riwayat' },
                    ]}
                    onChange={setTab}
                />
                {isOwner && (
                    <form
                        className="ml-auto flex items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();
                            rateForm.post('/purchases/rate', { preserveScroll: true });
                        }}
                    >
                        <span className="text-ink-2 text-sm">Kurs default</span>
                        <div className={inputCls + ' flex !h-[42px] w-40 items-center gap-1.5'}>
                            <span className="text-ink-3 text-sm">Rp</span>
                            <input
                                className="num w-full bg-transparent outline-none"
                                inputMode="decimal"
                                value={rateEdit}
                                onChange={(e) => {
                                    setRateEdit(e.target.value);
                                    const n = parseDec(e.target.value);
                                    rateForm.setData('rate', isNaN(n) ? 0 : Math.round(n * 10000));
                                }}
                            />
                        </div>
                        <Btn size="sm" disabled={rateForm.processing || rateForm.data.rate === defaultRate}>
                            Simpan
                        </Btn>
                    </form>
                )}
            </div>

            {tab === 'daftar' &&
                (groups.length === 0 ? (
                    <div className="card py-14 text-center">
                        <p className="font-medium">Tidak ada yang perlu dibeli.</p>
                        <p className="text-ink-3 mt-1 text-sm">Daftar akan terisi otomatis saat ada pesanan yang sudah dibayar atau dibelikan dulu.</p>
                    </div>
                ) : (
                    <div className="grid gap-5">
                        {groups.map((g) => (
                            <section key={g.maktabah}>
                                <div className="mb-2.5 flex items-center gap-2">
                                    <Store className="text-brand size-5" />
                                    <h2 className="font-display text-lg font-semibold">{g.maktabah}</h2>
                                    <span className="text-ink-3 text-sm">
                                        · {g.items.length} judul, {g.total_qty} buah
                                    </span>
                                </div>
                                <div className="grid gap-2.5 md:grid-cols-2">
                                    {g.items.map((it) => (
                                        <article key={it.product_id} className="card flex gap-3.5 !p-3.5">
                                            <Photo id={it.product_id} has={it.has_photo} />
                                            <div className="flex min-w-0 flex-1 flex-col">
                                                {it.title_ar && <span className="ar block text-left text-[17px]">{it.title_ar}</span>}
                                                <span className="text-ink-2 text-[14px]">{it.title_latin}</span>
                                                {it.author && <span className="text-ink-3 text-[12.5px]">{it.author}</span>}
                                                {it.alternates.length > 0 && <span className="text-ink-3 mt-1 text-[12.5px]">Toko cadangan: {it.alternates.join(', ')}</span>}
                                                {it.waiting_days >= 3 && <span className="text-warn mt-1 text-[12.5px] font-medium">Menunggu {it.waiting_days} hari</span>}
                                                <div className="mt-auto flex items-end gap-3 pt-2">
                                                    <div>
                                                        <span className="font-display num text-[26px] leading-none font-semibold">{it.qty}</span>
                                                        <span className="text-ink-3 ml-1 text-sm">buah</span>
                                                    </div>
                                                    <Btn variant="primary" className="ml-auto !h-11 !px-6" onClick={() => openBuy(it)}>
                                                        Beli
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

            {tab === 'riwayat' && (
                <div className="card overflow-x-auto !p-0">
                    <table className="num w-full min-w-[640px]">
                        <thead>
                            <tr className="text-ink-3 border-line border-b text-left text-[13px] [&>th]:px-4 [&>th]:py-3 [&>th]:font-medium">
                                <th>Tanggal</th>
                                <th>Kitab</th>
                                <th>Maktabah</th>
                                <th className="text-right">Jumlah</th>
                                <th className="text-right">Harga EGP</th>
                                <th className="text-right">Kurs</th>
                                {isOwner && <th className="text-right">Total Rp</th>}
                                {isOwner && <th />}
                            </tr>
                        </thead>
                        <tbody>
                            {history.map((h) => (
                                <tr key={h.id} className="border-line border-b last:border-0 [&>td]:px-4 [&>td]:py-3">
                                    <td className="text-ink-2 text-sm">
                                        {h.purchased_at}
                                        <small className="text-ink-3 block">
                                            {h.by}
                                            {h.corrected && ' · dikoreksi'}
                                        </small>
                                    </td>
                                    <td>
                                        {h.title_ar && <span className="ar block text-left">{h.title_ar}</span>}
                                        <small className="text-ink-3 text-[13px]">{h.title_latin}</small>
                                    </td>
                                    <td className="text-ink-2">{h.maktabah}</td>
                                    <td className="text-right">{h.qty}</td>
                                    <td className="text-right">{egpToText(h.price_egp)}</td>
                                    <td className="text-right">{rateToText(h.rate)}</td>
                                    {isOwner && <td className="text-right">{rupiah(h.cost_idr ?? 0)}</td>}
                                    {isOwner && (
                                        <td className="text-right">
                                            <Btn size="sm" variant="ghost" onClick={() => openCorrect(h)} aria-label="Koreksi">
                                                <Pencil className="size-4" />
                                            </Btn>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {history.length === 0 && <p className="text-ink-3 py-12 text-center text-sm">Belum ada catatan belanja.</p>}
                    {!isOwner && history.length > 0 && <p className="text-ink-3 border-line border-t px-4 py-3 text-[13px]">Catatan yang sudah disimpan tidak bisa diubah. Bila salah ketik, hubungi Owner.</p>}
                </div>
            )}

            <Modal
                open={buying !== null}
                onClose={() => setBuying(null)}
                title={buying?.history ? 'Koreksi catatan belanja' : 'Catat belanja'}
                description={buying?.history ? 'Stok dan status pesanan akan dihitung ulang otomatis.' : 'Isi sesuai yang benar-benar Anda beli hari ini.'}
            >
                {title && (
                    <div className="bg-soft mb-1 flex items-center gap-3 rounded-xl p-3">
                        <Photo id={title.product_id} has={buying?.item?.has_photo ?? false} size="sm" />
                        <div>
                            {title.title_ar && <span className="ar block text-left">{title.title_ar}</span>}
                            <span className="text-ink-2 text-sm">{title.title_latin}</span>
                            {buying?.item && <span className="text-ink-3 block text-[12.5px]">Dibutuhkan {buying.item.qty} buah</span>}
                        </div>
                    </div>
                )}
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Maktabah tempat membeli" error={form.errors.maktabah_id} hint="Kalau membeli di toko lain karena toko utama kehabisan, ganti di sini.">
                        <select className={inputCls} value={form.data.maktabah_id} onChange={(e) => form.setData('maktabah_id', e.target.value ? Number(e.target.value) : '')} required>
                            <option value="">— pilih —</option>
                            {maktabahs.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Jumlah dibeli" error={form.errors.qty} hint="Yang benar-benar didapat.">
                            <input
                                className={inputCls + ' num text-lg'}
                                inputMode="numeric"
                                value={form.data.qty}
                                onChange={(e) => form.setData('qty', e.target.value.replace(/\D/g, '') === '' ? '' : Number(e.target.value.replace(/\D/g, '')))}
                                required
                            />
                        </Field>
                        <Field label="Harga satuan (EGP)" error={form.errors.price_egp}>
                            <input
                                className={inputCls + ' num text-lg'}
                                inputMode="decimal"
                                value={egpText}
                                onChange={(e) => {
                                    setEgpText(e.target.value);
                                    const n = parseDec(e.target.value);
                                    form.setData('price_egp', isNaN(n) ? '' : Math.round(n * 100));
                                }}
                                required
                            />
                        </Field>
                        <Field label="Kurs (Rp per 1 EGP)" error={form.errors.rate}>
                            <input
                                className={inputCls + ' num'}
                                inputMode="decimal"
                                value={rateText}
                                onChange={(e) => {
                                    setRateText(e.target.value);
                                    const n = parseDec(e.target.value);
                                    form.setData('rate', isNaN(n) ? '' : Math.round(n * 10000));
                                }}
                                required
                            />
                        </Field>
                        <Field label="Tanggal beli" error={form.errors.purchased_at}>
                            <input type="date" className={inputCls} max={today} value={form.data.purchased_at} onChange={(e) => form.setData('purchased_at', e.target.value)} required />
                        </Field>
                    </div>

                    <div className="bg-soft num text-ink-2 rounded-xl p-3 text-sm">
                        Total ≈ <b className="text-ink">EGP {(Number(form.data.qty || 0) * (Number(form.data.price_egp || 0) / 100)).toLocaleString('id-ID', { maximumFractionDigits: 2 })}</b> ·{' '}
                        <b className="text-ink">{rupiah(estIdr)}</b>
                        {buying?.item && Number(form.data.qty || 0) > buying.item.qty && <span className="block pt-1">Kelebihan {Number(form.data.qty) - buying.item.qty} buah akan masuk stok Indonesia.</span>}
                        {buying?.item && Number(form.data.qty || 0) > 0 && Number(form.data.qty) < buying.item.qty && (
                            <span className="text-warn block pt-1">Masih kurang {buying.item.qty - Number(form.data.qty)} buah — tetap di daftar, Owner diberi tahu otomatis.</span>
                        )}
                        {priceDiffers && <span className="text-gold-ink block pt-1">Harga berbeda dari yang tersimpan (EGP {egpToText(buying!.item!.price_egp)}) — harga di Master Produk akan diperbarui.</span>}
                    </div>

                    {!isOwner && <p className="text-ink-3 -mt-1 text-[12.5px]">Periksa dua kali sebelum menyimpan. Setelah disimpan, hanya Owner yang bisa membetulkan.</p>}

                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setBuying(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" disabled={form.processing}>
                            {buying?.history ? 'Simpan koreksi' : 'Simpan'}
                        </Btn>
                    </div>
                </form>
            </Modal>
        </ErpLayout>
    );
}
