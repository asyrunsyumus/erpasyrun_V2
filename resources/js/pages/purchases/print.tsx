import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

interface Item {
    product_id: number;
    sku: string;
    title_latin: string;
    title_ar: string | null;
    author: string | null;
    has_photo: boolean;
    alternates: string[];
    qty: number;
}
interface Group {
    maktabah: string;
    items: Item[];
    total_qty: number;
}

/** Daftar belanja siap cetak / simpan PDF untuk dibawa ke maktabah tanpa HP. */
export default function PurchasePrint({ groups, printedAt }: { groups: Group[]; printedAt: string }) {
    return (
        <div className="min-h-screen bg-[#f4f5f3] py-8 print:bg-white print:py-0">
            <Head title="Daftar belanja Mesir" />
            <style>{`@media print { @page { size: A4; margin: 12mm; } .no-print { display: none !important; } section { break-inside: avoid-page; } tr { break-inside: avoid; } }`}</style>

            <div className="no-print mx-auto mb-4 flex max-w-[820px] items-center gap-3 px-4">
                <p className="flex-1 text-sm text-[#4b5b62]">Tekan tombol di kanan, lalu pilih “Simpan sebagai PDF” atau printer.</p>
                <button onClick={() => window.print()} className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white">
                    <Printer className="size-[18px]" /> Cetak / simpan PDF
                </button>
            </div>

            <article className="num mx-auto max-w-[820px] bg-white px-8 py-8 text-[#122630] shadow-sm print:max-w-none print:p-0 print:shadow-none">
                <header className="mb-6 flex items-center gap-4 border-b-2 border-[#122630] pb-3">
                    <img src="/logo.png" alt="" className="size-12" />
                    <div className="flex-1">
                        <h1 className="font-display text-lg font-semibold">Daftar Belanja Mesir</h1>
                        <p className="text-xs text-[#4b5b62]">Dicetak {printedAt}</p>
                    </div>
                    <p className="text-sm font-medium">{groups.reduce((s, g) => s + g.total_qty, 0)} buah</p>
                </header>

                {groups.length === 0 && <p className="py-10 text-center text-[#7f8c91]">Tidak ada yang perlu dibeli.</p>}

                {groups.map((g) => (
                    <section key={g.maktabah} className="mb-6">
                        <h2 className="font-display mb-2 text-base font-semibold">
                            {g.maktabah} <span className="text-sm font-normal text-[#7f8c91]">· {g.total_qty} buah</span>
                        </h2>
                        <table className="w-full text-sm">
                            <tbody>
                                {g.items.map((it) => (
                                    <tr key={it.product_id} className="border-b border-[#e3e6e3]">
                                        <td className="w-8 py-2 align-top">
                                            <span className="inline-block size-4 rounded-sm border-[1.5px] border-[#122630]" />
                                        </td>
                                        <td className="w-14 py-2 align-top">
                                            {it.has_photo ? <img src={`/products/${it.product_id}/photo`} alt="" className="h-14 w-10 rounded object-cover" /> : <span className="block h-14 w-10 rounded bg-[#f4f6f5]" />}
                                        </td>
                                        <td className="py-2 align-top">
                                            {it.title_ar && <span className="ar block text-left text-base">{it.title_ar}</span>}
                                            <span>{it.title_latin}</span>
                                            <span className="block text-xs text-[#7f8c91]">
                                                {it.sku}
                                                {it.author && ` · ${it.author}`}
                                                {it.alternates.length > 0 && ` · cadangan: ${it.alternates.join(', ')}`}
                                            </span>
                                        </td>
                                        <td className="w-20 py-2 text-right align-top">
                                            <span className="font-display text-xl font-semibold">{it.qty}</span>
                                        </td>
                                        <td className="w-24 py-2 pl-3 align-top text-xs text-[#7f8c91]">
                                            dapat: ____
                                            <br />
                                            EGP: ____
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                ))}
            </article>
        </div>
    );
}
