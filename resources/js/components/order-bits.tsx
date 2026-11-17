/** Lencana status pesanan — warna hanya untuk status (prinsip desain). */
export function StatusBadge({ status, label }: { status: string; label: string }) {
    const tone: Record<string, string> = {
        menunggu_bayar: 'bg-warn-soft text-warn',
        belanja_mesir: 'bg-gold-soft text-gold-ink',
        menunggu_bagasi: 'bg-gold-soft text-gold-ink',
        dalam_bagasi: 'bg-brand-soft text-brand',
        siap_packing: 'bg-brand-soft text-brand',
        selesai: 'bg-ok-soft text-ok',
        menunggu_pembatalan: 'bg-bad-soft text-bad',
        dibatalkan: 'bg-soft text-ink-3',
    };
    return (
        <span className={'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[13px] font-medium whitespace-nowrap ' + (tone[status] ?? 'bg-soft text-ink-2')}>
            <i className="size-[7px] rounded-full bg-current" />
            {label}
        </span>
    );
}

export function PaymentBadge({ status }: { status: string }) {
    if (status === 'paid') return <span className="text-ok text-[13px] font-medium">Lunas</span>;
    if (status === 'trusted') return <span className="text-gold-ink text-[13px] font-medium">Dibelikan dulu</span>;
    return <span className="text-warn text-[13px] font-medium">Belum bayar</span>;
}
