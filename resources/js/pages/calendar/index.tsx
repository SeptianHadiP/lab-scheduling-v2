import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type EventRow = {
    date: string;
    start_time: string;
    end_time: string;
    status: 'terpakai' | 'menunggu';
    mine: boolean;
    reservation_id?: number;
};

type Slot = { id: number; name: string; start_time: string; end_time: string };

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

const pad = (n: number) => String(n).padStart(2, '0');
const iso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

const parse = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);

    return new Date(y, m - 1, d);
};

const monthTitle = (month: string) => {
    const [y, m] = month.split('-').map(Number);

    return new Date(y, m - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};

const shiftMonth = (month: string, delta: number) => {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
};

const chipClass = (e: EventRow) =>
    e.status === 'terpakai'
        ? 'bg-primary/15 text-foreground'
        : 'bg-yellow-500/15 text-yellow-600 dark:text-yellow-400';

export default function CalendarPage({
    labs,
    selectedLabId,
    month,
    gridStart,
    gridEnd,
    today,
    events,
    slots,
}: {
    labs: { id: number; name: string }[];
    selectedLabId: number | null;
    month: string;
    gridStart: string;
    gridEnd: string;
    today: string;
    events: EventRow[];
    slots: Slot[];
}) {
    const [selected, setSelected] = useState(today);

    const go = (next: { lab?: number | null; month?: string }) => {
        const lab = next.lab === undefined ? selectedLabId : next.lab;
        const query: Record<string, string> = { month: next.month ?? month };

        if (lab) {
            query.lab = String(lab);
        }

        router.get('/calendar', query, { preserveState: true, preserveScroll: true, replace: true });
    };

    const days: string[] = [];
    for (let d = parse(gridStart); iso(d) <= gridEnd; d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)) {
        days.push(iso(d));
    }

    const byDate = events.reduce<Record<string, EventRow[]>>((acc, e) => {
        (acc[e.date] ??= []).push(e);

        return acc;
    }, {});

    const selectedDate = selected.startsWith(month) ? selected : `${month}-01`;
    const dayEvents = byDate[selectedDate] ?? [];

    const slotStatus = (s: Slot) => {
        const hit = dayEvents.filter((e) => e.start_time < s.end_time && e.end_time > s.start_time);

        if (hit.some((e) => e.status === 'terpakai')) {
            return 'Terpakai';
        }

        return hit.length > 0 ? 'Menunggu persetujuan' : 'Tersedia';
    };

    return (
        <>
            <Head title="Kalender Lab" />
            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Kalender Lab</h1>
                    <p className="text-sm text-muted-foreground">
                        Menampilkan jam yang sudah terpakai atau sedang menunggu persetujuan. Detail kegiatan orang lain tidak ditampilkan.
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="lab">Laboratorium</Label>
                        <select
                            id="lab"
                            className={selectClass}
                            value={selectedLabId ?? ''}
                            onChange={(e) => go({ lab: Number(e.target.value) })}
                        >
                            {labs.map((l) => (
                                <option key={l.id} value={l.id}>{l.name}</option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" aria-label="Bulan sebelumnya" onClick={() => go({ month: shiftMonth(month, -1) })}>
                            <ChevronLeft />
                        </Button>
                        <span className="min-w-36 text-center text-sm font-medium capitalize">{monthTitle(month)}</span>
                        <Button variant="outline" size="sm" aria-label="Bulan berikutnya" onClick={() => go({ month: shiftMonth(month, 1) })}>
                            <ChevronRight />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSelected(today);
                                go({ month: today.slice(0, 7) });
                            }}
                        >
                            Hari ini
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1"><span className="inline-block size-3 rounded bg-primary/15" /> Terpakai</span>
                    <span className="flex items-center gap-1"><span className="inline-block size-3 rounded bg-yellow-500/15" /> Menunggu persetujuan</span>
                    <span>Garis bawah: milik Anda</span>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="grid min-w-[640px] grid-cols-7">
                        {WEEKDAYS.map((w) => (
                            <div key={w} className="border-b border-r bg-muted/30 p-2 text-center text-xs font-medium">
                                {w}
                            </div>
                        ))}

                        {days.map((d) => {
                            const inMonth = d.startsWith(month);
                            const list = byDate[d] ?? [];

                            return (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() => setSelected(d)}
                                    className={`min-h-24 border-b border-r p-2 text-left align-top text-xs transition-colors hover:bg-accent/40 ${
                                        inMonth ? '' : 'bg-muted/30 text-muted-foreground'
                                    } ${d === selectedDate ? 'bg-accent/60' : ''}`}
                                >
                                    <span
                                        className={`inline-flex size-6 items-center justify-center rounded-full ${
                                            d === today ? 'bg-primary text-primary-foreground' : ''
                                        }`}
                                    >
                                        {Number(d.slice(8))}
                                    </span>

                                    <div className="mt-1 space-y-1">
                                        {list.slice(0, 3).map((e, i) => (
                                            <div
                                                key={i}
                                                className={`truncate rounded px-1 py-0.5 ${chipClass(e)} ${e.mine ? 'underline' : ''}`}
                                            >
                                                {e.start_time}-{e.end_time}
                                            </div>
                                        ))}
                                        {list.length > 3 && <div className="text-muted-foreground">+{list.length - 3} lagi</div>}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <h2 className="font-medium">
                        {parse(selectedDate).toLocaleDateString('id-ID', {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </h2>

                    <div className="space-y-1 text-sm">
                        <p className="text-muted-foreground">Ketersediaan sesi</p>
                        {slots.map((s) => {
                            const status = slotStatus(s);

                            return (
                                <div key={s.id} className="flex items-center justify-between gap-3">
                                    <span>{s.name} ({s.start_time}-{s.end_time})</span>
                                    <Badge variant={status === 'Tersedia' ? 'secondary' : status === 'Terpakai' ? 'destructive' : 'outline'}>
                                        {status}
                                    </Badge>
                                </div>
                            );
                        })}
                    </div>

                    <div className="space-y-1 text-sm">
                        <p className="text-muted-foreground">Jam yang terisi</p>
                        {dayEvents.length === 0 && <p>Tidak ada pemakaian pada hari ini.</p>}
                        {dayEvents.map((e, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <span>{e.start_time} - {e.end_time}</span>
                                <Badge variant="outline">{e.status === 'terpakai' ? 'Terpakai' : 'Menunggu persetujuan'}</Badge>
                                {e.mine && e.reservation_id && (
                                    <Link href={`/reservations/${e.reservation_id}`} className="underline underline-offset-4">
                                        Milik Anda
                                    </Link>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm">
                            <Link href="/reservations/create">Ajukan Jadwal</Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href="/bookings/create">Booking Lab</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

CalendarPage.layout = {
    breadcrumbs: [{ title: 'Kalender Lab', href: '/calendar' }],
};