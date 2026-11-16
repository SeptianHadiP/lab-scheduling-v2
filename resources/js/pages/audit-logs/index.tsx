import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Log = {
    id: number;
    at: string;
    actor: string;
    action: string;
    entity: string | null;
    result: string;
    reason: string | null;
    metadata: Record<string, unknown> | null;
    ip: string | null;
};

type Filters = { action: string; from: string; to: string };

type Paginated = {
    data: Log[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs';

export default function Index({
    logs,
    actions,
    filters,
}: {
    logs: Paginated;
    actions: string[];
    filters: Filters;
}) {
    const [form, setForm] = useState<Filters>(filters);

    const apply = () => {
        const params = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ''));
        router.get('/audit-logs', params, { preserveState: true, replace: true });
    };

    const reset = () => {
        setForm({ action: '', from: '', to: '' });
        router.get('/audit-logs', {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Audit Log" />
            <div className="space-y-4 p-4">
                <h1 className="text-xl font-semibold">Audit Log</h1>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="action">Aksi</Label>
                        <select
                            id="action"
                            className={selectClass}
                            value={form.action}
                            onChange={(e) => setForm({ ...form, action: e.target.value })}
                        >
                            <option value="">Semua</option>
                            {actions.map((a) => (
                                <option key={a} value={a}>{a}</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="from">Dari</Label>
                        <Input id="from" type="date" value={form.from} onChange={(e) => setForm({ ...form, from: e.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to">Sampai</Label>
                        <Input id="to" type="date" value={form.to} onChange={(e) => setForm({ ...form, to: e.target.value })} />
                    </div>
                    <Button onClick={apply}>Terapkan</Button>
                    <Button variant="outline" onClick={reset}>Reset</Button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Waktu</th>
                                <th className="p-3">Aktor</th>
                                <th className="p-3">Aksi</th>
                                <th className="p-3">Entitas</th>
                                <th className="p-3">Hasil</th>
                                <th className="p-3">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.length === 0 && (
                                <tr>
                                    <td className="p-3" colSpan={6}>Belum ada catatan.</td>
                                </tr>
                            )}
                            {logs.data.map((l) => (
                                <tr key={l.id} className="border-b align-top last:border-0">
                                    <td className="p-3 whitespace-nowrap">{l.at}</td>
                                    <td className="p-3">{l.actor}</td>
                                    <td className="p-3">{l.action}</td>
                                    <td className="p-3">{l.entity ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={l.result === 'success' ? 'secondary' : 'destructive'}>
                                            {l.result === 'success' ? 'Berhasil' : 'Gagal'}
                                        </Badge>
                                    </td>
                                    <td className="p-3">
                                        {l.metadata || l.reason || l.ip ? (
                                            <details>
                                                <summary className="cursor-pointer text-muted-foreground">Lihat</summary>
                                                <pre className="mt-1 max-w-xs overflow-x-auto text-xs">
                                                    {JSON.stringify({ ...l.metadata, alasan: l.reason, ip: l.ip }, null, 2)}
                                                </pre>
                                            </details>
                                        ) : (
                                            '-'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex gap-2">
                    {logs.prev_page_url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={logs.prev_page_url}>Sebelumnya</Link>
                        </Button>
                    )}
                    {logs.next_page_url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={logs.next_page_url}>Berikutnya</Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [{ title: 'Audit Log', href: '/audit-logs' }],
};