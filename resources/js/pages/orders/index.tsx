import { applyFilters, EmptyState, PageHeader, Pager, type Paginated, SearchBox, Table } from '@/components/erp';
import { PaymentBadge, StatusBadge } from '@/components/order-bits';
import ErpLayout from '@/layouts/erp-layout';
import { rupiah } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface Row {
    id: number;
    number: string;
    customer: string;
    customer_type: string;
    channel: string;
    payment_status: string;
    status: string;
    status_label: string;
    total: number;
    qty: number;
    has_label: boolean;
    created_at: string;
}
interface Props {
    items: Paginated<Row>;
    filters: { q: string; status: string; payment: string | null };
    statuses: { value: string; label: string }[];
}

export default function OrdersIndex({ items, filters, statuses }: Props) {
    const set = (patch: Partial<Props['filters']>) => applyFilters('/orders', { ...filters, ...patch });
    const quick = [
        { label: 'Aktif', status: 'aktif', payment: null },
        { label: 'Belum bayar', status: 'aktif', payment: 'belum' },
        { label: 'Belum ada resi', status: 'aktif', payment: 'tanpa_resi' },
        { label: 'Semua', status: '', payment: null },
    ];

    return (
        <ErpLayout>
            <Head title="Order & Invoice" />
            <PageHeader title="Order & Invoice" subtitle="Semua pesanan, dari yang baru masuk sampai selesai.">
                <Link href="/orders/create" className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white">
                    <Plus className="size-[18px]" /> Order baru
                </Link>
            </PageHeader>

            <div className="mb-4 flex flex-wrap gap-3">
                <SearchBox value={filters.q} placeholder="Cari nomor order atau nama pembeli…" onSearch={(q) => set({ q })} />
                <div className="bg-surface border-line flex h-[42px] items-center gap-0.5 rounded-[10px] border p-1">
                    {quick.map((f) => {
                        const on = (filters.status ?? '') === f.status && (filters.payment ?? null) === f.payment;
                        return (
                            <button
                                key={f.label}
                                type="button"
                                onClick={() => set({ status: f.status, payment: f.payment })}
                                className={'h-full rounded-[7px] px-3 text-sm font-medium whitespace-nowrap ' + (on ? 'bg-brand-soft text-brand' : 'text-ink-2 hover:bg-soft')}
                            >
                                {f.label}
                            </button>
                        );
                    })}
                </div>
                <select
                    className="bg-surface border-line h-[42px] rounded-[10px] border px-3 text-sm"
                    value={statuses.some((s) => s.value === filters.status) ? filters.status : ''}
                    onChange={(e) => set({ status: e.target.value || 'aktif', payment: null })}
                >
                    <option value="">Tahap: semua aktif</option>
                    {statuses.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
            </div>

            <Table
                head={
                    <>
                        <th>Order</th>
                        <th>Pembeli</th>
                        <th>Tahap</th>
                        <th>Bayar</th>
                        <th className="text-right">Kitab</th>
                        <th className="text-right">Total</th>
                    </>
                }
                empty={items.data.length === 0 && <EmptyState title="Belum ada order di sini.">Tekan “Order baru” untuk mencatat pesanan.</EmptyState>}
            >
                {items.data.map((o) => (
                    <tr key={o.id} className="hover:bg-soft cursor-pointer" onClick={() => router.visit(`/orders/${o.id}`)}>
                        <td>
                            <Link href={`/orders/${o.id}`} className="font-medium" onClick={(e) => e.stopPropagation()}>
                                {o.number}
                            </Link>
                            <small className="text-ink-3 block text-[13px]">
                                {o.created_at} · {o.channel === 'shopee' ? 'Shopee' : 'WhatsApp'}
                                {!o.has_label && o.status !== 'selesai' && o.status !== 'dibatalkan' && <span className="text-warn"> · belum ada resi</span>}
                            </small>
                        </td>
                        <td>
                            {o.customer}
                            {o.customer_type === 'reseller' && <small className="text-ink-3 block text-[13px]">Reseller</small>}
                        </td>
                        <td>
                            <StatusBadge status={o.status} label={o.status_label} />
                        </td>
                        <td>
                            <PaymentBadge status={o.payment_status} />
                        </td>
                        <td className="text-right">{o.qty}</td>
                        <td className="text-right font-medium">{rupiah(o.total)}</td>
                    </tr>
                ))}
            </Table>
            <Pager page={items} />
        </ErpLayout>
    );
}
