import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Row = {
    row: number;
    npm: string;
    name: string;
    status: 'baru' | 'tautkan' | 'sudah' | 'error';
    errors: string[];
    warning: string | null;
};

type Sheet = {
    name: string;
    dosen: string | null;
    course: string | null;
    class_code: string | null;
    class_label: string | null;
    errors: string[];
    rows: Row[];
    counts: { baru: number; tautkan: number; sudah: number; error: number };
    importable: boolean;
};

type Preview = { sheets: Sheet[]; total_importable: number };

type ResultItem = {
    sheet: string;
    class: string | null;
    imported: boolean;
    created: number;
    linked: number;
    reason: string | null;
};

const statusLabel: Record<Row['status'], string> = {
    baru: 'Baru',
    tautkan: 'Ditautkan',
    sudah: 'Sudah di kelas',
    error: 'Error',
};

export default function Import({ preview, result }: { preview: Preview | null; result: ResultItem[] | null }) {
    const { setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/students/import', { forceFormData: true, onSuccess: () => reset() });
    };

    return (
        <>
            <Head title="Impor Mahasiswa" />
            <div className="space-y-5 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Impor Mahasiswa</h1>
                    <p className="text-sm text-muted-foreground">
                        Unggah berkas Excel (.xlsx), satu sheet per kelas. Header: Nama Dosen, Mata Kuliah, Kelas. Tabel: No, NPM, NAMA.
                        Mata kuliah dan kelas harus sudah dibuat Ketua Lab dan menjadi kelas Anda.
                    </p>
                </div>

                {result && (
                    <div className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="font-medium">Hasil impor</h2>
                        {result.map((r, i) => (
                            <div key={i} className="text-sm">
                                <Badge variant={r.imported ? 'secondary' : 'destructive'}>
                                    {r.imported ? 'Berhasil' : 'Dilewati'}
                                </Badge>{' '}
                                <span className="font-medium">{r.class ?? r.sheet}</span>
                                {r.imported
                                    ? `: ${r.created} mahasiswa baru, ${r.linked} ditautkan.`
                                    : `: ${r.reason}`}
                            </div>
                        ))}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid max-w-md gap-1">
                        <Label htmlFor="file">Berkas Excel (.xlsx, maks. 5 MB)</Label>
                        <Input
                            id="file"
                            type="file"
                            accept=".xlsx"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        />
                        <InputError message={errors.file} />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>Unggah dan Pratinjau</Button>
                        <Button asChild variant="outline">
                            <a href="/students/import/template">Unduh Template</a>
                        </Button>
                    </div>
                </form>

                {preview && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Pratinjau</h2>

                        {preview.sheets.map((s, i) => (
                            <div key={i} className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">Sheet: {s.name}</span>
                                    <Badge variant={s.importable ? 'secondary' : 'destructive'}>
                                        {s.importable ? 'Siap diimpor' : 'Tidak akan diimpor'}
                                    </Badge>
                                </div>

                                {s.class_label && <p className="text-sm text-muted-foreground">{s.class_label}</p>}

                                {s.errors.map((e, j) => (
                                    <p key={j} className="text-sm text-red-400">{e}</p>
                                ))}

                                {s.rows.length > 0 && (
                                    <>
                                        <p className="text-sm text-muted-foreground">
                                            Baru {s.counts.baru}, ditautkan {s.counts.tautkan}, sudah di kelas {s.counts.sudah}, error {s.counts.error}
                                        </p>
                                        <div className="max-h-80 overflow-auto rounded-md border">
                                            <table className="w-full text-left text-sm">
                                                <thead className="sticky top-0 border-b bg-background text-muted-foreground">
                                                    <tr>
                                                        <th className="p-2">Baris</th>
                                                        <th className="p-2">NPM</th>
                                                        <th className="p-2">Nama</th>
                                                        <th className="p-2">Status</th>
                                                        <th className="p-2">Catatan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {s.rows.map((r) => (
                                                        <tr key={r.row} className="border-b last:border-0">
                                                            <td className="p-2">{r.row}</td>
                                                            <td className="p-2">{r.npm}</td>
                                                            <td className="p-2">{r.name}</td>
                                                            <td className="p-2">
                                                                <Badge variant={r.status === 'error' ? 'destructive' : 'secondary'}>
                                                                    {statusLabel[r.status]}
                                                                </Badge>
                                                            </td>
                                                            <td className="p-2 text-xs">
                                                                {r.errors.length > 0 ? r.errors.join(' ') : (r.warning ?? '-')}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}

                        <div className="flex gap-2">
                            <Button
                                disabled={preview.total_importable === 0}
                                onClick={() => router.post('/students/import/confirm')}
                            >
                                Impor {preview.total_importable} mahasiswa
                            </Button>
                            <Button variant="outline" onClick={() => router.delete('/students/import')}>
                                Batal
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Import.layout = {
    breadcrumbs: [{ title: 'Impor Mahasiswa', href: '/students/import' }],
};