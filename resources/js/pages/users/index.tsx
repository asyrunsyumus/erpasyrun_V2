import { Btn, Field, inputCls, Modal, PageHeader, Table } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Copy, KeyRound, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface U {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    must_change_password: boolean;
    last_login: string | null;
    is_me: boolean;
}

const ROLE_HINT: Record<string, string> = {
    owner: 'Seluruh sistem, termasuk harga, laporan, dan akun staf.',
    admin: 'Produk, Customer, Order & Invoice, resi, pembatalan.',
    mesir: 'Daftar belanja Mesir dan Maktabah (lihat saja).',
    gudang: 'Terima barang, packing, Stok Ready.',
};

export default function Users({ users, roles }: { users: U[]; roles: { value: string; label: string }[] }) {
    const { flash, errors } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const temp = flash.temp_password as { name: string; email: string; password: string } | null | undefined;
    const [showTemp, setShowTemp] = useState(false);
    const [copied, setCopied] = useState(false);
    const [editing, setEditing] = useState<U | 'new' | null>(null);
    const [deleting, setDeleting] = useState<U | null>(null);
    const [resetting, setResetting] = useState<U | null>(null);
    const form = useForm({ name: '', email: '', role: 'admin' });
    const del = useForm({ password: '' });

    useEffect(() => {
        if (temp) {
            setShowTemp(true);
            setCopied(false);
        }
    }, [temp]);

    const open = (u: U | 'new') => {
        form.clearErrors();
        form.setData(u === 'new' ? { name: '', email: '', role: 'admin' } : { name: u.name, email: u.email, role: u.role });
        setEditing(u);
    };

    return (
        <ErpLayout>
            <Head title="Pengguna" />
            <PageHeader title="Pengguna" subtitle="Akun staf. Setiap orang hanya melihat menu yang menjadi tugasnya.">
                <Btn variant="primary" onClick={() => open('new')}>
                    <Plus className="size-[18px]" /> Tambah pengguna
                </Btn>
            </PageHeader>

            <Table
                head={
                    <>
                        <th>Nama</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Terakhir masuk</th>
                        <th className="w-44" />
                    </>
                }
            >
                {users.map((u) => (
                    <tr key={u.id} className={u.is_active ? '' : 'opacity-60'}>
                        <td>
                            <span className="font-medium">{u.name}</span>
                            {u.is_me && <span className="text-ink-3 text-[13px]"> (Anda)</span>}
                            <small className="text-ink-3 block text-[13px]">{u.email}</small>
                        </td>
                        <td>{u.role_label}</td>
                        <td>
                            {u.is_active ? (
                                u.must_change_password ? (
                                    <span className="text-warn text-[13px] font-medium">Belum ganti password sementara</span>
                                ) : (
                                    <span className="text-ok text-[13px] font-medium">Aktif</span>
                                )
                            ) : (
                                <span className="text-ink-3 text-[13px] font-medium">Nonaktif</span>
                            )}
                        </td>
                        <td className="text-ink-2 text-sm">{u.last_login ?? '—'}</td>
                        <td className="text-right whitespace-nowrap">
                            {!u.is_me && (
                                <>
                                    <Btn size="sm" variant="ghost" onClick={() => open(u)} title="Ubah nama / peran">
                                        <Pencil className="size-4" />
                                    </Btn>
                                    <Btn size="sm" variant="ghost" onClick={() => setResetting(u)} title="Buat password sementara baru">
                                        <KeyRound className="size-4" />
                                    </Btn>
                                    <Btn size="sm" variant="ghost" onClick={() => router.post(`/users/${u.id}/toggle`, {}, { preserveScroll: true })} title={u.is_active ? 'Nonaktifkan' : 'Aktifkan'}>
                                        <Power className={'size-4 ' + (u.is_active ? '' : 'text-ok')} />
                                    </Btn>
                                    <Btn
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            del.reset();
                                            del.clearErrors();
                                            setDeleting(u);
                                        }}
                                        title="Hapus permanen"
                                    >
                                        <Trash2 className="size-4" />
                                    </Btn>
                                </>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>
            <p className="text-ink-3 mt-3 text-[13px]">Staf yang berhenti cukup dinonaktifkan (ikon daya) — ia langsung keluar dari sistem. Hapus permanen hanya bila perlu; riwayat pekerjaannya tetap tersimpan.</p>

            {/* Password sementara — tampil sekali */}
            <Modal open={showTemp && !!temp} onClose={() => setShowTemp(false)} title="Password sementara" description="Hanya ditampilkan SEKALI. Catat sekarang dan sampaikan lewat jalur pribadi.">
                {temp && (
                    <div className="grid gap-4">
                        <div className="bg-soft num rounded-xl p-4 text-sm">
                            <p className="text-ink-2">{temp.name}</p>
                            <p className="text-ink-2">{temp.email}</p>
                            <p className="font-display mt-2 text-2xl font-semibold tracking-wider select-all">{temp.password}</p>
                        </div>
                        <p className="text-ink-2 text-sm">Saat login pertama, sistem akan meminta staf mengganti password ini. Password ini tidak disimpan di mana pun dan tidak bisa dilihat lagi — termasuk oleh Anda.</p>
                        <div className="flex justify-end gap-2">
                            <Btn
                                onClick={() => {
                                    navigator.clipboard?.writeText(temp.password).then(() => setCopied(true));
                                }}
                            >
                                <Copy className="size-4" /> {copied ? 'Tersalin' : 'Salin'}
                            </Btn>
                            <Btn variant="primary" onClick={() => setShowTemp(false)}>
                                Sudah saya catat
                            </Btn>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Tambah pengguna' : 'Ubah pengguna'}>
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
                        editing === 'new' ? form.post('/users', opts) : form.put(`/users/${(editing as U).id}`, opts);
                    }}
                >
                    <Field label="Nama" error={form.errors.name}>
                        <input className={inputCls} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required autoFocus />
                    </Field>
                    {editing === 'new' && (
                        <Field label="Email (untuk login)" error={form.errors.email}>
                            <input type="email" className={inputCls} value={form.data.email} onChange={(e) => form.setData('email', e.target.value.toLowerCase())} required />
                        </Field>
                    )}
                    <Field label="Peran" error={form.errors.role}>
                        <div className="grid gap-2">
                            {roles.map((r) => (
                                <button
                                    type="button"
                                    key={r.value}
                                    onClick={() => form.setData('role', r.value)}
                                    className={'rounded-xl border p-3 text-left ' + (form.data.role === r.value ? 'border-brand bg-brand-soft' : 'border-line hover:border-ink-3')}
                                >
                                    <span className={'block font-medium ' + (form.data.role === r.value ? 'text-brand' : '')}>{r.label}</span>
                                    <span className="text-ink-3 text-[12.5px]">{ROLE_HINT[r.value]}</span>
                                </button>
                            ))}
                        </div>
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setEditing(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" disabled={form.processing}>
                            {editing === 'new' ? 'Buat akun' : 'Simpan'}
                        </Btn>
                    </div>
                </form>
            </Modal>

            <Modal open={resetting !== null} onClose={() => setResetting(null)} title="Buat password sementara baru?" description="Password lama langsung tidak berlaku dan staf keluar dari semua perangkat.">
                <div className="flex justify-end gap-2">
                    <Btn onClick={() => setResetting(null)}>Batal</Btn>
                    <Btn variant="primary" onClick={() => resetting && router.post(`/users/${resetting.id}/reset-password`, {}, { preserveScroll: true, onFinish: () => setResetting(null) })}>
                        Ya, buat baru
                    </Btn>
                </div>
            </Modal>

            <Modal open={deleting !== null} onClose={() => setDeleting(null)} title="Hapus permanen?" description="Nama dan email akan dihapus dan akun tidak bisa dipakai lagi. Riwayat pesanan yang pernah ia kerjakan tetap tersimpan.">
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        deleting && del.delete(`/users/${deleting.id}`, { preserveScroll: true, onSuccess: () => setDeleting(null) });
                    }}
                >
                    <p className="text-sm">
                        Akun: <b>{deleting?.name}</b> ({deleting?.email})
                    </p>
                    <Field label="Ketik ulang password Anda" error={del.errors.password || errors.password}>
                        <input type="password" className={inputCls} value={del.data.password} onChange={(e) => del.setData('password', e.target.value)} autoComplete="current-password" required />
                    </Field>
                    <div className="flex justify-end gap-2">
                        <Btn type="button" onClick={() => setDeleting(null)}>
                            Batal
                        </Btn>
                        <Btn variant="primary" className="!bg-bad" disabled={del.processing}>
                            Hapus permanen
                        </Btn>
                    </div>
                </form>
            </Modal>
        </ErpLayout>
    );
}
