import { applyFilters, Btn, ConfirmDelete, EmptyState, ErrorBanner, Field, inputCls, Modal, PageHeader, Pager, type Paginated, SearchBox, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';

interface Maktabah {
    id: number;
    name: string;
    area: string | null;
    phone: string | null;
    notes: string | null;
    products_count: number;
}

export default function Maktabahs({ items, filters, canEdit }: { items: Paginated<Maktabah>; filters: { q: string }; canEdit: boolean }) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [editing, setEditing] = useState<Maktabah | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Maktabah | null>(null);
    const form = useForm({ name: '', area: '', phone: '', notes: '' });

    const open = (m: Maktabah | 'new') => {
        form.clearErrors();
        form.setData(m === 'new' ? { name: '', area: '', phone: '', notes: '' } : { name: m.name, area: m.area ?? '', phone: m.phone ?? '', notes: m.notes ?? '' });
        setEditing(m);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? form.post('/maktabahs', opts) : form.put(`/maktabahs/${(editing as Maktabah).id}`, opts);
    };

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
                        {canEdit && <th className="w-24" />}
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
                        {canEdit && (
                            <td className="text-right whitespace-nowrap">
                                <Btn size="sm" variant="ghost" onClick={() => open(m)} aria-label="Ubah">
                                    <Pencil className="size-4" />
                                </Btn>
                                <Btn size="sm" variant="ghost" onClick={() => setDeleting(m)} aria-label="Hapus">
                                    <Trash2 className="size-4" />
                                </Btn>
                            </td>
                        )}
                    </tr>
                ))}
            </Table>
            <Pager page={items} />

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Tambah maktabah' : 'Ubah maktabah'}>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Nama maktabah" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoFocus required />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Area" error={form.errors.area}>
                            <input className={inputCls} value={form.data.area} onChange={(e) => form.setData('area', e.target.value)} placeholder="mis. Al-Azhar" />
                        </Field>
                        <Field label="Telepon" error={form.errors.phone}>
                            <input className={inputCls} value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Catatan" error={form.errors.notes}>
                        <textarea className={inputCls + ' h-20 py-2'} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
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
                title="Hapus maktabah?"
                message={<>Maktabah <b>{deleting?.name}</b> akan dihapus. Sistem menolak bila maktabah ini masih dipakai kitab atau punya riwayat belanja.</>}
                onConfirm={() => deleting && router.delete(`/maktabahs/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
