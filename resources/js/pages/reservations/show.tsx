import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Session = {
    id: number;
    sequence_no: number;
    date: string;
    start_time: string;
    end_time: string;
    has_module: boolean;
};

type Approval = {
    id: number;
    round: number;
    stage_label: string;
    decision: string;
    decision_label: string;
    override: boolean;
    by: string | null;
    note: string | null;
    at: string;
};

type Conflict = { sequence_no: number; date: string; start_time: string; end_time: string };

type Reservation = {
    id: number;
    title: string;
    type_label: string;
    type: string;
    edit_url: string;
    external_name: string | null;
    external_contact: string | null;
    status: string;
    status_label: string;
    lab: string;
    creator: string;
    booked_for: string | null;
    class: string | null;
    tools_software: string | null;
    grading_enabled: boolean;
    certificate_enabled: boolean;
    certificate_title: string | null;
    sessions: Session[];
    approvals: Approval[];
};

type Decision = 'approved' | 'revision_requested' | 'rejected';

export default function Show({
    reservation: r,
    can,
    decision,
    revision_note,
    conflicts,
    problems,
}: {
    reservation: Reservation;
    can: { edit: boolean; send: boolean; cancel: boolean; decide: boolean };
    decision: { stage_label: string; override: boolean } | null;
    revision_note: string | null;
    conflicts: Conflict[] | null;
    problems: string[] | null;
}) {
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const send = () => router.post(`/reservations/${r.id}/send`, {}, { preserveScroll: true });

    const cancel = () => {
        if (window.confirm('Batalkan pengajuan ini? Slot akan dilepas.')) {
            router.post(`/reservations/${r.id}/cancel`, {}, { preserveScroll: true });
        }
    };

    const decide = (value: Decision) => {
        if (value === 'rejected' && !window.confirm('Tolak pengajuan ini? Slot akan dilepas.')) {
            return;
        }

        router.post(
            `/reservations/${r.id}/decide`,
            { decision: value, note },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (e) => setErrors(e),
                onSuccess: () => {
                    setNote('');
                    setErrors({});
                },
            },
        );
    };

    return (
        <>
            <Head title={r.title} />
            <div className="space-y-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold">{r.title}</h1>
                        <p className="text-sm text-muted-foreground">{r.type_label} · {r.lab}</p>
                    </div>
                    <Badge variant="outline">{r.status_label}</Badge>
                </div>

                {revision_note && (
                    <div className="space-y-1 rounded-xl border border-yellow-500/50 bg-yellow-500/10 p-4 text-sm">
                        <p className="font-medium">Pengajuan diminta direvisi</p>
                        <p>{revision_note}</p>
                        <p className="text-muted-foreground">
                            Perbaiki pengajuan, lalu kirim ulang. Slot akan diperiksa kembali saat dikirim.
                        </p>
                    </div>
                )}

                {conflicts && conflicts.length > 0 && (
                    <div className="space-y-2 rounded-xl border border-red-500/50 bg-red-500/10 p-4 text-sm">
                        <p className="font-medium">Pengajuan belum terkirim karena jadwal bentrok pada:</p>
                        <ul className="list-disc pl-5">
                            {conflicts.map((c) => (
                                <li key={c.sequence_no}>
                                    Pertemuan {c.sequence_no}: {c.date}, {c.start_time}-{c.end_time}
                                </li>
                            ))}
                        </ul>
                        <p>Ganti tanggal atau jam pertemuan tersebut, lalu kirim lagi.</p>
                        {can.edit && (
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/reservations/${r.id}/edit`}>Ubah pertemuan</Link>
                            </Button>
                        )}
                    </div>
                )}

                {problems && problems.length > 0 && (
                    <div className="space-y-2 rounded-xl border border-yellow-500/50 bg-yellow-500/10 p-4 text-sm">
                        <p className="font-medium">Perlu diperbaiki:</p>
                        <ul className="list-disc pl-5">
                            {problems.map((p, i) => (
                                <li key={i}>{p}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <dl className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 text-sm md:grid-cols-2 dark:border-sidebar-border">
                    <div><dt className="text-muted-foreground">Kelas</dt><dd>{r.class ?? '-'}</dd></div>
                    <div>
                        <dt className="text-muted-foreground">{r.type === 'booking' ? 'Atas nama' : 'Dosen pengampu'}</dt>
                        <dd>{r.external_name ? `${r.external_name} (${r.external_contact})` : (r.booked_for ?? '-')}</dd>
                    </div>
                    <div><dt className="text-muted-foreground">Dibuat oleh</dt><dd>{r.creator}</dd></div>
                    <div><dt className="text-muted-foreground">Tools / software</dt><dd>{r.tools_software || '-'}</dd></div>
                    <div><dt className="text-muted-foreground">Nilai</dt><dd>{r.grading_enabled ? 'Ya' : 'Tidak'}</dd></div>
                    <div>
                        <dt className="text-muted-foreground">Sertifikat</dt>
                        <dd>{r.certificate_enabled ? `Ya: ${r.certificate_title}` : 'Tidak'}</dd>
                    </div>
                </dl>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-muted-foreground">
                            <tr>
                                <th className="p-3">Pertemuan</th>
                                <th className="p-3">Tanggal</th>
                                <th className="p-3">Jam</th>
                                <th className="p-3">Modul</th>
                            </tr>
                        </thead>
                        <tbody>
                            {r.sessions.map((s) => (
                                <tr key={s.id} className="border-b last:border-0">
                                    <td className="p-3">{s.sequence_no}</td>
                                    <td className="p-3">{s.date}</td>
                                    <td className="p-3">{s.start_time} - {s.end_time}</td>
                                    <td className="p-3">
                                        {s.has_module ? (
                                            <a className="underline underline-offset-4" href={`/reservations/${r.id}/sessions/${s.id}/module`}>
                                                Unduh
                                            </a>
                                        ) : (
                                            <span className="text-muted-foreground">{r.type === 'booking' ? '-' : 'Belum diunggah'}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {can.decide && decision && (
                    <div className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <h2 className="font-medium">{decision.stage_label}</h2>

                        {decision.override && (
                            <p className="text-sm text-yellow-500">
                                Anda bukan pemroses utama tahap ini. Keputusan akan dicatat sebagai override dan wajib disertai
                                alasan.
                            </p>
                        )}

                        <div className="grid gap-1">
                            <Label htmlFor="note">
                                {decision.override ? 'Alasan (wajib)' : 'Catatan (wajib untuk revisi dan penolakan)'}
                            </Label>
                            <textarea
                                id="note"
                                rows={3}
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-xs"
                            />
                            <InputError message={errors.note} />
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button disabled={busy} onClick={() => decide('approved')}>Setujui</Button>
                            {r.type !== 'booking' && (
                                <Button variant="outline" disabled={busy} onClick={() => decide('revision_requested')}>
                                    Minta Revisi
                                </Button>
                            )}
                            <Button variant="destructive" disabled={busy} onClick={() => decide('rejected')}>Tolak</Button>
                        </div>
                    </div>
                )}

                {r.approvals.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="font-medium">Riwayat persetujuan</h2>
                        <ul className="space-y-1 text-sm">
                            {r.approvals.map((a) => (
                                <li key={a.id}>
                                    <span className="text-muted-foreground">{a.at}</span> · Putaran {a.round} · {a.stage_label}:{' '}
                                    <span className="font-medium">{a.decision_label}</span>
                                    {a.by ? ` oleh ${a.by}` : ''}
                                    {a.override && <Badge variant="outline" className="ml-1">Override</Badge>}
                                    {a.note ? ` (${a.note})` : ''}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {can.send && (
                        <Button onClick={send}>
                            {r.status === 'revision_requested' ? 'Kirim Ulang' : 'Kirim Pengajuan'}
                        </Button>
                    )}
                    {can.edit && (
                        <Button asChild variant="outline">
                            <Link href={r.edit_url}>Ubah</Link>
                        </Button>
                    )}
                    {can.cancel && <Button variant="outline" onClick={cancel}>Batalkan</Button>}
                    <Button asChild variant="ghost">
                        <Link href="/reservations">Kembali</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

Show.layout = {
    breadcrumbs: [
        { title: 'Pengajuan Jadwal', href: '/reservations' },
        { title: 'Detail', href: '#' },
    ],
};