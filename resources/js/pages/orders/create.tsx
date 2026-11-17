import { Btn, Field, inputCls, MoneyInput, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { kg, rupiah } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, Minus, Plus, Search, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface CustomerOpt {
    id: number;
    code: string;
    name: string;
    type: 'reguler' | 'reseller';
    city: string | null;
    phone: string | null;
}
interface ProductOpt {
    id: number;
    sku: string;
    title_latin: string;
    title_ar: string | null;
    has_photo: boolean;
    weight_grams: number;
    price: number;
    stock: number;
}
interface Line extends ProductOpt {
    qty: number;
}

function useLookup<T>(url: string, q: string, extra = '') {
    const [data, setData] = useState<T[]>([]);
    useEffect(() => {
        const ctrl = new AbortController();
        const t = setTimeout(() => {
            fetch(`${url}?q=${encodeURIComponent(q)}${extra}`, { signal: ctrl.signal, headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then(setData)
                .catch(() => {});
        }, 200);
        return () => {
            clearTimeout(t);
            ctrl.abort();
        };
    }, [url, q, extra]);
    return data;
}

/** Kotak pencarian dengan daftar pilihan di bawahnya. */
function Picker<T>({ placeholder, url, extra, render, onPick, autoFocus }: { placeholder: string; url: string; extra?: string; render: (x: T) => React.ReactNode; onPick: (x: T) => void; autoFocus?: boolean }) {
    const [q, setQ] = useState('');
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    const list = useLookup<T>(url, q, extra);
    useEffect(() => {
        const h = (e: MouseEvent) => !box.current?.contains(e.target as Node) && setOpen(false);
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);
    return (
        <div ref={box} className="relative">
            <label className={inputCls + ' flex items-center gap-2'}>
                <Search className="text-ink-3 size-[18px]" />
                <input
                    className="w-full bg-transparent outline-none"
                    placeholder={placeholder}
                    value={q}
                    autoFocus={autoFocus}
                    onFocus={() => setOpen(true)}
                    onChange={(e) => {
                        setQ(e.target.value);
                        setOpen(true);
                    }}
                />
            </label>
            {open && (
                <div className="bg-surface border-line absolute inset-x-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-xl border shadow-lg">
                    {list.length === 0 ? (
                        <p className="text-ink-3 px-4 py-3 text-sm">Tidak ditemukan.</p>
                    ) : (
                        list.map((x, i) => (
                            <button
                                key={i}
                                type="button"
                                className="hover:bg-soft border-line block w-full border-b px-4 py-2.5 text-left last:border-0"
                                onClick={() => {
                                    onPick(x);
                                    setQ('');
                                    setOpen(false);
                                }}
                            >
                                {render(x)}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

type Pay = 'unpaid' | 'trusted' | 'paid';
const PAY: { value: Pay; title: string; desc: string }[] = [
    { value: 'unpaid', title: 'Belum bayar', desc: 'Ditahan dulu. Belum masuk daftar belanja Tim Mesir.' },
    { value: 'trusted', title: 'Belum bayar (dibelikan dulu)', desc: 'Pembeli dipercaya. Langsung masuk daftar belanja.' },
    { value: 'paid', title: 'Lunas', desc: 'Uang sudah masuk. Langsung masuk daftar belanja.' },
];

export default function CreateOrder() {
    const [customer, setCustomer] = useState<CustomerOpt | null>(null);
    const [lines, setLines] = useState<Line[]>([]);
    const form = useForm<{ customer_id: number | ''; channel: 'whatsapp' | 'shopee'; payment_status: Pay | ''; shipping_fee: number | ''; discount: number | ''; notes: string; items: { product_id: number; qty: number }[] }>({
        customer_id: '',
        channel: 'whatsapp',
        payment_status: '',
        shipping_fee: '',
        discount: '',
        notes: '',
        items: [],
    });

    // Harga mengikuti tipe pembeli: bila pembeli diganti, harga di keranjang ikut diperbarui.
    useEffect(() => {
        if (!customer || lines.length === 0) return;
        Promise.all(
            lines.map((l) =>
                fetch(`/orders/lookup/products?type=${customer.type}&q=${encodeURIComponent(l.sku)}`, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((arr: ProductOpt[]) => arr.find((p) => p.id === l.id)),
            ),
        ).then((fresh) => setLines((ls) => ls.map((l, i) => (fresh[i] ? { ...l, price: fresh[i]!.price, stock: fresh[i]!.stock } : l))));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customer?.id]);

    const add = (p: ProductOpt) => setLines((ls) => (ls.some((l) => l.id === p.id) ? ls.map((l) => (l.id === p.id ? { ...l, qty: l.qty + 1 } : l)) : [...ls, { ...p, qty: 1 }]));
    const setQty = (id: number, qty: number) => setLines((ls) => ls.map((l) => (l.id === id ? { ...l, qty: Math.max(1, qty) } : l)));

    const subtotal = lines.reduce((s, l) => s + l.price * l.qty, 0);
    const discount = Math.min(Number(form.data.discount || 0), subtotal);
    const total = subtotal - discount + Number(form.data.shipping_fee || 0);
    const fromStock = lines.reduce((s, l) => s + Math.min(l.qty, l.stock), 0);
    const toBuy = lines.reduce((s, l) => s + Math.max(0, l.qty - l.stock), 0);
    const buyGrams = lines.reduce((s, l) => s + Math.max(0, l.qty - l.stock) * l.weight_grams, 0);
    const missingPrice = lines.filter((l) => l.price <= 0);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, payment_status: d.channel === 'shopee' ? null : d.payment_status, items: lines.map((l) => ({ product_id: l.id, qty: l.qty })) }));
        form.post('/orders');
    };

    const errs = form.errors as Record<string, string>;
    const itemError = errs.items ?? Object.entries(errs).find(([k]) => k.startsWith('items.'))?.[1];

    return (
        <ErpLayout>
            <Head title="Order baru" />
            <PageHeader title="Order baru" subtitle="Pilih pembeli, tambahkan kitab, pilih status bayar, lalu simpan.">
                <Link href="/orders" className="text-ink-2 text-sm font-medium">
                    Batal
                </Link>
            </PageHeader>

            <form onSubmit={submit} className="grid gap-4 xl:grid-cols-[1fr_360px]">
                <div className="grid content-start gap-4">
                    {/* 1. Pembeli */}
                    <section className="card">
                        <h3 className="mb-3 text-base font-semibold">1. Pembeli</h3>
                        {customer ? (
                            <div className="border-line flex items-center gap-3 rounded-xl border p-3">
                                <div className="flex-1">
                                    <b className="font-medium">{customer.name}</b>{' '}
                                    <span className={'ml-1 rounded-full px-2 py-0.5 text-[12.5px] font-medium ' + (customer.type === 'reseller' ? 'bg-gold-soft text-gold-ink' : 'bg-soft text-ink-2')}>
                                        {customer.type === 'reseller' ? 'Reseller' : 'Reguler'}
                                    </span>
                                    <small className="text-ink-3 block text-[13px]">{[customer.code, customer.city, customer.phone].filter(Boolean).join(' · ')}</small>
                                </div>
                                <Btn
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        setCustomer(null);
                                        form.setData('customer_id', '');
                                    }}
                                >
                                    <X className="size-4" /> Ganti
                                </Btn>
                            </div>
                        ) : (
                            <>
                                <Picker<CustomerOpt>
                                    placeholder="Ketik nama atau nomor HP pembeli…"
                                    url="/orders/lookup/customers"
                                    autoFocus
                                    onPick={(c) => {
                                        setCustomer(c);
                                        form.setData('customer_id', c.id);
                                    }}
                                    render={(c) => (
                                        <>
                                            <span className="font-medium">{c.name}</span> <span className="text-ink-3 text-[13px]">· {c.type === 'reseller' ? 'Reseller' : 'Reguler'}</span>
                                            <small className="text-ink-3 block text-[12.5px]">{[c.code, c.city, c.phone].filter(Boolean).join(' · ')}</small>
                                        </>
                                    )}
                                />
                                <p className="text-ink-3 mt-2 text-[13px]">
                                    Pembeli belum terdaftar?{' '}
                                    <Link href="/customers" className="text-brand font-medium">
                                        Tambahkan di menu Customer
                                    </Link>{' '}
                                    — tipe pembeli menentukan harga.
                                </p>
                            </>
                        )}
                        {errs.customer_id && <p className="text-bad mt-2 text-[13px]">{errs.customer_id}</p>}
                    </section>

                    {/* 2. Kitab */}
                    <section className={'card ' + (customer ? '' : 'pointer-events-none opacity-50')}>
                        <h3 className="mb-3 text-base font-semibold">2. Kitab yang dipesan</h3>
                        {customer && (
                            <Picker<ProductOpt>
                                placeholder="Ketik judul kitab (Latin atau Arab)…"
                                url="/orders/lookup/products"
                                extra={`&type=${customer.type}`}
                                onPick={add}
                                render={(p) => (
                                    <div className="flex items-center gap-3">
                                        {p.has_photo ? (
                                            <img src={`/products/${p.id}/photo`} className="h-10 w-8 rounded object-cover" alt="" />
                                        ) : (
                                            <span className="bg-soft text-ink-3 grid h-10 w-8 place-items-center rounded">
                                                <BookOpen className="size-4" />
                                            </span>
                                        )}
                                        <div className="min-w-0 flex-1">
                                            {p.title_ar && <span className="ar block text-left">{p.title_ar}</span>}
                                            <span className="text-[13.5px]">{p.title_latin}</span>
                                        </div>
                                        <div className="num text-right text-[13px]">
                                            <div className={p.price > 0 ? 'font-medium' : 'text-warn'}>{p.price > 0 ? rupiah(p.price) : 'harga belum diisi'}</div>
                                            <div className="text-ink-3">stok {p.stock}</div>
                                        </div>
                                    </div>
                                )}
                            />
                        )}

                        {lines.length > 0 && (
                            <div className="mt-4 grid gap-2">
                                {lines.map((l) => {
                                    const take = Math.min(l.qty, l.stock);
                                    const buy = l.qty - take;
                                    return (
                                        <div key={l.id} className="border-line num flex flex-wrap items-center gap-3 rounded-xl border p-3">
                                            <div className="min-w-[180px] flex-1">
                                                {l.title_ar && <span className="ar block text-left">{l.title_ar}</span>}
                                                <span className="text-[13.5px]">{l.title_latin}</span>
                                                <div className="mt-1 flex flex-wrap gap-1.5 text-[12px]">
                                                    {take > 0 && <span className="bg-ok-soft text-ok rounded-md px-2 py-0.5">{take} dari gudang</span>}
                                                    {buy > 0 && <span className="bg-gold-soft text-gold-ink rounded-md px-2 py-0.5">{buy} dibeli di Mesir</span>}
                                                </div>
                                            </div>
                                            <div className="border-line flex items-center rounded-[10px] border">
                                                <button type="button" className="hover:bg-soft grid size-10 place-items-center rounded-l-[10px]" onClick={() => setQty(l.id, l.qty - 1)} aria-label="Kurangi">
                                                    <Minus className="size-4" />
                                                </button>
                                                <input
                                                    className="w-12 bg-transparent text-center outline-none"
                                                    inputMode="numeric"
                                                    value={l.qty}
                                                    onChange={(e) => setQty(l.id, Number(e.target.value.replace(/\D/g, '') || 1))}
                                                />
                                                <button type="button" className="hover:bg-soft grid size-10 place-items-center rounded-r-[10px]" onClick={() => setQty(l.id, l.qty + 1)} aria-label="Tambah">
                                                    <Plus className="size-4" />
                                                </button>
                                            </div>
                                            <div className="w-32 text-right">
                                                <div className={l.price > 0 ? 'font-medium' : 'text-bad text-[13px]'}>{l.price > 0 ? rupiah(l.price * l.qty) : 'harga belum diisi'}</div>
                                                {l.price > 0 && <div className="text-ink-3 text-[12.5px]">@ {rupiah(l.price)}</div>}
                                            </div>
                                            <Btn type="button" size="sm" variant="ghost" onClick={() => setLines((ls) => ls.filter((x) => x.id !== l.id))} aria-label="Hapus">
                                                <Trash2 className="size-4" />
                                            </Btn>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        {missingPrice.length > 0 && <p className="text-bad mt-3 text-[13px]">Ada kitab yang harganya belum diisi Owner. Hapus dari order atau minta Owner mengisi harganya dulu.</p>}
                        {itemError && <p className="text-bad mt-3 text-[13px]">{itemError}</p>}
                    </section>

                    {/* 3. Saluran & pembayaran */}
                    <section className={'card ' + (lines.length ? '' : 'pointer-events-none opacity-50')}>
                        <h3 className="mb-3 text-base font-semibold">3. Saluran & pembayaran</h3>
                        <div className="mb-4 grid grid-cols-2 gap-2 sm:max-w-sm">
                            {(['whatsapp', 'shopee'] as const).map((c) => (
                                <button
                                    type="button"
                                    key={c}
                                    onClick={() => form.setData('channel', c)}
                                    className={'h-11 rounded-[10px] border font-medium ' + (form.data.channel === c ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2')}
                                >
                                    {c === 'whatsapp' ? 'WhatsApp' : 'Shopee'}
                                </button>
                            ))}
                        </div>
                        {form.data.channel === 'shopee' ? (
                            <p className="bg-soft text-ink-2 rounded-xl p-3 text-sm">Pesanan Shopee otomatis dianggap lunas dan tidak dibuatkan invoice.</p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-3">
                                {PAY.map((p) => (
                                    <button
                                        type="button"
                                        key={p.value}
                                        onClick={() => form.setData('payment_status', p.value)}
                                        className={'rounded-xl border p-3 text-left ' + (form.data.payment_status === p.value ? 'border-brand bg-brand-soft' : 'border-line hover:border-ink-3')}
                                    >
                                        <span className={'block font-medium ' + (form.data.payment_status === p.value ? 'text-brand' : '')}>{p.title}</span>
                                        <span className="text-ink-3 mt-1 block text-[12.5px]">{p.desc}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                        {errs.payment_status && <p className="text-bad mt-2 text-[13px]">{errs.payment_status}</p>}
                    </section>
                </div>

                {/* Ringkasan */}
                <aside className="grid content-start gap-4 xl:sticky xl:top-6 xl:self-start">
                    <section className="card num">
                        <h3 className="mb-3 text-base font-semibold">Ringkasan tagihan</h3>
                        <div className="text-ink-2 flex justify-between py-1.5">
                            <span>Subtotal ({lines.reduce((s, l) => s + l.qty, 0)} kitab)</span>
                            <b className="text-ink font-medium">{rupiah(subtotal)}</b>
                        </div>
                        <div className="grid grid-cols-2 gap-3 py-2">
                            <Field label="Diskon" error={errs.discount}>
                                <MoneyInput value={form.data.discount} onChange={(v) => form.setData('discount', v)} />
                            </Field>
                            <Field label="Ongkir" error={errs.shipping_fee}>
                                <MoneyInput value={form.data.shipping_fee} onChange={(v) => form.setData('shipping_fee', v)} />
                            </Field>
                        </div>
                        <div className="border-line mt-1 flex items-baseline justify-between border-t pt-3">
                            <span className="font-semibold">Total tagihan</span>
                            <b className="font-display text-xl font-semibold">{rupiah(total)}</b>
                        </div>
                        {lines.length > 0 && (
                            <div className="bg-soft text-ink-2 mt-4 rounded-xl p-3 text-[13px]">
                                <b className="text-ink font-medium">{fromStock}</b> kitab diambil dari gudang
                                {toBuy > 0 && (
                                    <>
                                        , <b className="text-ink font-medium">{toBuy}</b> dibeli di Mesir (± {kg(buyGrams)} bagasi)
                                    </>
                                )}
                                .
                            </div>
                        )}
                        <Field label="Catatan (opsional)" className="mt-4">
                            <textarea className={inputCls + ' h-16 py-2'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </Field>
                        <Btn variant="primary" className="mt-4 w-full" disabled={form.processing || !customer || lines.length === 0 || missingPrice.length > 0 || (form.data.channel === 'whatsapp' && !form.data.payment_status)}>
                            {form.processing ? 'Menyimpan…' : 'Simpan order'}
                        </Btn>
                        {form.data.payment_status === 'paid' && form.data.channel === 'whatsapp' && <p className="text-warn mt-2 text-center text-[12.5px]">Pilih “Lunas” hanya bila uangnya sudah benar-benar masuk.</p>}
                    </section>
                </aside>
            </form>
        </ErpLayout>
    );
}
