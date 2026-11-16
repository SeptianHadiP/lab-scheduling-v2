import PublicSiteHeader from '@/components/public-site-header';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Award, CalendarDays, CheckCircle2, Download, FileText, Search, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { dashboard, login } from '@/routes';

type Certificate = {
    id: number;
    certificate_number: string;
    student_name: string;
    title: string;
    category: string;
    description: string | null;
    issuer: string;
    issued_at: string | null;
};

type Props = {
    query: string;
    searched: boolean;
    certificates: Certificate[];
};

export default function Sertifikat({ query: initialQuery, searched, certificates }: Props) {
    const { auth } = usePage().props;
    const [query, setQuery] = useState(initialQuery);

    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/sertifikat', { q: query.trim() }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Cari Sertifikat | Lab Scheduling" />
            <main className="cert-page">
                <PublicSiteHeader active="sertifikat" />

                <section className="cert-hero">
                    <div className="cert-hero-copy">
                        <span className="cert-eyebrow"><ShieldCheck size={15} /> PORTAL VERIFIKASI PUBLIK</span>
                        <h1>Cari <span>Sertifikat</span></h1>
                        <p>Temukan dan verifikasi sertifikat kegiatan laboratorium yang telah diterbitkan dan dipublikasikan oleh pengelola.</p>
                        <div className="cert-hero-note"><CheckCircle2 size={16} /> Tidak perlu login untuk memverifikasi sertifikat publik.</div>
                    </div>
                    <div className="cert-hero-art" aria-hidden="true">
                        <div className="cert-art-orbit orbit-one" />
                        <div className="cert-art-orbit orbit-two" />
                        <div className="cert-art-paper"><Award size={53} /><span>TERVERIFIKASI</span><i /><i /><i /></div>
                    </div>
                </section>

                <section className="cert-content">
                    <div className="cert-section-heading">
                        <div>
                            <span className="cert-section-kicker">PENCARIAN SERTIFIKAT</span>
                            <h2>Periksa keaslian dokumen</h2>
                            <p>Masukkan nomor sertifikat atau nama mahasiswa. Hanya sertifikat publik yang diterbitkan yang akan ditampilkan.</p>
                        </div>
                    </div>

                    <form className="cert-toolbar public-cert-search" onSubmit={search}>
                        <label className="cert-search">
                            <Search size={19} />
                            <input
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Nomor sertifikat atau nama mahasiswa..."
                                aria-label="Nomor sertifikat atau nama mahasiswa"
                                minLength={3}
                                maxLength={100}
                            />
                        </label>
                        <button type="submit" className="cert-button-primary"><Search size={16} /> Cari Sertifikat</button>
                    </form>

                    {!searched && (
                        <div className="cert-empty">
                            <Search size={28} />
                            <h3>Mulai pencarian sertifikat</h3>
                            <p>Gunakan minimal 3 karakter nama atau masukkan nomor sertifikat lengkap.</p>
                        </div>
                    )}

                    {searched && certificates.length > 0 && (
                        <>
                            <div className="public-result-heading">
                                <h3>Hasil pencarian</h3>
                                <span>{certificates.length} sertifikat ditemukan</span>
                            </div>
                            <div className="cert-list">
                                {certificates.map((certificate) => (
                                    <article className="cert-card" key={certificate.id}>
                                        <div className="cert-card-emblem"><Award size={28} strokeWidth={1.6} /></div>
                                        <div className="cert-card-main">
                                            <div className="cert-card-meta">
                                                <span className="cert-category">{certificate.category}</span>
                                                <span className="cert-public-status"><CheckCircle2 size={13} /> Terverifikasi</span>
                                            </div>
                                            <h3>{certificate.title}</h3>
                                            <p><strong>Nama mahasiswa:</strong> {certificate.student_name}</p>
                                            {certificate.description && <p>{certificate.description}</p>}
                                            <div className="cert-card-foot">
                                                <span><FileText size={14} /> {certificate.certificate_number}</span>
                                                <span><CalendarDays size={14} /> {certificate.issued_at ?? 'Tanggal tidak tersedia'}</span>
                                            </div>
                                            <small className="public-issuer">Diterbitkan oleh {certificate.issuer}</small>
                                        </div>
                                        <div className="cert-card-actions">
                                            <a className="cert-button-primary" href={`/sertifikat/${certificate.id}/unduh`} target="_blank" rel="noreferrer">
                                                <Download size={16} /> Cetak / PDF
                                            </a>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </>
                    )}

                    {searched && certificates.length === 0 && (
                        <div className="cert-empty">
                            <Search size={28} />
                            <h3>Sertifikat tidak ditemukan</h3>
                            <p>Periksa kembali nomor sertifikat atau ejaan nama mahasiswa. Sertifikat privat tidak ditampilkan di portal publik.</p>
                            <button type="button" onClick={() => { setQuery(''); router.get('/sertifikat', {}, { replace: true }); }}>Hapus pencarian</button>
                        </div>
                    )}

                    <div className="cert-demo-notice">
                        <ShieldCheck size={17} />
                        <p><strong>Privasi terjaga:</strong> portal ini hanya menampilkan sertifikat yang ditandai publik oleh pengelola. Informasi akun, email, dan data pribadi lain tidak ditampilkan.</p>
                    </div>

                    <div className="private-cert-prompt">
                        <div><strong>Mahasiswa Universitas Bhayangkara?</strong><span>Masuk untuk melihat arsip sertifikat pribadi Anda.</span></div>
                        <Link href="/login" className="cert-button-secondary">Masuk ke Dashboard <ArrowRight size={16} /></Link>
                    </div>
                </section>

                <footer className="cert-footer">Lab Scheduling <span>·</span> Universitas Bhayangkara Jakarta Raya</footer>
            </main>
        </>
    );
}
