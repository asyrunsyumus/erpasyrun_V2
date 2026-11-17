import { PageHeader, Segmented } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah, rupiahShort } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { Download, Info } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BarChart, CountUp, type BarDatum } from '@/components/motion';

type Tab = 'penjualan' | 'margin' | 'hutang' | 'stok' | 'reseller';
interface Props {
    tab: Tab;
    from: string;
    to: string;
    summary: { sales: number; orders: number; cash: number; cogs: number; expenses: number; profit: number; receivable: number; unfinal: number };
    data: { rows: Record<string, string | number | null>[]; daily?: { date: string; sales: number; orders: number }[] };
}

const TABS: { value: Tab; label: string }[] = [
    { value: 'penjualan', label: 'Penjualan' },
    { value: 'margin', label: 'Margin per kitab' },
    { value: 'hutang', label: 'Hutang pembeli' },
    { value: 'stok', label: 'Nilai stok' },
    { value: 'reseller', label: 'Performa reseller' },
];

/** Kolom per tab: [kunci, judul, jenis]. */
const COLS: Record<Tab, [string, string, 'text' | 'num' | 'rp' | 'pct' | 'ar'][]> = {
    penjualan: [
        ['tanggal', 'Tanggal', 'text'],
        ['order', 'Order', 'text'],
        ['pembeli', 'Pembeli', 'text'],
        ['saluran', 'Saluran', 'text'],
        ['subtotal', 'Subtotal', 'rp'],
        ['diskon', 'Diskon', 'rp'],
        ['penjualan_bersih', 'Penjualan bersih', 'rp'],
        ['bayar', 'Bayar', 'text'],
    ],
    margin: [
        ['kitab', 'Kitab', 'text'],
        ['arab', '', 'ar'],
        ['terjual', 'Terjual', 'num'],
        ['penjualan_bersih', 'Penjualan', 'rp'],
        ['modal', 'Modal', 'rp'],
        ['untung', 'Untung', 'rp'],
        ['margin_persen', 'Margin', 'pct'],
    ],
    hutang: [
        ['order', 'Order', 'text'],
        ['pembeli', 'Pembeli', 'text'],
        ['telepon', 'Telepon', 'text'],
        ['jenis', 'Jenis', 'text'],
        ['umur_hari', 'Umur (hari)', 'num'],
        ['tagihan', 'Tagihan', 'rp'],
    ],
    stok: [
        ['kitab', 'Kitab', 'text'],
        ['arab', '', 'ar'],
        ['stok', 'Stok', 'num'],
        ['modal_rata', 'Modal rata-rata', 'rp'],
        ['nilai_stok', 'Nilai stok', 'rp'],
        ['terakhir_bergerak', 'Terakhir bergerak', 'text'],
    ],
    reseller: [
        ['reseller', 'Reseller', 'text'],
        ['kota', 'Kota', 'text'],
        ['jumlah_order', 'Order', 'num'],
        ['penjualan_bersih', 'Penjualan', 'rp'],
        ['sudah_bayar', 'Sudah bayar', 'rp'],
        ['belum_bayar', 'Belum bayar', 'rp'],
        ['order_terakhir', 'Order terakhir', 'text'],
    ],
};

function iso(d: Date) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
const now = new Date();
const PRESETS = [
    { label: 'Bulan ini', from: iso(new Date(now.getFullYear(), now.getMonth(), 1)), to: iso(now) },
    { label: 'Bulan lalu', from: iso(new Date(now.getFullYear(), now.getMonth() - 1, 1)), to: iso(new Date(now.getFullYear(), now.getMonth(), 0)) },
    { label: '3 bulan', from: iso(new Date(now.getFullYear(), now.getMonth() - 2, 1)), to: iso(now) },
    { label: 'Tahun ini', from: iso(new Date(now.getFullYear(), 0, 1)), to: iso(now) },
];

type Period = 'harian' | 'pekanan' | 'bulanan';

