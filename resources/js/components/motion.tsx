import { useEffect, useRef, useState, type ReactNode } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

/** true bila pengguna mengaktifkan "kurangi gerakan" di sistemnya. */
export function useReducedMotion(): boolean {
    const [reduced, setReduced] = useState(() => typeof window !== 'undefined' && window.matchMedia?.(QUERY).matches === true);
    useEffect(() => {
        const mq = window.matchMedia?.(QUERY);
        if (!mq) return;
        const onChange = () => setReduced(mq.matches);
        mq.addEventListener('change', onChange);
        return () => mq.removeEventListener('change', onChange);
    }, []);
    return reduced;
}

const easeOut = (t: number) => 1 - Math.pow(1 - t, 3);
const clamp = (v: number, a = 0, b = 1) => Math.min(b, Math.max(a, v));

/**
 * Tween per batang: batang ke-i mulai setelah i * stagger ms, selesai dalam dur ms.
 * Mengembalikan fungsi pembatal.
 */
function tween(n: number, stagger: number, dur: number, from: (i: number) => number, to: (i: number) => number, set: (m: number[]) => void, done?: () => void): () => void {
    let id = 0;
    const t0 = performance.now();
    const total = (n - 1) * stagger + dur;
    const step = (now: number) => {
        const el = now - t0;
        const m = Array.from({ length: n }, (_, i) => {
            const k = easeOut(clamp((el - i * stagger) / dur));
            return from(i) + (to(i) - from(i)) * k;
        });
        set(m);
        if (el < total) {
            id = requestAnimationFrame(step);
        } else {
            done?.();
        }
    };
    id = requestAnimationFrame(step);

    return () => cancelAnimationFrame(id);
}

export interface BarDatum {
    label: string;
    value: number;
    /** Lapisan kedua di dalam batang (mis. untung di dalam penjualan). */
    overlay?: number;
}

/**
 * Bar chart: batang naik dari 0 (700ms, berurutan 40ms), angka ikut naik, hover menaikkan batang
 * dan menampilkan tooltip. Ganti periode: batang lama mengecil dulu, baru yang baru tumbuh.
 */
export function BarChart({
    data,
    periodKey,
    format,
    overlayLabel,
    height = 170,
}: {
    data: BarDatum[];
    periodKey: string;
    format: (n: number) => string;
    overlayLabel?: string;
    height?: number;
}) {
    const reduced = useReducedMotion();
    const [view, setView] = useState<BarDatum[]>(data);
    const [mult, setMult] = useState<number[]>(() => data.map(() => (reduced ? 1 : 0)));
    const [hover, setHover] = useState<number | null>(null);
    const cancel = useRef<() => void>(() => {});
    const first = useRef(true);
    const multRef = useRef(mult);
    multRef.current = mult;

    useEffect(() => {
        cancel.current();
        if (reduced) {
            setView(data);
            setMult(data.map(() => 1));
            return;
        }
        if (first.current) {
            first.current = false;
            setView(data);
            cancel.current = tween(data.length, 40, 700, () => 0, () => 1, setMult);
            return;
        }
        // Ganti periode: batang lama mengecil ke 0 dulu (tanpa lompatan), baru batang baru tumbuh.
        const cur = multRef.current;
        cancel.current = tween(
            view.length,
            0,
            240,
            (i) => cur[i] ?? 0,
            () => 0,
            setMult,
            () => {
                setView(data);
                setMult(data.map(() => 0));
                cancel.current = tween(data.length, 40, 700, () => 0, () => 1, setMult);
            },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [periodKey, data]);

    useEffect(() => () => cancel.current(), []);

    const max = Math.max(1, ...view.map((d) => d.value));
    const n = view.length;
    const showValues = n <= 12;
    const labelEvery = Math.max(1, Math.ceil(n / 8));

    return (
        <div className="select-none">
            <div className="flex items-end gap-[3px] sm:gap-2" style={{ height: height + 22 }}>
                {view.map((d, i) => {
                    const m = mult[i] ?? 0;
                    const v = d.value * m;
                    const barH = Math.max(0, (v / max) * height);
                    const ov = d.overlay !== undefined ? (Math.max(0, Math.min(d.overlay, d.value)) * m / max) * height : null;
                    const on = hover === i;
                    return (
                        <div
                            key={i}
                            className="relative flex h-full flex-1 flex-col items-center justify-end"
                            onMouseEnter={() => setHover(i)}
                            onMouseLeave={() => setHover(null)}
                            onTouchStart={() => setHover(i)}
                            onTouchEnd={() => setTimeout(() => setHover((h) => (h === i ? null : h)), 1400)}
                        >
                            {showValues && (
                                <span className="num text-ink-2 mb-1 h-4 text-[11px] whitespace-nowrap" style={{ opacity: m > 0.2 ? 1 : 0 }}>
                                    {format(Math.round(v))}
                                </span>
                            )}
                            <div
                                className="bg-[#c9d9e0] dark:bg-[#2a434c] relative w-full max-w-[52px] origin-bottom rounded-t-md transition duration-200 ease-out"
                                style={{
                                    height: barH,
                                    transform: on && !reduced ? 'scaleX(1.07)' : undefined,
                                    filter: on ? 'brightness(1.08)' : undefined,
                                }}
                            >
                                {ov !== null && <div className="bg-brand absolute inset-x-0 bottom-0 rounded-t-md" style={{ height: ov }} />}
                            </div>
                            {on && m > 0.5 && (
                                <div
                                    className="bg-ink num pointer-events-none absolute left-1/2 z-10 -translate-x-1/2 rounded-md px-2 py-1 text-[11.5px] whitespace-nowrap text-white shadow-md"
                                    style={{ bottom: barH + (showValues ? 26 : 8) }}
                                >
                                    <b className="font-semibold">{d.label}</b>: {format(d.value)}
                                    {d.overlay !== undefined && overlayLabel && (
                                        <span className="block text-[#cbe1ea]">
                                            {overlayLabel} {format(d.overlay)}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
            <div className="mt-2 flex gap-[3px] sm:gap-2">
                {view.map((d, i) => (
                    <span key={i} className={'num flex-1 text-center text-[11.5px] ' + (hover === i ? 'text-ink font-semibold' : 'text-ink-2')}>
                        {i % labelEvery === 0 || i === n - 1 ? d.label : ''}
                    </span>
                ))}
            </div>
        </div>
    );
}

/** Angka yang ikut naik dari nilai sebelumnya. */
export function CountUp({ value, format = (n: number) => String(Math.round(n)), duration = 650 }: { value: number; format?: (n: number) => string; duration?: number }) {
    const reduced = useReducedMotion();
    const [shown, setShown] = useState(reduced ? value : 0);
    const last = useRef(reduced ? value : 0);

    useEffect(() => {
        if (reduced) {
            last.current = value;
            setShown(value);
            return;
        }
        const from = last.current;
        const t0 = performance.now();
        let id = 0;
        const step = (now: number) => {
            const k = easeOut(clamp((now - t0) / duration));
            const cur = from + (value - from) * k;
            last.current = cur;
            setShown(cur);
            if (k < 1) id = requestAnimationFrame(step);
        };
        id = requestAnimationFrame(step);

        return () => cancelAnimationFrame(id);
    }, [value, reduced, duration]);

    return <>{format(shown)}</>;
}

/** Bungkus halaman: naik-pudar halus sekali saat halaman dibuka. */
export function Enter({ children }: { children: ReactNode }) {
    return <div className="erp-enter">{children}</div>;
}
