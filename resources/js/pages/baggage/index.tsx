import { Btn, ConfirmDelete, ErrorBanner, Field, inputCls, Modal, MoneyInput, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { kg, rupiah } from '@/lib/format';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRightLeft, Pencil, Plane, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Waiting {
    id: number;
    number: string;
    customer: string;
    grams: number;
    days: number;
}
interface Schedule {
    id: number;
    depart_date: string;
    date_label: string;
    days_left: number;
    carrier_contact: string;
    quota_grams: number | null;
    cost_idr: number;
    used_grams: number;
    orders: { id: number; number: string; customer: string; grams: number; status: string }[];
    lines_total: number;
    lines_received: number;
}
interface Props {
    waiting: Waiting[];
    waitingGrams: number;
    schedules: Schedule[];
    today: string;
}
type Form = { depart_date: string; carrier_contact: string; quota_kg: string; cost_idr: number | '' };

function Usage({ used, quota }: { used: number; quota: number | null }) {
    if (quota === null) return <p className="text-ink-3 num text-[13.5px]">{kg(used)} terpakai · kuota tanpa batas</p>;
    const pct = Math.min(100, (used / quota) * 100);
    const full = pct >= 90;
    return (
        <>
            <div className="bg-soft mt-3 mb-1.5 h-2.5 overflow-hidden rounded-md">
                <i className={'block h-full rounded-md ' + (full ? 'bg-warn' : 'bg-brand')} style={{ width: `${pct}%` }} />
            </div>
            <p className="num text-[13.5px]">
                <b className="font-medium">
                    {kg(used)} / {kg(quota)}
                </b>
                <span className={full ? 'text-warn' : 'text-ink-3'}> · sisa {kg(Math.max(0, quota - used))}</span>
            </p>
        </>
    );
}

export default function Baggage({ waiting, waitingGrams, schedules, today }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [editing, setEditing] = useState<Schedule | 'new' | null>(null);
    const [moving, setMoving] = useState<Schedule | null>(null);
    const [deleting, setDeleting] = useState<Schedule | null>(null);
    const [target, setTarget] = useState<number | ''>('');
    const form = useForm<Form>({ depart_date: today, carrier_contact: '', quota_kg: '', cost_idr: '' });

    const open = (s: Schedule | 'new') => {
        form.clearErrors();
        form.setData(
            s === 'new'
                ? { depart_date: today, carrier_contact: '', quota_kg: '', cost_idr: '' }
                : { depart_date: s.depart_date, carrier_contact: s.carrier_contact, quota_kg: s.quota_grams === null ? '' : String(s.quota_grams / 1000).replace('.', ','), cost_idr: s.cost_idr || '' },
        );
        setEditing(s);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, quota_kg: d.quota_kg.trim() === '' ? null : Number(d.quota_kg.replace(',', '.')), cost_idr: d.cost_idr || 0 }));
        const opts = { preserveScroll: true, onSuccess: () => setEditing(null) };
        editing === 'new' ? form.post('/baggage', opts) : form.put(`/baggage/${(editing as Schedule).id}`, opts);
    };

    const upcoming = schedules.filter((s) => s.days_left >= 0).sort((a, b) => a.depart_date.localeCompare(b.depart_date));
    const past = schedules.filter((s) => s.days_left < 0);
    const targets = (from: Schedule) => upcoming.filter((s) => s.id !== from.id);

    const Card = ({ s }: { s: Schedule }) => (
        <article className="card">
            <div className="flex flex-wrap items-start gap-3">
                <span className="bg-brand-soft text-brand grid size-10 shrink-0 place-items-center rounded-xl">
                    <Plane className="size-5" />
                </span>
                <div className="min-w-[180px] flex-1">
                    <p className="font-display num text-lg font-semibold">{s.date_label}</p>
                    <p className="text-ink-3 text-[13.5px]">
                        {s.carrier_contact} · {s.days_left > 0 ? `${s.days_left} hari lagi` : s.days_left === 0 ? 'hari ini' : `${-s.days_left} hari lalu`}
                        {s.cost_idr > 0 && ` · ongkos ${rupiah(s.cost_idr)}`}
                    </p>
                </div>
                <div className="flex gap-1">
                    <Btn size="sm" variant="ghost" onClick={() => open(s)} aria-label="Ubah">
                        <Pencil className="size-4" /> Ubah
                    </Btn>
                    {s.orders.length > 0 ? (
                        <Btn
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                setTarget('');
                                setMoving(s);
                            }}
                            disabled={s.lines_received > 0}
                            title={s.lines_received > 0 ? 'Sebagian kitab sudah diterima gudang' : ''}
                        >
                            <ArrowRightLeft className="size-4" /> Pindah
                        </Btn>
                    ) : (
                        <Btn size="sm" variant="ghost" onClick={() => setDeleting(s)} aria-label="Hapus">
                            <Trash2 className="size-4" />
                        </Btn>
                    )}
                </div>
            </div>
            <Usage used={s.used_grams} quota={s.quota_grams} />
            {s.lines_total > 0 && (
                <p className="text-ink-3 mt-1 text-[13px]">
                    Diterima gudang: {s.lines_received} / {s.lines_total} judul
                </p>
            )}
            {s.orders.length > 0 && (
                <div className="border-line mt-3 border-t pt-2">
                    <p className="text-ink-2 mb-1 text-sm font-medium">{s.orders.length} pesanan terpasang</p>
                    <div className="flex flex-wrap gap-1.5">
                        {s.orders.map((o) => (
                            <Link key={o.id} href={`/orders/${o.id}`} className="bg-soft hover:bg-brand-soft num rounded-lg px-2.5 py-1 text-[12.5px]" title={o.customer}>
                                {o.number} · {kg(o.grams)}
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </article>
    );

    return (
        <ErpLayout>
            <Head title="Jadwal Bagasi" />
            <PageHeader title="Jadwal Bagasi" subtitle="Pesanan berhenti di sini sampai ada jadwal. Begitu jadwal disimpan, pesanan yang menunggu dipasang otomatis dari yang paling lama.">
                <Btn variant="primary" onClick={() => open('new')}>
                    <Plus className="size-[18px]" /> Jadwal baru
                </Btn>
            </PageHeader>

            <ErrorBanner message={errors.delete} />

            <section className={'card mb-5 ' + (waiting.length ? '!border-gold !bg-gold-soft' : '')}>
                <div className="flex flex-wrap items-baseline gap-2">
                    <h3 className="text-base font-semibold">Menunggu jadwal bagasi ({waiting.length})</h3>
                    {waiting.length > 0 && <span className="text-ink-2 num text-sm">total {kg(waitingGrams)}</span>}
                </div>
                {waiting.length === 0 ? (
                    <p className="text-ink-3 mt-1 text-sm">Tidak ada pesanan yang menunggu.</p>
                ) : (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {waiting.map((w) => (
                            <Link key={w.id} href={`/orders/${w.id}`} className="bg-surface border-line hover:border-brand num rounded-lg border px-2.5 py-1.5 text-[13px]">
                                <b className="font-medium">{w.number}</b> · {kg(w.grams)} · <span className={w.days >= 5 ? 'text-warn font-medium' : 'text-ink-3'}>{w.days} hari</span>
                            </Link>
                        ))}
                    </div>
                )}
            </section>

            <h2 className="font-display mb-3 text-lg font-semibold">Akan berangkat</h2>
            {upcoming.length === 0 ? (
                <p className="text-ink-3 card mb-6 text-sm">Belum ada jadwal yang akan datang.</p>
            ) : (
                <div className="mb-6 grid gap-3 lg:grid-cols-2">
                    {upcoming.map((s) => (
                        <Card key={s.id} s={s} />
                    ))}
                </div>
            )}

            {past.length > 0 && (
                <>
                    <h2 className="font-display text-ink-2 mb-3 text-lg font-semibold">Sudah lewat</h2>
                    <div className="grid gap-3 lg:grid-cols-2">
                        {past.map((s) => (
                            <Card key={s.id} s={s} />
                        ))}
                    </div>
                </>
            )}

            <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Jadwal bagasi baru' : 'Ubah jadwal bagasi'} description={editing === 'new' ? 'Setelah disimpan, pesanan yang menunggu langsung dipasang otomatis.' : 'Bila kuota dinaikkan, pesanan yang masih menunggu dicek ulang otomatis.'}>
                <form onSubmit={submit} className="grid gap-4">
                    <Field label="Tanggal keberangkatan" error={form.errors.depart_date}>
                        <input type="date" className={inputCls} value={form.data.depart_date} onChange={(e) => form.setData('depart_date', e.target.value)} required />
                    </Field>
                    <Field label="Kontak jasa bagasi" error={form.errors.carrier_contact}>
                        <input className={inputCls} value={form.data.carrier_contact} onChange={(e) => form.setData('carrier_contact', e.target.value)} placeholder="mis. Ust. Hamid · +20 10 …" required />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Kuota (kg)" error={(form.errors as Record<string, string>).quota_kg} hint="Kosongkan = tanpa batas.">
                            <input className={inputCls + ' num'} inputMode="decimal" value={form.data.quota_kg} onChange={(e) => form.setData('quota_kg', e.target.value.replace(/[^0-9.,]/g, ''))} />
                        </Field>
                        <Field label="Ongkos bagasi" error={form.errors.cost_idr} hint="Dibagi ke kitab sesuai berat.">
                            <MoneyInput value={form.data.cost_idr} onChange={(v) => form.setData('cost_idr', v)} />
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

            <Modal open={moving !== null} onClose={() => setMoving(null)} title="Pindahkan pesanan" description="Semua pesanan di jadwal ini dipindah ke jadwal lain. Setelah kosong, jadwal ini bisa dihapus.">
                {moving && (
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(`/baggage/${moving.id}/move`, { target_id: target }, { preserveScroll: true, onSuccess: () => setMoving(null) });
                        }}
                    >
                        <p className="text-ink-2 num text-sm">
                            {moving.orders.length} pesanan · {kg(moving.used_grams)} dari jadwal {moving.date_label}
                        </p>
                        <Field label="Pindah ke jadwal" error={errors.target_id}>
                            <select className={inputCls} value={target} onChange={(e) => setTarget(e.target.value ? Number(e.target.value) : '')} required>
                                <option value="">— pilih —</option>
                                {targets(moving).map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.date_label} · sisa {t.quota_grams === null ? 'tanpa batas' : kg(Math.max(0, t.quota_grams - t.used_grams))}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        {targets(moving).length === 0 && <p className="text-warn text-sm">Belum ada jadwal lain yang akan datang. Buat jadwal baru dulu.</p>}
                        <div className="flex justify-end gap-2">
                            <Btn type="button" onClick={() => setMoving(null)}>
                                Batal
                            </Btn>
                            <Btn variant="primary" disabled={!target}>
                                Pindahkan
                            </Btn>
                        </div>
                    </form>
                )}
            </Modal>

            <ConfirmDelete
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title="Hapus jadwal?"
                message={<>Jadwal {deleting?.date_label} akan dihapus. Jadwal ini tidak dipakai pesanan mana pun.</>}
                onConfirm={() => deleting && router.delete(`/baggage/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
            />
        </ErpLayout>
    );
}
