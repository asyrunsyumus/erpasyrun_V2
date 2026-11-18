import { applyFilters, Btn, ConfirmDelete, EmptyState, ErrorBanner, Field, inputCls, Modal, MoneyInput, PageHeader, Pager, type Paginated, SearchBox, Segmented, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BookOpen, ImagePlus, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Product {
    id: number;
    sku: string;
    title_ar: string | null;
    title_latin: string;
    author: string | null;
    publisher: string | null;
    pentahqiq: string | null;
    category_id: number | null;
    category: string | null;
    weight_grams: number | null;
    primary_maktabah_id: number | null;
    primary_maktabah: string | null;
    alt_maktabah_ids: number[];
    is_active: boolean;
    has_photo: boolean;
    stock: number;
    price_regular: number;
    price_reseller: number;
    price_egp?: number; // hanya dikirim untuk Owner
}
interface Opt {
    id: number;
    name: string;
}
interface Props {
    items: Paginated<Product>;
    filters: { q: string; status: string };
    isOwner: boolean;
    categories: Opt[];
    maktabahs: Opt[];
    defaultRate: number;
}

type Form = {
    title_ar: string;
    title_latin: string;
    author: string;
    publisher: string;
    pentahqiq: string;
    category: string;
    weight_grams: number | '';
    primary_maktabah_id: number | '';
    alt_maktabah_ids: number[];
    is_active: boolean;
    price_egp: number | '';
    price_regular: number | '';
    price_reseller: number | '';
    photo: File | null;
};

function Cover({ p }: { p: Pick<Product, 'id' | 'has_photo'> }) {
    return p.has_photo ? (
        <img src={`/products/${p.id}/photo`} alt="" className="bg-soft h-12 w-9 shrink-0 rounded-[5px] object-cover" loading="lazy" />
    ) : (
        <span className="bg-soft text-ink-3 grid h-12 w-9 shrink-0 place-items-center rounded-[5px]">
            <BookOpen className="size-4" />
        </span>
    );
}

/** Kalkulator untung per kitab (Owner). Kurs & ongkos bagasi bisa dicoba-coba di sini tanpa disimpan. */
function ProfitCalc({ egpPiaster, weight, regular, reseller, defaultRate }: { egpPiaster: number; weight: number; regular: number; reseller: number; defaultRate: number }) {
    const [rate, setRate] = useState<number | ''>(defaultRate ? Math.round(defaultRate / 10000) : '');
    const [bagPerKg, setBagPerKg] = useState<number | ''>('');
    const buy = (egpPiaster / 100) * Number(rate || 0);
    const bag = (weight / 1000) * Number(bagPerKg || 0);
    const cost = buy + bag;
    const row = (label: string, price: number) => {
        const profit = price - cost;
        const pct = price > 0 ? Math.round((profit / price) * 100) : 0;
        return (
            <div className="flex justify-between py-1">
                <span className="text-ink-2">{label}</span>
                <b className={'font-medium ' + (price === 0 ? 'text-ink-3' : profit < 0 ? 'text-bad' : 'text-ok')}>{price === 0 ? '—' : `${rupiah(profit)} (${pct}%)`}</b>
            </div>
        );
    };
    return (
        <div className="bg-soft num rounded-xl p-4 text-sm">
            <p className="mb-3 font-medium">Perkiraan untung per kitab</p>
            <div className="mb-3 grid grid-cols-2 gap-3">
                <Field label="Kurs (Rp per EGP)">
                    <MoneyInput value={rate} onChange={setRate} />
                </Field>
                <Field label="Ongkos bagasi per kg">
                    <MoneyInput value={bagPerKg} onChange={setBagPerKg} />
                </Field>
            </div>
            <div className="flex justify-between py-1">
                <span className="text-ink-2">Harga beli</span>
                <span>{rupiah(buy)}</span>
            </div>
            <div className="flex justify-between py-1">
                <span className="text-ink-2">Ongkos bagasi ({weight || 0} g)</span>
                <span>{rupiah(bag)}</span>
            </div>
            <div className="border-line mb-1 flex justify-between border-b py-1 pb-2">
                <span className="font-medium">Modal per kitab</span>
                <b className="font-semibold">{rupiah(cost)}</b>
            </div>
            {row('Untung harga reguler', regular)}
            {row('Untung harga reseller', reseller)}
        </div>
    );
}

export default function Products({ items, filters, isOwner, categories, maktabahs, defaultRate }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [editing, setEditing] = useState<Product | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Product | null>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const blank: Form = { title_ar: '', title_latin: '', author: '', publisher: '', pentahqiq: '', category: '', weight_grams: '', primary_maktabah_id: '', alt_maktabah_ids: [], is_active: true, price_egp: '', price_regular: '', price_reseller: '', photo: null };
    const form = useForm<Form>(blank);
    const [egpText, setEgpText] = useState('');

    const open = (p: Product | 'new') => {
        form.clearErrors();
        setPreview(null);
        if (p === 'new') {
            form.setData(blank);
            setEgpText('');
        } else {
            form.setData({
                title_ar: p.title_ar ?? '',
                title_latin: p.title_latin,
                author: p.author ?? '',
                publisher: p.publisher ?? '',
                pentahqiq: p.pentahqiq ?? '',
                category: p.category ?? '',
                weight_grams: p.weight_grams ?? '',
                primary_maktabah_id: p.primary_maktabah_id ?? '',
                alt_maktabah_ids: p.alt_maktabah_ids,
                is_active: p.is_active,
                price_egp: p.price_egp ?? '',
                price_regular: p.price_regular,
                price_reseller: p.price_reseller,
                photo: null,
            });
            setEgpText(p.price_egp != null ? (p.price_egp / 100).toString().replace('.', ',') : '');
        }
        setEditing(p);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, primary_maktabah_id: d.primary_maktabah_id || null, is_active: d.is_active ? 1 : 0 }));
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? form.post('/products', opts) : form.post(`/products/${(editing as Product).id}`, opts);
    };

    const altOptions = useMemo(() => maktabahs.filter((m) => m.id !== form.data.primary_maktabah_id), [maktabahs, form.data.primary_maktabah_id]);

    return (
        <ErpLayout>
            <Head title="Produk (Kitab)" />
            <PageHeader title="Produk (Kitab)" subtitle={isOwner ? 'Data kitab, harga, dan toko tempat membelinya.' : 'Harga jual diatur oleh Owner. Anda bisa menambah kitab dan mengubah keterangannya.'}>
                <Btn onClick={() => router.visit('/imports?type=products')}>
                    <Upload className="size-[18px]" /> Import
                </Btn>
                <Btn variant="primary" onClick={() => open('new')}>
                    <Plus className="size-[18px]" /> Tambah kitab
                </Btn>
            </PageHeader>

            <ErrorBanner message={errors.delete} />

            <div className="mb-4 flex flex-wrap gap-3">
                <SearchBox value={filters.q} placeholder="Cari judul (Latin/Arab), penulis, atau SKU…" onSearch={(q) => applyFilters('/products', { q, status: filters.status })} />
                <Segmented
                    value={filters.status as 'aktif' | 'nonaktif' | 'semua'}
                    options={[
                        { value: 'aktif', label: 'Aktif' },
                        { value: 'nonaktif', label: 'Nonaktif' },
                        { value: 'semua', label: 'Semua' },
                    ]}
                    onChange={(s) => applyFilters('/products', { q: filters.q, status: s })}
                />
            </div>

            <Table
                head={
                    <>
                        <th>Kitab</th>
                        <th>Maktabah utama</th>
                        <th className="text-right">Berat</th>
                        <th className="text-right">Stok</th>
                        <th className="text-right">Reguler</th>
                        <th className="text-right">Reseller</th>
                        <th className="w-24" />
                    </>
                }
                empty={items.data.length === 0 && <EmptyState title={filters.q ? 'Tidak ditemukan.' : 'Belum ada kitab.'}>{!filters.q && 'Tambahkan satu per satu, atau import dari Excel.'}</EmptyState>}
            >
                {items.data.map((p) => (
                    <tr key={p.id} className={p.is_active ? '' : 'opacity-60'}>
                        <td>
                            <div className="flex items-center gap-3">
                                <Cover p={p} />
                                <div className="min-w-0">
                                    {p.title_ar && <span className="ar block text-left text-base">{p.title_ar}</span>}
                                    <span className={p.title_ar ? 'text-ink-2 text-[13.5px]' : 'font-medium'}>{p.title_latin}</span>
                                    <small className="text-ink-3 block text-[12.5px]">
                                        {p.sku}
                                        {p.author && ` · ${p.author}`}
                                        {!p.is_active && ' · nonaktif'}
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td className="text-ink-2">{p.primary_maktabah || '—'}</td>
                        <td className="text-right">{p.weight_grams ? `${p.weight_grams} g` : <span className="text-warn text-[13px]">belum diisi</span>}</td>
                        <td className={'text-right ' + (p.stock > 0 ? '' : 'text-ink-3')}>{p.stock}</td>
                        <td className="text-right">{p.price_regular ? rupiah(p.price_regular) : <span className="text-warn text-[13px]">belum diisi</span>}</td>
                        <td className="text-right">{p.price_reseller ? rupiah(p.price_reseller) : <span className="text-warn text-[13px]">belum diisi</span>}</td>
                        <td className="text-right whitespace-nowrap">
                            <Btn size="sm" variant="ghost" onClick={() => open(p)} aria-label="Ubah">
                                <Pencil className="size-4" />
                            </Btn>
                            {isOwner && (
                                <Btn size="sm" variant="ghost" onClick={() => setDeleting(p)} aria-label="Hapus">
                                    <Trash2 className="size-4" />
                                </Btn>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            <Pager page={items} />

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Tambah kitab' : 'Ubah kitab'} wide>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="flex gap-4">
                        <label className="border-line hover:border-brand bg-soft text-ink-3 grid h-[104px] w-[78px] shrink-0 cursor-pointer place-items-center overflow-hidden rounded-lg border border-dashed">
                            {preview ? (
                                <img src={preview} alt="" className="h-full w-full object-cover" />
                            ) : editing !== 'new' && editing?.has_photo ? (
                                <img src={`/products/${editing.id}/photo`} alt="" className="h-full w-full object-cover" />
                            ) : (
                                <span className="flex flex-col items-center gap-1 text-[11px]">
                                    <ImagePlus className="size-5" />
                                    Foto
                                </span>
                            )}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="hidden"
                                onChange={(e) => {
                                    const f = e.target.files?.[0] ?? null;
                                    form.setData('photo', f);
                                    setPreview(f ? URL.createObjectURL(f) : null);
                                }}
                            />
                        </label>
                        <div className="grid flex-1 gap-4">
                            <Field label="Judul (Latin)" error={form.errors.title_latin}>
                                <input className={inputCls} value={form.data.title_latin} onChange={(e) => form.setData('title_latin', e.target.value)} required placeholder="mis. Fathul Qarib" />
                            </Field>
                            <Field label="Judul (Arab)" error={form.errors.title_ar}>
                                <input dir="rtl" className={inputCls + ' font-arabic text-lg'} value={form.data.title_ar} onChange={(e) => form.setData('title_ar', e.target.value)} placeholder="فتح القريب المجيب" />
                            </Field>
                        </div>
                    </div>
                    {form.errors.photo && <p className="text-bad -mt-2 text-[13px]">{form.errors.photo}</p>}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Penulis" error={form.errors.author}>
                            <input className={inputCls} value={form.data.author} onChange={(e) => form.setData('author', e.target.value)} />
                        </Field>
                        <Field label="Penerbit" error={form.errors.publisher}>
                            <input className={inputCls} value={form.data.publisher} onChange={(e) => form.setData('publisher', e.target.value)} />
                        </Field>
                        <Field label="Pentahqiq" error={form.errors.pentahqiq} hint="Nama muhaqqiq (penyunting) kitab, bila ada.">
                            <input className={inputCls} value={form.data.pentahqiq} onChange={(e) => form.setData('pentahqiq', e.target.value)} />
                        </Field>
                        <Field label="Kategori" error={form.errors.category} hint="Ketik baru atau pilih yang sudah ada.">
                            <input className={inputCls} list="cat-list" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                            <datalist id="cat-list">
                                {categories.map((c) => (
                                    <option key={c.id} value={c.name} />
                                ))}
                            </datalist>
                        </Field>
                        <Field label="Berat (gram)" error={form.errors.weight_grams} hint="Boleh kosong bila belum tahu — nanti diminta saat kitab ini pertama kali dipesan.">
                            <input
                                className={inputCls + ' num'}
                                inputMode="numeric"
                                placeholder="belum diketahui"
                                value={form.data.weight_grams}
                                onChange={(e) => form.setData('weight_grams', e.target.value.replace(/\D/g, '') === '' ? '' : Number(e.target.value.replace(/\D/g, '')))}
                            />
                        </Field>
                        <Field label="Maktabah utama" error={form.errors.primary_maktabah_id}>
                            <select className={inputCls} value={form.data.primary_maktabah_id} onChange={(e) => form.setData('primary_maktabah_id', e.target.value ? Number(e.target.value) : '')}>
                                <option value="">— belum ditentukan —</option>
                                {maktabahs.map((m) => (
                                    <option key={m.id} value={m.id}>
                                        {m.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Toko cadangan" hint="Muncul di daftar belanja Tim Mesir bila toko utama kehabisan.">
                            <div className="border-input flex max-h-28 flex-wrap gap-1.5 overflow-y-auto rounded-[10px] border p-2">
                                {altOptions.length === 0 && <span className="text-ink-3 text-sm">Belum ada maktabah lain.</span>}
                                {altOptions.map((m) => {
                                    const on = form.data.alt_maktabah_ids.includes(m.id);
                                    return (
                                        <button
                                            type="button"
                                            key={m.id}
                                            onClick={() => form.setData('alt_maktabah_ids', on ? form.data.alt_maktabah_ids.filter((x) => x !== m.id) : [...form.data.alt_maktabah_ids, m.id])}
                                            className={'rounded-full border px-2.5 py-1 text-[13px] ' + (on ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2')}
                                        >
                                            {m.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>
                    </div>

                    {isOwner ? (
                        <div className="grid gap-4 sm:grid-cols-[1fr_1fr]">
                            <div className="grid content-start gap-4">
                                <Field label="Harga beli (EGP)" error={form.errors.price_egp}>
                                    <div className={inputCls + ' flex items-center gap-2'}>
                                        <span className="text-ink-3 text-sm">EGP</span>
                                        <input
                                            inputMode="decimal"
                                            className="num w-full bg-transparent outline-none"
                                            value={egpText}
                                            onChange={(e) => {
                                                const t = e.target.value.replace(/[^0-9.,]/g, '');
                                                setEgpText(t);
                                                const n = parseFloat(t.replace(',', '.'));
                                                form.setData('price_egp', isNaN(n) ? '' : Math.round(n * 100));
                                            }}
                                        />
                                    </div>
                                </Field>
                                <Field label="Harga jual reguler" error={form.errors.price_regular}>
                                    <MoneyInput value={form.data.price_regular} onChange={(v) => form.setData('price_regular', v)} />
                                </Field>
                                <Field label="Harga jual reseller" error={form.errors.price_reseller}>
                                    <MoneyInput value={form.data.price_reseller} onChange={(v) => form.setData('price_reseller', v)} />
                                </Field>
                            </div>
                            <ProfitCalc
                                egpPiaster={Number(form.data.price_egp || 0)}
                                weight={Number(form.data.weight_grams || 0)}
                                regular={Number(form.data.price_regular || 0)}
                                reseller={Number(form.data.price_reseller || 0)}
                                defaultRate={defaultRate}
                            />
                        </div>
                    ) : (
                        editing !== 'new' &&
                        editing && (
                            <div className="bg-soft text-ink-2 rounded-xl p-4 text-sm">
                                Harga reguler <b className="text-ink num">{editing.price_regular ? rupiah(editing.price_regular) : 'belum diisi'}</b> · reseller{' '}
                                <b className="text-ink num">{editing.price_reseller ? rupiah(editing.price_reseller) : 'belum diisi'}</b>. Harga hanya bisa diubah Owner.
                            </div>
                        )
                    )}

                    <label className="flex items-center gap-2.5 text-sm">
                        <input type="checkbox" className="accent-[var(--brand)] size-4" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
                        Aktif (bisa dipilih saat membuat order)
                    </label>

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
                title="Hapus kitab?"
                message={<>Kitab <b>{deleting?.title_latin}</b> akan dihapus. Bila sudah pernah dipesan, sistem akan menolak — nonaktifkan saja.</>}
                onConfirm={() => deleting && router.delete(`/products/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
