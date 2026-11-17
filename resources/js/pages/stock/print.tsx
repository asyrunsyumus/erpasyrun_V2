import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';

interface Row {
    sku: string;
    title_latin: string;
    title_ar: string | null;
    author: string | null;
    publisher: string | null;
    weight_grams: number | null;
    category: string | null;
    qty: number;
}

const HEAD: Record<string, string> = { sku: 'SKU', judul: 'Judul', arab: 'Arab', penulis: 'Penulis', penerbit: 'Penerbit', kategori: 'Kategori', berat: 'Berat', qty: 'Stok', hitung: 'Hitung fisik' };

export default function StockPrint({ rows, cols, printedAt }: { rows: Row[]; cols: string[]; printedAt: string }) {
    const cell = (r: Row, c: string) => {
        switch (c) {
            case 'sku':
                return r.sku;
            case 'judul':
                return r.title_latin;
            case 'arab':
                return <span className="ar block text-left">{r.title_ar}</span>;
            case 'penulis':
                return r.author;
            case 'penerbit':
                return r.publisher;
            case 'kategori':
                return r.category;
            case 'berat':
                return r.weight_grams ? `${r.weight_grams} g` : '—';
            case 'qty':
                return <b>{r.qty}</b>;
            case 'hitung':
                return <span className="inline-block h-5 w-16 border-b border-[#122630]" />;
        }
    };
    return (
        <div className="min-h-screen bg-[#f4f5f3] py-8 print:bg-white print:py-0">
            <Head title="Daftar stok" />
            <style>{`@media print { @page { size: A4; margin: 12mm; } .no-print { display: none !important; } tr { break-inside: avoid; } }`}</style>
            <div className="no-print mx-auto mb-4 flex max-w-[860px] justify-end px-4">
                <button onClick={() => window.print()} className="bg-brand inline-flex h-[42px] items-center gap-2 rounded-[10px] px-[18px] font-medium text-white">
                    <Printer className="size-[18px]" /> Cetak / simpan PDF
                </button>
            </div>
            <article className="num mx-auto max-w-[860px] bg-white px-8 py-8 text-[#122630] shadow-sm print:max-w-none print:p-0 print:shadow-none">
                <header className="mb-4 flex items-center gap-4 border-b-2 border-[#122630] pb-3">
                    <img src="/logo.png" alt="" className="size-12" />
                    <div className="flex-1">
                        <h1 className="font-display text-lg font-semibold">Stok Ready — Gudang Indonesia</h1>
                        <p className="text-xs text-[#4b5b62]">Dicetak {printedAt}</p>
                    </div>
                    <p className="text-sm font-medium">
                        {rows.length} judul · {rows.reduce((s, r) => s + Number(r.qty), 0)} buah
                    </p>
                </header>
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-[#122630] text-left">
                            {cols.map((c) => (
                                <th key={c} className={'py-1.5 font-semibold ' + (c === 'qty' || c === 'berat' ? 'text-right' : '')}>
                                    {HEAD[c]}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={i} className="border-b border-[#e3e6e3]">
                                {cols.map((c) => (
                                    <td key={c} className={'py-1.5 pr-2 ' + (c === 'qty' || c === 'berat' ? 'text-right' : '')}>
                                        {cell(r, c)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </article>
        </div>
    );
}
