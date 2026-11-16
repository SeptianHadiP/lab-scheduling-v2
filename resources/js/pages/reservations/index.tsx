import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    title: string;
    type_label: string;
    lab: string;
    creator: string;
    status: string;
    status_label: string;
    first_date: string | null;
    last_date: string | null;
    sessions: number;
};

const variant = (s: string) =>
    s === 'approved' || s === 'completed' ? 'secondary' : s === 'rejected' || s === 'cancelled' ? 'destructive' : 'outline';

export default function Index({ reservations }: { reservations: Row[] }) {
    return (
        <>
            <Head title="Pengajuan Jadwal" />
            <div className="space-y-4 p-4">
                <div className="flex gap-2">
                    <Button asChild>
                        <Link href="/reservations/create">
                            <Plus /> Ajukan Jadwal
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/bookings/create">
                            <Plus /> Booking Lab
                        </Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Pengajuan</th>
                                <th className="p-3">Jenis</th>
                                <th className="p-3">Lab</th>
                                <th className="p-3">Tanggal</th>
                                <th className="p-3">Pertemuan</th>
                                <th className="p-3">Pembuat</th>
                                <th className="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {reservations.length === 0 && (
                                <tr>
                                    <td className="p-3" colSpan={7}>Belum ada pengajuan.</td>
                                </tr>
                            )}
                            {reservations.map((r) => (
                                <tr key={r.id} className="border-b last:border-0">
                                    <td className="p-3">{r.type_label}</td>
                                    <td className="p-3">
                                        <Link href={`/reservations/${r.id}`} className="font-medium underline-offset-4 hover:underline">
                                            {r.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">{r.lab}</td>
                                    <td className="p-3 whitespace-nowrap">
                                        {r.first_date ?? '-'}
                                        {r.last_date && r.last_date !== r.first_date ? ` s/d ${r.last_date}` : ''}
                                    </td>
                                    <td className="p-3">{r.sessions}</td>
                                    <td className="p-3">{r.creator}</td>
                                    <td className="p-3">
                                        <Badge variant={variant(r.status)}>{r.status_label}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Pengajuan Jadwal', href: '/reservations' }],
};