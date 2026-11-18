import { applyFilters, Btn, ConfirmDelete, EmptyState, ErrorBanner, Field, inputCls, Modal, PageHeader, Pager, type Paginated, SearchBox, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

interface Maktabah {
    id: number;
    name: string;
    area: string | null;
    phone: string | null;
    notes: string | null;
    gmaps_url: string | null;
    products_count: number;
}

type Form = { name: string; area: string; phone: string; notes: string; gmaps_url: string };
const blank: Form = { name: '', area: '', phone: '', notes: '', gmaps_url: '' };

/** Ikon pin peta (SVG), dipakai untuk tombol buka link Google Maps. */
export function GmapsIcon({ className = 'size-4' }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
            <path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Z" fill="#EA4335" />
            <circle cx="12" cy="9" r="2.6" fill="#fff" />
            <path d="M12 2a7 7 0 0 0-7 7h4.4A2.6 2.6 0 0 1 12 6.4Z" fill="#FBBC04" />
            <path d="M5 9c0 1.2.4 2.2.9 3.2L9.4 9H5Z" fill="#34A853" />
        </svg>
    );
}

/** Tombol ikon: membuka link Maps tersimpan di tab baru. */
function MapsButton({ url, label = 'Buka di Google Maps' }: { url: string; label?: string }) {
    return (
        <a href={url} target="_blank" rel="noopener" aria-label={label} title={label} className="hover:bg-soft inline-grid size-9 place-items-center rounded-lg border border-line">
            <GmapsIcon className="size-5" />
        </a>
    );
}

export default function Maktabahs({ items, filters, canEdit }: { items: Paginated<Maktabah>; filters: { q: string }; canEdit: boolean }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [editing, setEditing] = useState<Maktabah | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Maktabah | null>(null);
    const form = useForm<Form>(blank);

    const open = (m: Maktabah | 'new') => {
        form.clearErrors();
        form.setData(m === 'new' ? blank : { name: m.name, area: m.area ?? '', phone: m.phone ?? '', notes: m.notes ?? '', gmaps_url: m.gmaps_url ?? '' });
        setEditing(m);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? form.post('/maktabahs', opts) : form.put(`/maktabahs/${(editing as Maktabah).id}`, opts);
    };

    // Peta pencarian dari nama & area, hanya saat link belum diisi. Tidak disimpan.
    const searchQuery = [form.data.name, form.data.area].filter((s) => s.trim()).join(' ');
    const embedSrc = `https://www.google.com/maps?q=${encodeURIComponent(searchQuery)}&output=embed`;
    const searchUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(searchQuery)}`;

    return (
        <ErpLayout>
            <Head title="Maktabah" />
            <PageHeader title="Maktabah" subtitle={canEdit ? 'Toko kitab di Mesir tempat Tim Mesir berbelanja.' : 'Daftar toko kitab di Mesir. Hanya Owner yang bisa menambah atau mengubah.'}>
                {canEdit && (
                    <>
                        <Btn onClick={() => router.visit('/imports?type=maktabahs')}>
                            <Upload className="size-[18px]" /> Import
                        </Btn>
                        <Btn variant="primary" onClick={() => open('new')}>
                            <Plus className="size-[18px]" /> Tambah maktabah
                        </Btn>
                    </>
                )}
            </PageHeader>

            <ErrorBanner message={errors.delete} />

            <div className="mb-4 flex flex-wrap gap-3">
                <SearchBox value={filters.q} placeholder="Cari nama atau area…" onSearch={(q) => applyFilters('/maktabahs', { q })} />
            </div>

            <Table
                head={
                    <>
                        <th>Nama</th>
                        <th>Area</th>
                        <th>Telepon</th>
                        <th className="text-right">Kitab utama</th>
                        <th className="w-24" />
                    </>
                }
                empty={items.data.length === 0 && <EmptyState title={filters.q ? 'Tidak ditemukan.' : 'Belum ada maktabah.'}>{canEdit && !filters.q && 'Tambahkan satu per satu, atau import dari Excel.'}</EmptyState>}
            >
                {items.data.map((m) => (
                    <tr key={m.id}>
                        <td>
                            <span className="font-medium">{m.name}</span>
                            {m.notes && <small className="text-ink-3 block text-[13px]">{m.notes}</small>}
                        </td>
                        <td className="text-ink-2">{m.area || '—'}</td>
                        <td className="text-ink-2">{m.phone || '—'}</td>
                        <td className="text-right">{m.products_count}</td>
                        <td className="text-right whitespace-nowrap">
                            {m.gmaps_url && <MapsButton url={m.gmaps_url} />}
                            {canEdit && (
                                <>
                                    <Btn size="sm" variant="ghost" onClick={() => open(m)} aria-label="Ubah">
                                        <Pencil className="size-4" />
                                    </Btn>
                                    <Btn size="sm" variant="ghost" onClick={() => setDeleting(m)} aria-label="Hapus">
                                        <Trash2 className="size-4" />
                                    </Btn>
                                </>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            <Pager page={items} />

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Tambah maktabah' : 'Ubah maktabah'} wide>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nama maktabah" error={form.errors.name}>
                            <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus required />
                        </Field>
                        <Field label="Area" error={form.errors.area}>
                            <input className={inputCls} value={form.data.area} onChange={(e) => form.setData('area', e.target.value)} placeholder="mis. Al-Azhar" />
                        </Field>
                        <Field label="Telepon" error={form.errors.phone}>
                            <input className={inputCls} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field label="Catatan" error={form.errors.notes}>
                            <input className={inputCls} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </Field>
                    </div>

                    <Field label="Link Google Maps" error={form.errors.gmaps_url} hint="Ketik atau tempel link dari Google Maps. Dibiarkan kosong pun tidak apa-apa.">
                        <div className="flex items-center gap-2">
                            <input
                                className={inputCls + ' flex-1'}
                                inputMode="url"
                                placeholder="https://maps.app.goo.gl/…"
                                value={form.data.gmaps_url}
                                onChange={(e) => form.setData('gmaps_url', e.target.value)}
                            />
                            {form.data.gmaps_url.trim() && <MapsButton url={form.data.gmaps_url.trim()} />}
                        </div>
                    </Field>

                    {!form.data.gmaps_url.trim() && (
                        <div className="grid gap-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="text-ink-2 flex items-center gap-1.5 text-sm font-medium">
                                    <Search className="size-4" /> Cari toko di peta
                                </span>
                                {searchQuery.trim() && (
                                    <a href={searchUrl} target="_blank" rel="noopener" className="text-brand text-[13px] font-medium">
                                        Buka pencarian penuh
                                    </a>
                                )}
                            </div>
                            {searchQuery.trim() ? (
                                <>
                                    <iframe title="Peta pencarian" src={embedSrc} loading="lazy" referrerPolicy="no-referrer-when-downgrade" className="border-line h-56 w-full rounded-xl border" />
                                    <p className="text-ink-3 text-[12.5px]">
                                        Kalau tokonya sudah ketemu, buka di Google Maps lalu salin link-nya ke kolom di atas.
                                    </p>
                                </>
                            ) : (
                                <p className="text-ink-3 text-[13px]">Isi nama atau area dulu untuk mencari toko di peta.</p>
                            )}
                        </div>
                    )}

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
                title="Hapus maktabah?"
                message={<>Maktabah <b>{deleting?.name}</b> akan dihapus. Sistem menolak bila maktabah ini masih dipakai kitab atau punya riwayat belanja.</>}
                onConfirm={() => deleting && router.delete(`/maktabahs/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
