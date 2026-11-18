import { telLink, waLink } from '@/lib/phone';
import { Phone } from 'lucide-react';

/** Ikon WhatsApp (SVG). */
export function WaIcon({ className = 'size-4' }: { className?: string }) {
    return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true" fill="none">
            <path
                d="M12 2.5a9.5 9.5 0 0 0-8.2 14.3L2.6 21.4l4.7-1.2A9.5 9.5 0 1 0 12 2.5Z"
                stroke="#25D366"
                strokeWidth="1.8"
                strokeLinejoin="round"
            />
            <path
                d="M8.9 7.6c.2-.4.5-.4.7-.4h.5c.2 0 .4 0 .5.4l.7 1.6c.1.2 0 .4-.1.6l-.4.5c.5 1 1.3 1.9 2.3 2.5l.5-.5c.2-.2.4-.2.6-.1l1.6.8c.2.1.3.3.2.6-.1.6-.6 1.2-1.1 1.3-.7.1-1.6 0-2.8-.6-1.6-.8-3.1-2.2-3.9-3.9-.5-1.1-.6-2-.4-2.7Z"
                fill="#25D366"
            />
        </svg>
    );
}

/** Tombol ikon WhatsApp; tidak tampil bila nomor kosong. */
export function WaButton({ phone, country, label = 'Chat WhatsApp' }: { phone: string | null | undefined; country?: string; label?: string }) {
    const href = waLink(phone, country);
    if (!href) return null;
    return (
        <a href={href} target="_blank" rel="noopener" aria-label={label} title={label} className="hover:bg-soft inline-grid size-9 place-items-center rounded-lg border border-line">
            <WaIcon className="size-5" />
        </a>
    );
}

/** Tombol ikon telepon; tidak tampil bila nomor kosong. */
export function TelButton({ phone, label = 'Telepon' }: { phone: string | null | undefined; label?: string }) {
    const href = telLink(phone);
    if (!href) return null;
    return (
        <a href={href} aria-label={label} title={label} className="hover:bg-soft text-ink-2 inline-grid size-9 place-items-center rounded-lg border border-line">
            <Phone className="size-[18px]" />
        </a>
    );
}
