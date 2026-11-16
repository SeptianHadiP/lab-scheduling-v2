import PublicSiteHeader from '@/components/public-site-header';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight, BookOpen, CalendarDays, CheckCircle2, Clock3,
    FlaskConical, GraduationCap, ListChecks, ShieldCheck, Target, UsersRound,
} from 'lucide-react';
import { dashboard, login } from '@/routes';

const values = [
    {
        icon: CalendarDays,
        title: 'Penjadwalan lebih teratur',
        description: 'Informasi jadwal laboratorium disajikan dengan lebih jelas agar kegiatan praktikum dapat direncanakan sejak awal.',
    },
    {
        icon: UsersRound,
        title: 'Kolaborasi yang mudah',
        description: 'Membantu mahasiswa, dosen, dan pengelola laboratorium berkoordinasi dalam penggunaan fasilitas.',
    },
    {
        icon: Clock3,
        title: 'Penggunaan waktu yang efisien',
        description: 'Memudahkan pengguna memahami waktu kegiatan sehingga benturan jadwal dapat diminimalkan.',
    },
];

export default function Tentang() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Tentang | Lab Scheduling Ubhara Jaya" />
            <main className="welcome-page about-page">
                <PublicSiteHeader active="tentang" />

                <section className="about-hero">
                    <div className="about-hero-glow" />
                    <div className="about-hero-copy">
                        <span className="eyebrow"><BookOpen size={15} /> MENGENAL LAB SCHEDULING</span>
                        <h1>Tentang <span>Lab Scheduling</span></h1>
                        <p className="about-lead">
                            Membantu kegiatan praktikum, pembelajaran, dan penelitian berjalan
                            lebih terencana melalui informasi penjadwalan laboratorium yang
                            mudah diakses.
                        </p>
                        <Link className="button-primary" href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Buka Dashboard' : 'Mulai Sekarang'} <ArrowRight size={19} />
                        </Link>
                    </div>
                    <div className="about-hero-art" aria-hidden="true">
                        <div className="about-orbit orbit-one" />
                        <div className="about-orbit orbit-two" />
                        <div className="about-flask"><FlaskConical size={104} strokeWidth={1.1} /></div>
                        <div className="about-art-caption"><span /> Sistem Penjadwalan Laboratorium</div>
                    </div>
                </section>

                <section className="about-story">
                    <div className="about-story-heading">
                        <span className="section-kicker">TENTANG PLATFORM</span>
                        <h2>Pengelolaan laboratorium yang <span>lebih terarah.</span></h2>
                    </div>
                    <div className="about-story-copy">
                        <p>
                            Lab Scheduling merupakan platform pendukung pengelolaan jadwal dan
                            penggunaan laboratorium di lingkungan Universitas Bhayangkara Jakarta Raya.
                            Platform ini dirancang untuk membantu pengguna memperoleh informasi
                            penjadwalan secara lebih praktis dan terstruktur.
                        </p>
                        <p>
                            Dengan informasi yang tersusun dalam satu tempat, mahasiswa, dosen,
                            dan pengelola dapat merencanakan kegiatan laboratorium dengan lebih
                            baik serta mendukung kelancaran aktivitas akademik.
                        </p>
                    </div>
                </section>

                <section className="about-lab-mission" aria-labelledby="about-lab-mission-title">
                    <div className="about-lab-mission-heading">
                        <span className="section-kicker">FAKULTAS ILMU KOMPUTER</span>
                        <h2 id="about-lab-mission-title">Laboratorium</h2>
                        <p>Fakultas Ilmu Komputer<br />Universitas Bhayangkara Jakarta Raya</p>
                        <span className="about-lab-mission-rule" aria-hidden="true" />
                    </div>
                    <div className="about-lab-mission-grid">
                        <article className="about-lab-mission-card about-lab-vision">
                            <div className="about-lab-mission-card-heading">
                                <span className="about-lab-mission-icon"><Target size={23} /></span>
                                <h3>Visi</h3>
                            </div>
                            <p>
                                Terwujudnya laboratorium yang mendukung Fakultas Ilmu Komputer untuk <strong>unggul</strong> di tingkat Nasional dan Internasional bidang ilmu Komputer yang berwawasan <strong>kebangsaan</strong> dan berbasis <strong>sekuriti</strong> guna menghasilkan sumber daya manusia yang <strong>mampu bersaing</strong> dan <strong>berperilaku baik</strong>.
                            </p>
                        </article>
                        <article className="about-lab-mission-card about-lab-mission-list">
                            <div className="about-lab-mission-card-heading">
                                <span className="about-lab-mission-icon"><ListChecks size={23} /></span>
                                <h3>Misi</h3>
                            </div>
                            <ol>
                                <li>Menyelenggarakan proses pembelajaran praktikum dengan menerapkan kurikulum adaptif berbasis IPTEKS.</li>
                                <li>Memfasilitasi kegiatan penelitian dasar maupun terapan untuk mengembangkan ilmu pengetahuan dan teknologi.</li>
                                <li>Memfasilitasi kegiatan pengabdian kepada masyarakat sebagai upaya menyebarluaskan hasil pengembangan ilmu pengetahuan dan teknologi.</li>
                                <li>Menjalin kerjasama dengan berbagai stakeholder untuk mendukung terwujudnya pelaksanaan Tri Dharma perguruan tinggi.</li>
                            </ol>
                        </article>
                    </div>
                </section>

                <section className="about-values">
                    <div className="about-section-heading">
                        <span className="section-kicker">MANFAAT UTAMA</span>
                        <h2>Dirancang untuk mendukung aktivitas akademik</h2>
                        <p>Fokus pada kemudahan akses informasi, koordinasi, dan perencanaan kegiatan laboratorium.</p>
                    </div>
                    <div className="about-value-grid">
                        {values.map(({ icon: Icon, title, description }, index) => (
                            <article className="about-value-card" key={title}>
                                <span className={`about-value-icon tone-${index + 1}`}><Icon size={24} strokeWidth={1.8} /></span>
                                <h3>{title}</h3>
                                <p>{description}</p>
                                <CheckCircle2 className="about-check" size={18} />
                            </article>
                        ))}
                    </div>
                </section>

                <section className="about-audience">
                    <div className="about-audience-icon"><GraduationCap size={31} /></div>
                    <div className="about-audience-copy">
                        <span className="section-kicker">UNTUK KOMUNITAS KAMPUS</span>
                        <h2>Satu platform untuk mendukung kolaborasi.</h2>
                        <p>Dirancang untuk mendukung mahasiswa, dosen, dan pengelola laboratorium dalam merencanakan penggunaan fasilitas.</p>
                    </div>
                    <div className="about-audience-points">
                        <span><CheckCircle2 size={17} /> Mahasiswa</span>
                        <span><CheckCircle2 size={17} /> Dosen</span>
                        <span><CheckCircle2 size={17} /> Pengelola laboratorium</span>
                    </div>
                </section>

                <section className="bottom-banner about-cta">
                    <div className="bottom-banner-icon"><ShieldCheck size={23} /></div>
                    <div>
                        <h2>Mulai rencanakan kegiatan laboratorium Anda</h2>
                        <p>Akses sistem untuk melanjutkan ke layanan Lab Scheduling.</p>
                    </div>
                    <Link className="button-primary banner-button" href={auth.user ? dashboard() : login()}>
                        {auth.user ? 'Buka Dashboard' : 'Masuk ke Sistem'} <ArrowRight size={18} />
                    </Link>
                </section>

                <footer className="welcome-footer">
                    <div className="footer-brand"><span className="footer-dot" /> Lab Scheduling <span>·</span> Universitas Bhayangkara Jakarta Raya</div>
                    <p>Platform pendukung kegiatan praktikum, pembelajaran, dan penelitian.</p>
                </footer>
            </main>
        </>
    );
}
