import { rupiah } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

interface Props {
    shop: { name: string; bank: string | null; note: string | null; phone: string | null };
    invoice: {
        number: string;
        issued_at: string;
        order_number: string;
        paid: boolean;
        paid_at: string | null;
        customer: { name: string; phone: string | null; address: string | null; city: string | null };
        items: { title: string; title_ar: string | null; qty: number; price: number; total: number }[];
        subtotal: number;
        discount: number;
        shipping_fee: number;
        total: number;
    };
}

/** Halaman invoice berdiri sendiri (tanpa sidebar) agar rapi saat dicetak / disimpan sebagai PDF. */
export default function Invoice({ shop, invoice: inv }: Props) {
    return (
        <div className="min-h-screen bg-[#f4f5f3] py-8 print:bg-white print:py-0">
            <Head title={`Invoice ${inv.number}`} />
            <style>{`@media print { @page { size: A4; margin: 14mm; } .no-print { display: none !important; } }`}</style>

            <div className="no-print mx-auto mb-4 flex max-w-[780px] justify-end gap-2 px-4">
                <button onClick={() => window.print()} className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white">
                    <Printer className="size-[18px]" /> Cetak / simpan PDF
                </button>
            </div>

            <article className="num mx-auto max-w-[780px] bg-white px-10 py-10 text-[#122630] shadow-sm print:max-w-none print:p-0 print:shadow-none">
                <header className="mb-8 flex items-start gap-6">
                    <img src="/logo.png" alt="" className="size-20" />
                    <div className="flex-1">
                        <h1 className="font-display text-xl font-semibold">{shop.name}</h1>
                        {shop.phone && <p className="text-sm text-[#4b5b62]">WhatsApp {shop.phone}</p>}
                    </div>
                    <div className="text-right">
                        <p className="font-display text-2xl font-semibold tracking-wide text-[#0b5a7a]">INVOICE</p>
                        <p className="text-sm">{inv.number}</p>
                        <p className="text-sm text-[#4b5b62]">{inv.issued_at}</p>
                    </div>
                </header>

                <div className="mb-8 grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <p className="mb-1 text-xs font-medium tracking-wide text-[#7f8c91] uppercase">Ditagihkan kepada</p>
                        <p className="font-medium">{inv.customer.name}</p>
                        {inv.customer.phone && <p>{inv.customer.phone}</p>}
                        <p className="text-[#4b5b62]">{[inv.customer.address, inv.customer.city].filter(Boolean).join(', ')}</p>
                    </div>
                    <div className="text-right">
                        <p className="mb-1 text-xs font-medium tracking-wide text-[#7f8c91] uppercase">Status</p>
                        {inv.paid ? (
                            <p className="font-semibold text-[#2f8a5b]">LUNAS{inv.paid_at && ` · ${inv.paid_at}`}</p>
                        ) : (
                            <p className="font-semibold text-[#a86e14]">BELUM DIBAYAR</p>
                        )}
                        <p className="mt-1 text-[#4b5b62]">Order {inv.order_number}</p>
                    </div>
                </div>

                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b-2 border-[#122630] text-left">
                            <th className="py-2 font-semibold">Kitab</th>
                            <th className="py-2 text-right font-semibold">Jumlah</th>
                            <th className="py-2 text-right font-semibold">Harga</th>
                            <th className="py-2 text-right font-semibold">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {inv.items.map((i, k) => (
                            <tr key={k} className="border-b border-[#e3e6e3]">
                                <td className="py-2.5">
                                    {i.title}
                                    {i.title_ar && <span className="ar block text-left text-[13px] text-[#4b5b62]">{i.title_ar}</span>}
                                </td>
                                <td className="py-2.5 text-right">{i.qty}</td>
                                <td className="py-2.5 text-right">{rupiah(i.price)}</td>
                                <td className="py-2.5 text-right">{rupiah(i.total)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-4 ml-auto w-[300px] text-sm">
                    <div className="flex justify-between py-1">
                        <span className="text-[#4b5b62]">Subtotal</span>
                        <span>{rupiah(inv.subtotal)}</span>
                    </div>
                    {inv.discount > 0 && (
                        <div className="flex justify-between py-1">
                            <span className="text-[#4b5b62]">Diskon</span>
                            <span>− {rupiah(inv.discount)}</span>
                        </div>
                    )}
                    <div className="flex justify-between py-1">
                        <span className="text-[#4b5b62]">Ongkir</span>
                        <span>{rupiah(inv.shipping_fee)}</span>
                    </div>
                    <div className="mt-1 flex justify-between border-t-2 border-[#122630] pt-2 text-base font-semibold">
                        <span>Total tagihan</span>
                        <span>{rupiah(inv.total)}</span>
                    </div>
                </div>

                {(shop.bank || shop.note) && (
                    <footer className="mt-10 border-t border-[#e3e6e3] pt-4 text-sm text-[#4b5b62]">
                        {shop.bank && <p className="whitespace-pre-line">Pembayaran ke: {shop.bank}</p>}
                        {shop.note && <p className="mt-2 whitespace-pre-line">{shop.note}</p>}
                    </footer>
                )}
                <p className="mt-10 text-center text-xs text-[#7f8c91]">Jazakumullahu khairan atas kepercayaan Anda.</p>
            </article>
        </div>
    );
}
