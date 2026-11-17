import ErpLayout from '@/layouts/erp-layout';
import { kg, rupiahShort } from '@/lib/format';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface Task {
    count: number;
    title: string;
    detail: string;
    action: string;
    href: string;
    urgent?: boolean;
}
interface Stage {
    key: string;
    label: string;
    count: number;
    avg_days: number;
    oldest_days: number;
    slow: boolean;
    done?: boolean;
}
interface Kpi {
    value: number;
    change?: number | null;
    orders?: number;
    over_30?: number;
    customers?: number;
}
interface Props {
    today: string;
    tasks: Task[];
    period?: string;
    kpis?: { sales: Kpi; cash: Kpi; profit: Kpi; receivable: Kpi };
    pipeline?: Stage[];
    series?: { label: string; sales: number; profit: number }[];
    baggage?: { date: string; days_left: number; contact: string; used_grams: number; quota_grams: number | null; orders: number } | null;
    waiting_grams?: number;
    waiting_orders?: number;
    debtors?: { name: string; type: string; amount: number; days: number }[];
    top_products?: { title_ar: string | null; title_latin: string; sold: number; profit: number; margin: number }[];
    [key: string]: unknown;
}

function greeting() {
    const h = new Date().getHours();
    if (h < 11) return 'Selamat pagi';
    if (h < 15) return 'Selamat siang';
    if (h < 18) return 'Selamat sore';
    return 'Selamat malam';
}

function Change({ value, invert = false }: { value?: number | null; invert?: boolean }) {
    if (value === null || value === undefined) return <span>belum ada pembanding</span>;
    const good = invert ? value <= 0 : value >= 0;
    return <span className={good ? 'text-ok font-semibold' : 'text-bad font-semibold'}>{value >= 0 ? '▲' : '▼'} {Math.abs(value)}%</span>;
}

function Empty({ children }: { children: React.ReactNode }) {
    return <p className="text-ink-3 py-6 text-center text-sm">{children}</p>;
}

