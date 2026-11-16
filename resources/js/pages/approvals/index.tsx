import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

type Item = {
    id: number;
    title: string;
    lab: string;
    creator: string;
    stage_label: string;
    override: boolean;
    first_date: string | null;
    sessions: number;
    waiting_since: string;
};

export default function Index({ items }: { items: Item[] }) {
    return (
        <>
            <Head title="Antrean Persetujuan" />
            <div className="space-y-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Antrean Persetujuan</h1>
                    <p className="text-sm text-muted-foreground">
                        Diurutkan dari yang paling lama menunggu. Baris bertanda Override berada di luar tahap atau lab Anda dan
                        memerlukan alasan.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Pengajuan</th>
                                <th className="p-3">Lab</th>
                                <th className="p-3">Pembuat</th>
                                <th className="p-3">Tahap</th>
                                <th className="p-3">Mulai</th>
                                <th className="p-3">Pertemuan</th>
                                <th className="p-3">Menunggu sejak</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 && (
                                <tr>
                                    <td className="p-3" colSpan={7}>Tidak ada pengajuan yang menunggu keputusan.</td>
                                </tr>
                            )}
                            {items.map((i) => (
                                <tr key={i.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <Link href={`/reservations/${i.id}`} className="font-medium underline-offset-4 hover:underline">
                                            {i.title}
                                        </Link>
                                    </td>
                                    <td className="p-3">{i.lab}</td>
                                    <td className="p-3">{i.creator}</td>
                                    <td className="p-3">
                                        {i.stage_label}{' '}
                                        {i.override && <Badge variant="outline">Override</Badge>}
                                    </td>
                                    <td className="p-3 whitespace-nowrap">{i.first_date ?? '-'}</td>
                                    <td className="p-3">{i.sessions}</td>
                                    <td className="p-3 whitespace-nowrap">{i.waiting_since}</td>
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
    breadcrumbs: [{ title: 'Antrean Persetujuan', href: '/approvals' }],
};