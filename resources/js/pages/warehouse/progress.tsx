import { EmptyState, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head } from '@inertiajs/react';
import { Check, Plane } from 'lucide-react';

interface Schedule {
    id: number;
    schedule: string;
    carrier: string | null;
    total: number;
    received: number;
}
interface Packing {
    id: number;
    number: string;
    customer: string;
    total: number;
    checked: number;
    has_label: boolean;
}
interface Activity {
    what: string;
    user: string;
    at: string;
}

function Bar({ done, total }: { done: number; total: number }) {
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    return (
        <div className="bg-soft h-2 w-full overflow-hidden rounded-full">
            <div className={'h-full rounded-full transition-[width] duration-500 ' + (pct === 100 ? 'bg-ok' : 'bg-brand')} style={{ width: `${pct}%` }} />
        </div>
    );
}

function Kpi({ label, value, hint }: { label: string; value: number; hint?: string }) {
    return (
        <div className="card">
            <p className="text-ink-3 text-[13px]">{label}</p>
            <p className="font-display num mt-1 text-3xl font-semibold">{value}</p>
            {hint && <p className="text-ink-3 mt-1 text-[12.5px]">{hint}</p>}
        </div>
    );
}

export default function WarehouseProgress({
    schedules,
    arrivals,
    packing,
    doneToday,
    activity,
}: {
    schedules: Schedule[];
    arrivals: { lines: number; qty: number };
    packing: Packing[];
    doneToday: number;
    activity: Activity[];
}) {
    const waitingInbound = schedules.reduce((s, x) => s + (x.total - x.received), 0);

    return (
        <ErpLayout>
            <Head title="Progres Gudang" />
            <PageHeader title="Progres Gudang" subtitle="Pantauan Tim Gudang Indonesia: kiriman dari Mesir, restok, packing, dan aktivitas. Hanya untuk Owner." />

            <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Kpi label="Kitab belum dicentang" value={waitingInbound} hint="dari kiriman bagasi" />
                <Kpi label="Restok / kelebihan menunggu" value={arrivals.lines} hint={`${arrivals.qty} buah belum dicentang`} />
                <Kpi label="Pesanan sedang dipacking" value={packing.length} />
                <Kpi label="Selesai hari ini" value={doneToday} hint="diserahkan ke ekspedisi" />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <section className="card">
                    <h2 className="font-display mb-3 text-lg font-semibold">Kiriman dari Mesir per bagasi</h2>
                    {schedules.length === 0 ? (
                        <EmptyState title="Belum ada kiriman." />
                    ) : (
                        <ul className="grid gap-3">
                            {schedules.map((s) => (
                                <li key={s.id} className="border-line rounded-xl border p-3">
                                    <div className="mb-2 flex items-center gap-2.5">
                                        <span className="bg-brand-soft text-brand grid size-8 place-items-center rounded-full">
                                            <Plane className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <b className="block text-sm font-semibold">Bagasi {s.schedule}</b>
                                            {s.carrier && <span className="text-ink-3 text-[12.5px]">{s.carrier}</span>}
                                        </div>
                                        <span className="num text-ink-2 text-[13px]">
                                            {s.received}/{s.total}
                                        </span>
                                    </div>
                                    <Bar done={s.received} total={s.total} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="card">
                    <h2 className="font-display mb-3 text-lg font-semibold">Pesanan sedang dipacking</h2>
                    {packing.length === 0 ? (
                        <EmptyState title="Tidak ada pesanan yang sedang dipacking." />
                    ) : (
                        <ul className="grid gap-3">
                            {packing.map((o) => (
                                <li key={o.id} className="border-line rounded-xl border p-3">
                                    <div className="mb-2 flex flex-wrap items-center gap-2 text-sm">
                                        <b className="num font-medium">{o.number}</b>
                                        <span className="text-ink-2">{o.customer}</span>
                                        <span className="num text-ink-2 ml-auto text-[13px]">
                                            {o.checked}/{o.total} tercentang
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Bar done={o.checked} total={o.total} />
                                        {o.checked === o.total && <Check className="text-ok size-4 shrink-0" />}
                                    </div>
                                    {!o.has_label && <p className="text-warn mt-2 text-[12.5px]">Resi belum diunggah Admin</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <section className="card mt-4">
                <h2 className="font-display mb-3 text-lg font-semibold">Aktivitas gudang terbaru</h2>
                {activity.length === 0 ? (
                    <EmptyState title="Belum ada aktivitas." />
                ) : (
                    <ul className="divide-line divide-y">
                        {activity.map((a, i) => (
                            <li key={i} className="flex flex-wrap items-center gap-2 py-2.5 text-sm">
                                <span className="font-medium">{a.what}</span>
                                <span className="text-ink-3">oleh {a.user}</span>
                                <span className="num text-ink-3 ml-auto text-[13px]">{a.at}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </ErpLayout>
    );
}
