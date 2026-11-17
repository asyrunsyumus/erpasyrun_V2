interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div className="card w-full max-w-[400px] !p-8">
                <div className="mb-7 flex flex-col items-center gap-3 text-center">
                    <img src="/logo.png" alt="Asyrun Syumus" className="size-20" />
                    <div className="space-y-1.5">
                        <h1 className="text-xl font-semibold">{title}</h1>
                        {description && <p className="text-ink-3 text-sm">{description}</p>}
                    </div>
                </div>
                {children}
            </div>
            <p className="text-ink-3 mt-6 text-xs">ERP Maktabah Asyrun Syumus</p>
        </div>
    );
}
