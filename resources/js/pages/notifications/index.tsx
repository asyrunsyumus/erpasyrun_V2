import { Btn, PageHeader } from '@/components/erp';
import ErpLayout from '@/layouts/erp-layout';
import { Head, router } from '@inertiajs/react';

interface N {
    id: string;
    message: string;
    href: string | null;
    read: boolean;
    at: string;
}

export default function Notifications({ items }: { items: N[] }) {
    const unread = items.filter((n) => !n.read).length;
    return (
        <ErpLayout>
            <Head title="Notifikasi" />
            <PageHeader title="Notifikasi" subtitle="Hanya untuk Anda. Menandai sudah dibaca tidak menghilangkannya dari rekan lain.">
                {unread > 0 && <Btn onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}>Tandai semua dibaca</Btn>}
            </PageHeader>
            <div className="card !p-0">
                {items.length === 0 && <p className="text-ink-3 py-12 text-center text-sm">Belum ada notifikasi.</p>}
                {items.map((n) => (
                    <a key={n.id} href={`/notifications/${n.id}/open`} className={'border-line hover:bg-soft flex items-start gap-3 border-b px-5 py-4 last:border-0 ' + (n.read ? '' : 'bg-brand-soft/40')}>
                        <span className={'mt-1.5 size-2 shrink-0 rounded-full ' + (n.read ? 'bg-transparent' : 'bg-brand')} />
                        <span className="flex-1">
                            <span className={n.read ? 'text-ink-2' : 'font-medium'}>{n.message}</span>
                            <small className="text-ink-3 block text-[12.5px]">{n.at}</small>
                        </span>
                    </a>
                ))}
            </div>
        </ErpLayout>
    );
}