/** Mengelompokkan rekap harian menjadi per hari / per minggu (Senin) / per bulan. */
function bucket(daily: { date: string; sales: number }[], period: Period): BarDatum[] {
    if (period === 'harian') {
        return daily.map((d) => ({ label: d.date.slice(8, 10) + '/' + d.date.slice(5, 7), value: d.sales }));
    }
    const map = new Map<string, BarDatum>();
    for (const d of daily) {
        const dt = new Date(d.date + 'T00:00:00');
        let key: string;
        let label: string;
        if (period === 'pekanan') {
            const mon = new Date(dt);
            mon.setDate(dt.getDate() - ((dt.getDay() + 6) % 7));
            key = iso(mon);
            label = 'Mg ' + mon.getDate() + '/' + (mon.getMonth() + 1);
        } else {
            key = d.date.slice(0, 7);
            label = dt.toLocaleDateString('id-ID', { month: 'short', year: '2-digit' });
        }
        const cur = map.get(key) ?? { label, value: 0 };
        cur.value += d.sales;
        map.set(key, cur);
    }
    return [...map.values()];
}

function Daily({ daily }: { daily: NonNullable<Props['data']['daily']> }) {
    const [period, setPeriod] = useState<Period>('harian');
    const data = useMemo(() => bucket(daily, period), [daily, period]);
    if (daily.length < 2) return null;
    return (
        <div className="card mb-4">
            <div className="mb-3 flex flex-wrap items-center gap-3">
                <p className="flex-1 text-sm font-medium">Penjualan bersih</p>
                <Segmented
                    value={period}
                    options={[
                        { value: 'harian', label: 'Harian' },
                        { value: 'pekanan', label: 'Pekanan' },
                        { value: 'bulanan', label: 'Bulanan' },
                    ]}
                    onChange={setPeriod}
                />
            </div>
            <BarChart data={data} periodKey={period} format={rupiahShort} height={150} />
        </div>
    );
}