function SalesChart({ series }: { series: NonNullable<Props['series']> }) {
    const max = Math.max(1, ...series.map((s) => s.sales));
    const H = 170;
    return (
        <div className="num">
            <div className="flex h-[190px] items-end gap-3 sm:gap-6">
                {series.map((s, i) => {
                    const last = i === series.length - 1;
                    const sh = (s.sales / max) * H;
                    const ph = Math.max(0, (s.profit / max) * H);
                    return (
                        <div key={s.label} className="flex flex-1 flex-col items-center justify-end gap-1">
                            {last && s.sales > 0 && <span className="text-xs font-semibold">{rupiahShort(s.sales).replace('Rp ', '')}</span>}
                            <div className="relative w-full max-w-[52px] rounded-md bg-[#c9d9e0] dark:bg-[#2a434c]" style={{ height: Math.max(sh, 2) }} title={`Penjualan ${rupiahShort(s.sales)}`}>
                                <div className="bg-brand absolute inset-x-0 bottom-0 rounded-md" style={{ height: ph }} title={`Untung ${rupiahShort(s.profit)}`} />
                            </div>
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex gap-3 sm:gap-6">
                {series.map((s, i) => (
                    <span key={s.label} className={'flex-1 text-center text-xs ' + (i === series.length - 1 ? 'text-ink font-semibold' : 'text-ink-2')}>
                        {s.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

export default function Dashboard(props: Props) {
    const { auth } = usePage<SharedData>().props;
    const role = auth.user.role;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <ErpLayout>
            <Head title="Beranda" />

            <div className="mb-6 flex flex-wrap items-center gap-3">
                <div className="min-w-[240px] flex-1">
                    <h1 className="text-[26px] font-semibold">
                        {greeting()}, {firstName}
                    </h1>
                    <p className="text-ink-2 mt-1">{props.today}</p>
                </div>
                {(role === 'owner' || role === 'admin') && (
                    <Link href="/orders" className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white hover:opacity-95">
                        <Plus className="size-[18px]" /> Buat order baru
                    </Link>
                )}
            </div>

            {/* Tugas hari ini */}
            <section className="card mb-6">
                <h3 className="mb-4 text-base font-semibold">Tugas Anda hari ini</h3>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {props.tasks.map((t) => {
                        const hot = t.urgent && t.count > 0;
                        return (
                            <Link
                                key={t.title}
                                href={t.href}
                                className={
                                    'flex flex-col gap-1 rounded-xl border p-4 transition-colors ' +
                                    (hot ? 'border-gold bg-gold-soft' : 'border-line hover:border-brand')
                                }
                            >
                                <span className={'font-display num text-[28px] font-semibold ' + (hot ? 'text-gold-ink' : t.count === 0 ? 'text-ink-3' : '')}>{t.count}</span>
                                <span className="font-medium">{t.title}</span>
                                <span className="text-ink-3 text-[13.5px]">{t.detail}</span>
                                <span className="text-brand mt-auto pt-2.5 text-sm font-medium">{t.action} →</span>
                            </Link>
                        );
                    })}
                </div>
            </section>

            {props.kpis && (
                <>
                    <div className="mb-3 flex flex-wrap items-baseline gap-2.5">
                        <h3 className="text-base font-semibold">Keuangan bulan ini</h3>
                        <span className="text-ink-3 text-[13.5px]">{props.period}</span>
                    </div>
                    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="bg-brand rounded-[14px] px-5 py-[18px] text-white">
                            <div className="text-sm text-[#cbe1ea]">Penjualan</div>
                            <div className="font-display num my-1.5 text-[27px] font-semibold">{rupiahShort(props.kpis.sales.value)}</div>
                            <div className="text-[13.5px] text-[#cbe1ea]">
                                {props.kpis.sales.change != null && <span className="font-semibold text-[#9fe0bb]">{props.kpis.sales.change >= 0 ? '▲' : '▼'} {Math.abs(props.kpis.sales.change)}% · </span>}
                                {props.kpis.sales.orders} pesanan
                            </div>
                        </div>
                        <div className="card !py-[18px]">
                            <div className="text-ink-2 text-sm">Uang masuk</div>
                            <div className="font-display num my-1.5 text-[27px] font-semibold">{rupiahShort(props.kpis.cash.value)}</div>
                            <div className="text-ink-3 text-[13.5px]">
                                <Change value={props.kpis.cash.change} /> · dari invoice lunas
                            </div>
                        </div>
                        <div className="card !py-[18px]">
                            <div className="text-ink-2 text-sm">Untung bersih</div>
                            <div className={'font-display num my-1.5 text-[27px] font-semibold ' + (props.kpis.profit.value < 0 ? 'text-bad' : '')}>{rupiahShort(props.kpis.profit.value)}</div>
                            <div className="text-ink-3 text-[13.5px]">
                                <Change value={props.kpis.profit.change} /> · setelah semua biaya
                            </div>
                        </div>
                        <div className="card !py-[18px]">
                            <div className="text-ink-2 text-sm">Belum dibayar pembeli</div>
                            <div className="font-display num my-1.5 text-[27px] font-semibold">{rupiahShort(props.kpis.receivable.value)}</div>
                            <div className="text-ink-3 text-[13.5px]">
                                {props.kpis.receivable.customers} customer
                                {(props.kpis.receivable.over_30 ?? 0) > 0 && <span className="text-bad font-semibold"> · {rupiahShort(props.kpis.receivable.over_30!)} &gt; 30 hari</span>}
                            </div>
                        </div>
                    </div>
                </>
            )}

            {props.pipeline && (
                <section className="card mb-4">
                    <div className="mb-4 flex flex-wrap items-baseline gap-2.5">
                        <h3 className="text-base font-semibold">Posisi pesanan sekarang</h3>
                        <span className="text-ink-3 text-[13.5px]">Kotak kuning = tertahan terlalu lama.</span>
                    </div>
                    <div className="border-line num grid grid-cols-2 overflow-hidden rounded-xl border sm:grid-cols-3 xl:grid-cols-6">
                        {props.pipeline.map((s, i) => (
                            <div key={s.key} className={'border-line flex flex-col gap-0.5 border-r border-b px-3.5 py-4 ' + (s.slow ? 'bg-warn-soft' : '')}>
                                <span className="text-ink-2 text-[13.5px] font-medium">
                                    {s.done ? '' : `${i + 1}. `}
                                    {s.label}
                                </span>
                                <span className={'font-display text-[26px] font-semibold ' + (s.done && s.count > 0 ? 'text-ok' : s.count === 0 ? 'text-ink-3' : '')}>{s.count}</span>
                                <span className={'text-[13px] ' + (s.slow ? 'text-warn font-medium' : 'text-ink-3')}>
                                    {s.count === 0 ? '—' : s.done ? `rata-rata ${s.avg_days} hari` : `± ${s.avg_days} hari${s.slow ? ' · tertahan' : ''}`}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {props.series && (
                <div className="mb-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.5fr_1fr]">
                    <section className="card">
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <h3 className="flex-1 text-base font-semibold">Penjualan & untung 6 bulan</h3>
                            <span className="text-ink-3 flex items-center gap-1.5 text-[13.5px]">
                                <i className="inline-block size-2.5 rounded-[3px] bg-[#c9d9e0]" />
                                Penjualan
                            </span>
                            <span className="text-ink-3 flex items-center gap-1.5 text-[13.5px]">
                                <i className="bg-brand inline-block size-2.5 rounded-[3px]" />
                                Untung bersih
                            </span>
                        </div>
                        <SalesChart series={props.series} />
                    </section>

                    <section className="card">
                        <div className="mb-4 flex items-center gap-3">
                            <h3 className="flex-1 text-base font-semibold">Bagasi berikutnya</h3>
                            <Link href="/baggage" className="border-line hover:border-ink-3 inline-flex h-[34px] items-center gap-1.5 rounded-[10px] border px-3 text-sm font-medium">
                                <Plus className="size-4" /> Jadwal baru
                            </Link>
                        </div>
                        {props.baggage ? (
                            <>
                                <p className="font-display num text-[22px] font-semibold">{props.baggage.date}</p>
                                <p className="text-ink-3 text-[13.5px]">
                                    {props.baggage.contact} · {props.baggage.days_left === 0 ? 'hari ini' : `${props.baggage.days_left} hari lagi`}
                                </p>
                                {props.baggage.quota_grams ? (
                                    <>
                                        <div className="bg-soft mt-3 mb-1 h-2.5 overflow-hidden rounded-md">
                                            <i className="bg-brand block h-full rounded-md" style={{ width: `${Math.min(100, (props.baggage.used_grams / props.baggage.quota_grams) * 100)}%` }} />
                                        </div>
                                        <p className="text-ink-3 num text-[13.5px]">
                                            {kg(props.baggage.used_grams)} terpakai dari {kg(props.baggage.quota_grams)} · sisa {kg(Math.max(0, props.baggage.quota_grams - props.baggage.used_grams))}
                                        </p>
                                    </>
                                ) : (
                                    <p className="text-ink-3 num mt-3 text-[13.5px]">{kg(props.baggage.used_grams)} terpakai · kuota tanpa batas</p>
                                )}
                                <div className="text-ink-2 num mt-2.5 flex justify-between py-2">
                                    <span>Pesanan ikut</span>
                                    <b className="text-ink font-medium">{props.baggage.orders} pesanan</b>
                                </div>
                            </>
                        ) : (
                            <p className="text-ink-3 py-2 text-sm">Belum ada jadwal bagasi yang akan datang.</p>
                        )}
                        <div className="text-ink-2 num flex justify-between py-2">
                            <span>Masih menunggu jadwal</span>
                            <b className={'font-medium ' + ((props.waiting_orders ?? 0) > 0 ? 'text-warn' : 'text-ink')}>
                                {props.waiting_orders} pesanan · {kg(props.waiting_grams ?? 0)}
                            </b>
                        </div>
                    </section>
                </div>
            )}

            {props.debtors && (
                <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1.5fr]">
                    <section className="card">
                        <h3 className="mb-3 text-base font-semibold">Belum dibayar pembeli</h3>
                        {props.debtors.length === 0 ? (
                            <Empty>Semua tagihan sudah lunas.</Empty>
                        ) : (
                            <table className="num w-full">
                                <tbody>
                                    {props.debtors.map((d) => (
                                        <tr key={d.name} className="border-line border-b last:border-0">
                                            <td className="py-3">
                                                {d.name}
                                                {d.type === 'reseller' && <small className="text-ink-3 block text-[13px]">Reseller</small>}
                                            </td>
                                            <td className="py-3 text-right">
                                                {rupiahShort(d.amount)}
                                                <small className={'block text-[13px] ' + (d.days > 30 ? 'text-bad' : 'text-ink-3')}>{d.days} hari</small>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                    <section className="card">
                        <h3 className="mb-3 text-base font-semibold">Kitab paling untung bulan ini</h3>
                        {props.top_products!.length === 0 ? (
                            <Empty>Belum ada penjualan bulan ini.</Empty>
                        ) : (
                            <table className="num w-full">
                                <thead>
                                    <tr className="text-ink-3 border-line border-b text-left text-[13px]">
                                        <th className="py-2.5 font-medium">Kitab</th>
                                        <th className="py-2.5 text-right font-medium">Terjual</th>
                                        <th className="py-2.5 text-right font-medium">Untung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.top_products!.map((p) => (
                                        <tr key={p.title_latin} className="border-line border-b last:border-0">
                                            <td className="py-3">
                                                {p.title_ar && <span className="ar block text-left text-base">{p.title_ar}</span>}
                                                <small className="text-ink-3 text-[13px]">{p.title_latin}</small>
                                            </td>
                                            <td className="py-3 text-right">{p.sold}</td>
                                            <td className="py-3 text-right">
                                                {rupiahShort(p.profit)}
                                                <small className="text-ink-3 block text-[13px]">{p.margin}%</small>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                </div>
            )}
        </ErpLayout>
    );
}
