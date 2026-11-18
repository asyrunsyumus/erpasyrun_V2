import { applyFilters, Btn, ConfirmDelete, EmptyState, ErrorBanner, Field, inputCls, Modal, PageHeader, Pager, type Paginated, SearchBox, Segmented, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { parseAddress } from '@/lib/address';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Upload, Wand2 } from 'lucide-react';
import { useState } from 'react';

interface Customer {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    address: string | null;
    city: string | null;
    kelurahan: string | null;
    kecamatan: string | null;
    kode_pos: string | null;
    type: 'reguler' | 'reseller';
    notes: string | null;
}
type Form = {
    name: string;
    phone: string;
    address: string;
    city: string;
    kelurahan: string;
    kecamatan: string;
    kode_pos: string;
    type: 'reguler' | 'reseller';
    notes: string;
};
const blank: Form = { name: '', phone: '', address: '', city: '', kelurahan: '', kecamatan: '', kode_pos: '', type: 'reguler', notes: '' };

/** Wilayah ringkas untuk tabel. */
export function wilayah(c: { kelurahan?: string | null; kecamatan?: string | null; city?: string | null; kode_pos?: string | null }): string {
    return [c.kelurahan, c.kecamatan, c.city, c.kode_pos].filter(Boolean).join(', ');
}