export default function Reports({ tab, from, to, summary, data }: Props) {
    const go = (p: Partial<{ tab: Tab; from: string; to: string }>) => router.get('/reports', { tab, from, to, ...p }, { preserveScroll: true, preserveState: true });
    const cols = COLS[tab];
    const rangeless = tab === 'hutang' || tab === 'stok';

    const fmt = (v: string | number | null, k: (typeof cols)[number][2]) => {
        if (v === null || v === '') return <span className="text-ink-3">—</span>;
        if (k === 'rp') return rupiah(Number(v));
        if (k === 'pct') return `${v}%`;
        if (k === 'ar') return <span className="ar block text-left">{v}</span>;
        return v;
    };
    const totalOf = (key: string) => data.rows.reduce((s, r) => s + Number(r[key] ?? 0), 0);

    return (
        <ErpLayout>
            <Head title="Laporan" />
            <PageHeader title="Laporan" subtitle="Semua angka memakai rumus yang sama dengan Beranda, jadi tidak ada lagi angka yang berbeda antar layar.">
                <a href={`/reports/export?${new URLSearchParams({ tab, from, to })}`} className="border-line hover:border-ink-3 inline-flex h-[42px] items-center gap-2 rounded-[10px] border px-[18px] font-medium">
                    <Download className="size-[18px]" /> Unduh Excel (CSV)
                </a>
            </PageHeader>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                {PRESETS.map((p) => (
                    <button
                        key={p.label}
                        onClick={() => go({ from: p.from, to: p.to })}
                        className={'h-[38px] rounded-[10px] border px-3 text-sm font-medium ' + (p.from === from && p.to === to ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-surface text-ink-2')}
                    >
                        {p.label}
                    </button>
                ))}
                <span className="text-ink-3 mx-1 text-sm">atau</span>
                <input type="date" value={from} max={to} onChange={(e) => go({ from: e.target.value })} className="bg-surface border-line h-[38px] rounded-[10px] border px-2 text-sm" />
                <span className="text-ink-3">–</span>
                <input type="date" value={to} min={from} onChange={(e) => go({ to: e.target.value })} className="bg-surface border-line h-[38px] rounded-[10px] border px-2 text-sm" />
            </div>

            <div className="num mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                {[
                    ['Penjualan bersih', summary.sales, `${summary.orders} pesanan`],
                    ['Uang masuk', summary.cash, 'lunas − refund'],
                    ['Modal (HPP)', summary.cogs, 'termasuk bagasi'],
                    ['Biaya', summary.expenses, 'operasional + lain'],
                    ['Untung bersih', summary.profit, 'penjualan − modal − biaya'],
                    ['Piutang hari ini', summary.receivable, 'belum dibayar'],
                ].map(([label, v, hint], i) => (
                    <div key={label as string} className={'rounded-[14px] border px-4 py-3.5 ' + (i === 4 ? 'bg-brand border-brand text-white' : 'card !p-0 !px-4 !py-3.5')}>
                        <div className={'text-[13px] ' + (i === 4 ? 'text-[#cbe1ea]' : 'text-ink-2')}>{label}</div>
                        <div className={'font-display my-0.5 text-xl font-semibold ' + (i === 4 && Number(v) < 0 ? 'text-[#ffb4ae]' : '')}>
                            <CountUp value={Number(v)} format={rupiahShort} />
                        </div>
                        <div className={'text-[12px] ' + (i === 4 ? 'text-[#cbe1ea]' : 'text-ink-3')}>{hint}</div>
                    </div>
                ))}
            </div>

            {summary.unfinal > 0 && (
                <div className="bg-gold-soft text-gold-ink mb-4 flex items-start gap-2.5 rounded-xl px-4 py-3 text-sm">
                    <Info className="mt-0.5 size-[18px] shrink-0" />
                    <span>
                        {summary.unfinal} kitab terjual di periode ini modalnya belum final (belum dibeli di Mesir atau belum masuk jadwal bagasi), jadi dihitung Rp 0 dulu. Untung bersih akan turun begitu modalnya lengkap.
                    </span>
                </div>
            )}

            <div className="mb-4 overflow-x-auto">
                <Segmented value={tab} options={TABS} onChange={(t) => go({ tab: t })} />
            </div>
            {rangeless && <p className="text-ink-3 -mt-2 mb-3 text-[13px]">Tab ini menampilkan keadaan hari ini, tidak terpengaruh rentang tanggal.</p>}

            {tab === 'penjualan' && data.daily && <Daily daily={data.daily} />}

            <div className="card overflow-x-auto !p-0">
                <table className="num w-full min-w-[720px] text-sm">
                    <thead>
                        <tr className="text-ink-3 border-line border-b text-left text-[13px] [&>th]:px-4 [&>th]:py-3 [&>th]:font-medium">
                            {cols.map(([k, l, t]) => (
                                <th key={k} className={t === 'rp' || t === 'num' || t === 'pct' ? 'text-right' : ''}>
                                    {l}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {data.rows.map((r, i) => (
                            <tr key={i} className="border-line border-b last:border-0 [&>td]:px-4 [&>td]:py-2.5">
                                {cols.map(([k, , t]) => (
                                    <td key={k} className={(t === 'rp' || t === 'num' || t === 'pct' ? 'text-right ' : '') + (k === 'untung' && Number(r[k]) < 0 ? 'text-bad' : '')}>
                                        {k === 'order' && r.order_id ? (
                                            <Link href={`/orders/${r.order_id}`} className="text-brand font-medium">
                                                {r[k]}
                                            </Link>
                                        ) : (
                                            fmt(r[k], t)
                                        )}
                                        {k === 'umur_hari' && Number(r[k]) > 30 && <span className="text-bad"> ●</span>}
                                        {k === 'modal' && Number(r.modal_belum_final) > 0 && <small className="text-gold-ink block">{r.modal_belum_final} belum final</small>}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                    {data.rows.length > 0 && (
                        <tfoot>
                            <tr className="border-line bg-soft border-t font-semibold [&>td]:px-4 [&>td]:py-3">
                                {cols.map(([k, , t], i) => (
                                    <td key={k} className={t === 'rp' || t === 'num' ? 'text-right' : ''}>
                                        {i === 0 ? `Total (${data.rows.length})` : t === 'rp' ? rupiah(totalOf(k)) : t === 'num' && k !== 'umur_hari' ? totalOf(k) : ''}
                                    </td>
                                ))}
                            </tr>
                        </tfoot>
                    )}
                </table>
                {data.rows.length === 0 && <p className="text-ink-3 py-12 text-center text-sm">Tidak ada data di periode ini.</p>}
            </div>
        </ErpLayout>
    );
}
