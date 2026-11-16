import PublicSiteHeader from '@/components/public-site-header';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDown, ArrowRight, CalendarDays, Clock3, FileBadge2, FlaskConical,
    Search, ShieldCheck, UsersRound,
} from 'lucide-react';
import { dashboard, login } from '@/routes';

const features = [
    {
        icon: CalendarDays,
        title: 'Jadwal Laboratorium',
        description: 'Lihat jadwal praktikum dan ketersediaan ruang dengan lebih mudah.',
        tone: 'mint',
    },
    {
        icon: FlaskConical,
        title: 'Pemesanan Laboratorium',
        description: 'Ajukan penggunaan laboratorium sesuai kebutuhan kegiatan Anda.',
        tone: 'violet',
    },
    {
        icon: Clock3,
        title: 'Informasi Waktu',
        description: 'Periksa waktu penggunaan agar kegiatan berjalan lebih terencana.',
        tone: 'blue',
    },
    {
        icon: UsersRound,
        title: 'Kolaborasi Terarah',
        description: 'Mendukung koordinasi mahasiswa, dosen, dan pengelola laboratorium.',
        tone: 'amber',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Lab Scheduling | Universitas Bhayangkara Jakarta Raya" />
            <main className="welcome-page">
                <PublicSiteHeader active="beranda" />

                <section className="hero" id="beranda">
                    <div className="hero-photo" role="img" aria-label="Suasana laboratorium komputer universitas" />
                    <div className="hero-shade" />
                    <div className="hero-content">
                        <span className="eyebrow"><FlaskConical size={15} /> SISTEM PENJADWALAN LABORATORIUM</span>
                        <h1>
                            Selamat Datang di
                            <span>Lab Scheduling</span>
                        </h1>
                        <h2>Universitas Bhayangkara Jakarta Raya</h2>
                        <p>
                            Sistem penjadwalan laboratorium terpadu untuk mendukung
                            kegiatan praktikum, pembelajaran, dan penelitian dalam
                            satu platform yang mudah digunakan.
                        </p>
                        <div className="hero-actions">
                            <Link className="button-primary" href={auth.user ? dashboard() : login()}>
                                Mulai Sekarang <ArrowRight size={19} />
                            </Link>
                            <a className="button-text" href="#tentang">
                                Pelajari Lebih Lanjut <ArrowDown size={16} />
                            </a>
                        </div>
                        <div className="hero-note">
                            <ShieldCheck size={17} />
                            <span>Akses informasi laboratorium lebih praktis dan terarah</span>
                        </div>
                    </div>
                    <a href="#tentang" className="scroll-cue"><span /> Gulir untuk menjelajahi</a>
                    <div className="hero-index"><span>01</span><i /> 03</div>
                </section>

                <section className="intro-section" id="tentang">
                    <div className="intro-copy">
                        <span className="section-kicker">MENGAPA MEMILIH KAMI?</span>
                        <h2>Solusi praktis untuk <span>manajemen laboratorium</span></h2>
                        <p>
                            Lab Scheduling membantu mahasiswa, dosen, dan pengelola
                            mengakses informasi jadwal serta merencanakan penggunaan
                            laboratorium secara lebih tertata.
                        </p>
                        <div className="intro-links"><a className="learn-link" href="#fitur">Kenali fitur kami <ArrowRight size={17} /></a><Link className="learn-link" href="/sertifikat">Lihat Sertifikat <FileBadge2 size={17} /></Link></div>
                    </div>

                    <div className="feature-grid" id="fitur">
                        {features.map(({ icon: Icon, title, description, tone }) => (
                            <article className="feature-card" key={title}>
                                <span className={`feature-icon ${tone}`}><Icon size={23} strokeWidth={1.8} /></span>
                                <h3>{title}</h3>
                                <p>{description}</p>
                                <a href="#laboratorium" aria-label={`Pelajari ${title}`}><ArrowRight size={19} /></a>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="bottom-banner" id="laboratorium">
                    <div className="bottom-banner-icon"><Search size={23} /></div>
                    <div>
                        <h2>Siap merencanakan kegiatan laboratorium?</h2>
                        <p>Mulai dengan melihat informasi dan layanan penjadwalan yang tersedia.</p>
                    </div>
                    <Link className="button-primary banner-button" href={auth.user ? dashboard() : login()}>
                        {auth.user ? 'Buka Dashboard' : 'Masuk ke Sistem'} <ArrowRight size={18} />
                    </Link>
                </section>

                <footer className="welcome-footer" id="panduan">
                    <div className="footer-brand"><span className="footer-dot" /> Lab Scheduling <span>·</span> Universitas Bhayangkara Jakarta Raya</div>
                    <p>Platform pendukung kegiatan praktikum, pembelajaran, dan penelitian.</p>
                </footer>
            </main>
        </>
    );
}
