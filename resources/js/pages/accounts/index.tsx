import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    lab: string | null;
    is_active: boolean;
};

export default function Index({ accounts, meId }: { accounts: Account[]; meId: number }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const toggle = (id: number) =>
        router.patch(`/accounts/${id}/status`, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Kelola Akun" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Kelola Akun</h1>
                    <Button asChild>
                        <Link href="/accounts/create">
                            <Plus /> Tambah Akun
                        </Link>
                    </Button>
                </div>

                <InputError message={errors.status} />

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Nama</th>
                                <th className="p-3">Email</th>
                                <th className="p-3">Role</th>
                                <th className="p-3">Lab</th>
                                <th className="p-3">Status</th>
                                <th className="p-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {accounts.map((a) => (
                                <tr key={a.id} className="border-b last:border-0">
                                    <td className="p-3">{a.name}</td>
                                    <td className="p-3">{a.email}</td>
                                    <td className="p-3">{a.role_label}</td>
                                    <td className="p-3">{a.lab ?? '-'}</td>
                                    <td className="p-3">
                                        <Badge variant={a.is_active ? 'secondary' : 'destructive'}>
                                            {a.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </td>
                                    <td className="flex gap-2 p-3">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={`/accounts/${a.id}/edit`}>Ubah</Link>
                                        </Button>
                                        {a.id !== meId && (
                                            <Button variant="outline" size="sm" onClick={() => toggle(a.id)}>
                                                {a.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                            </Button>
                                        )}
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
    breadcrumbs: [{ title: 'Kelola Akun', href: '/accounts' }],
};