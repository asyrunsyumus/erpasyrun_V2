import { Link } from '@inertiajs/react';

const items = [
    { title: 'Profil', url: '/settings/profile' },
    { title: 'Ganti password', url: '/settings/password' },
    { title: 'Verifikasi dua langkah', url: '/settings/two-factor' },
    { title: 'Tampilan', url: '/settings/appearance' },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;

    return (
        <div>
            <h1 className="mb-1 text-[26px] font-semibold">Akun saya</h1>
            <p className="text-ink-2 mb-6">Atur profil dan keamanan akun Anda.</p>

            <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                <nav className="flex gap-1 overflow-x-auto lg:w-48 lg:flex-col">
                    {items.map((item) => (
                        <Link
                            key={item.url}
                            href={item.url}
                            className={
                                'rounded-[10px] px-3 py-2 text-sm font-medium whitespace-nowrap ' +
                                (currentPath === item.url ? 'bg-brand-soft text-brand' : 'text-ink-2 hover:bg-soft')
                            }
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>
                <section className="card max-w-xl flex-1 space-y-8">{children}</section>
            </div>
        </div>
    );
}