export default function Customers({ items, filters, canDelete }: { items: Paginated<Customer>; filters: { q: string; type: string | null }; canDelete: boolean }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [editing, setEditing] = useState<Customer | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Customer | null>(null);
    const [parsedNote, setParsedNote] = useState('');
    const form = useForm<Form>(blank);

    const open = (c: Customer | 'new') => {
        form.clearErrors();
        setParsedNote('');
        form.setData(
            c === 'new'
                ? blank
                : {
                      name: c.name,
                      phone: c.phone ?? '',
                      address: c.address ?? '',
                      city: c.city ?? '',
                      kelurahan: c.kelurahan ?? '',
                      kecamatan: c.kecamatan ?? '',
                      kode_pos: c.kode_pos ?? '',
                      type: c.type,
                      notes: c.notes ?? '',
                  },
        );
        setEditing(c);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? form.post('/customers', opts) : form.put(`/customers/${(editing as Customer).id}`, opts);
    };

    // Baca otomatis dari teks alamat. Hanya mengisi kolom yang masih kosong; pengisi tetap mengecek.
    const readAddress = () => {
        const p = parseAddress(form.data.address);
        const found: string[] = [];
        if (p.kelurahan && !form.data.kelurahan) {
            form.setData('kelurahan', p.kelurahan);
            found.push('kelurahan');
        }
        if (p.kecamatan && !form.data.kecamatan) {
            form.setData('kecamatan', p.kecamatan);
            found.push('kecamatan');
        }
        if (p.kode_pos && !form.data.kode_pos) {
            form.setData('kode_pos', p.kode_pos);
            found.push('kode pos');
        }
        setParsedNote(found.length ? `Terisi dari alamat: ${found.join(', ')}. Mohon dicek lagi.` : 'Tidak ada kelurahan, kecamatan, atau kode pos yang terbaca di alamat.');
    };

    return (
        <ErpLayout>
            <Head title="Customer" />
            <PageHeader title="Customer" subtitle="Tipe pembeli menentukan harga yang dipakai saat membuat order.">
                <Btn onClick={() => router.visit('/imports?type=customers')}>
                    <Upload className="size-[18px]" /> Import
                </Btn>
                <Btn variant="primary" onClick={() => open('new')}>
                    <Plus className="size-[18px]" /> Tambah customer
                </Btn>
            </PageHeader>

            <ErrorBanner message={errors.delete} />

            <div className="mb-4 flex flex-wrap gap-3">
                <SearchBox value={filters.q} placeholder="Cari nama, telepon, kota, kecamatan, atau kode…" onSearch={(q) => applyFilters('/customers', { q, type: filters.type })} />
                <Segmented
                    value={(filters.type ?? 'semua') as 'semua' | 'reguler' | 'reseller'}
                    options={[
                        { value: 'semua', label: 'Semua' },
                        { value: 'reguler', label: 'Reguler' },
                        { value: 'reseller', label: 'Reseller' },
                    ]}
                    onChange={(t) => applyFilters('/customers', { q: filters.q, type: t === 'semua' ? null : t })}
                />
            </div>

            <Table
                head={
                    <>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Telepon</th>
                        <th>Wilayah</th>
                        <th className="w-24" />
                    </>
                }
                empty={items.data.length === 0 && <EmptyState title={filters.q ? 'Tidak ditemukan.' : 'Belum ada customer.'}>{!filters.q && 'Tambahkan satu per satu, atau import dari Excel.'}</EmptyState>}
            >
                {items.data.map((c) => (
                    <tr key={c.id}>
                        <td>
                            <span className="font-medium">{c.name}</span>
                            <small className="text-ink-3 block text-[13px]">{c.code}</small>
                        </td>
                        <td>
                            <span className={'rounded-full px-2.5 py-0.5 text-[13px] font-medium ' + (c.type === 'reseller' ? 'bg-gold-soft text-gold-ink' : 'bg-soft text-ink-2')}>
                                {c.type === 'reseller' ? 'Reseller' : 'Reguler'}
                            </span>
                        </td>
                        <td className="text-ink-2">{c.phone || '—'}</td>
                        <td className="text-ink-2">{wilayah(c) || '—'}</td>
                        <td className="text-right whitespace-nowrap">
                            <Btn size="sm" variant="ghost" onClick={() => open(c)} aria-label="Ubah">
                                <Pencil className="size-4" />
                            </Btn>
                            {canDelete && (
                                <Btn size="sm" variant="ghost" onClick={() => setDeleting(c)} aria-label="Hapus">
                                    <Trash2 className="size-4" />
                                </Btn>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            <Pager page={items} />

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Tambah customer' : 'Ubah customer'}>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Nama" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <Field label="Tipe pembeli" error={form.errors.type} hint="Reseller otomatis mendapat harga reseller.">
                        <div className="grid grid-cols-2 gap-2">
                            {(['reguler', 'reseller'] as const).map((t) => (
                                <button
                                    type="button"
                                    key={t}
                                    onClick={() => form.setData('type', t)}
                                    className={'h-11 rounded-[10px] border font-medium ' + (form.data.type === t ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-2')}
                                >
                                    {t === 'reguler' ? 'Reguler' : 'Reseller'}
                                </button>
                            ))}
                        </div>
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Telepon / WhatsApp" error={form.errors.phone}>
                            <input className={inputCls} inputMode="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Kota" error={form.errors.city}>
                            <input className={inputCls} value={form.data.city} onChange={(e) => form.setData('city', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Alamat pengiriman" error={form.errors.address}>
                        <textarea className={inputCls + ' h-20 py-2'} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                    </Field>
                    <div className="-mt-2 flex flex-wrap items-center gap-2">
                        <Btn type="button" size="sm" onClick={readAddress} disabled={!form.data.address.trim()}>
                            <Wand2 className="size-4" /> Baca dari alamat
                        </Btn>
                        {parsedNote && <span className="text-ink-3 text-[13px]">{parsedNote}</span>}
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Kelurahan" error={form.errors.kelurahan}>
                            <input className={inputCls} value={form.data.kelurahan} onChange={(e) => form.setData('kelurahan', e.target.value)} />
                        </Field>
                        <Field label="Kecamatan" error={form.errors.kecamatan}>
                            <input className={inputCls} value={form.data.kecamatan} onChange={(e) => form.setData('kecamatan', e.target.value)} />
                        </Field>
                        <Field label="Kode pos" error={form.errors.kode_pos}>
                            <input className={inputCls + ' num'} inputMode="numeric" value={form.data.kode_pos} onChange={(e) => form.setData('kode_pos', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Catatan" error={form.errors.notes}>
                        <input className={inputCls} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
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
                title="Hapus customer?"
                message={<>Customer <b>{deleting?.name}</b> akan dihapus. Sistem menolak bila customer ini sudah punya pesanan.</>}
                onConfirm={() => deleting && router.delete(`/customers/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
