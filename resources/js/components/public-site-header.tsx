
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUp,
    ChevronDown,
    FlaskConical,
    Menu,
    UsersRound,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { dashboard, login } from '@/routes';

type PublicPage =
    | 'beranda'
    | 'tentang'
    | 'laboratorium'
    | 'sertifikat'
    | 'panduan';

type Props = {
    active: PublicPage;
};

const navigation: {
    label: string;
    href: string;
    page: PublicPage;
}[] = [
    { label: 'Beranda', href: '/', page: 'beranda' },
    { label: 'Tentang', href: '/tentang', page: 'tentang' },
    {
        label: 'Laboratorium',
        href: '/laboratorium',
        page: 'laboratorium',
    },
    { label: 'Sertifikat', href: '/sertifikat', page: 'sertifikat' },
];

export default function PublicSiteHeader({ active }: Props) {
    const { auth } = usePage().props;

    const [menuOpen, setMenuOpen] = useState(false);
    const [guideOpen, setGuideOpen] = useState(false);
    const [showBackToTop, setShowBackToTop] = useState(false);

    const headerRef = useRef<HTMLElement>(null);
    const guideMenuRef = useRef<HTMLDivElement>(null);

    const closeMenus = () => {
        setGuideOpen(false);
        setMenuOpen(false);
    };

    useEffect(() => {
        const handlePointerDown = (event: PointerEvent) => {
            const target = event.target;

            if (
                target instanceof Node &&
                headerRef.current &&
                !headerRef.current.contains(target)
            ) {
                closeMenus();
            }
        };

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                closeMenus();
            }
        };

        const handleScroll = () => {
            setShowBackToTop(window.scrollY > 250);
        };

        document.addEventListener('pointerdown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);
        window.addEventListener('scroll', handleScroll, {
            passive: true,
        });

        handleScroll();

        return () => {
            document.removeEventListener(
                'pointerdown',
                handlePointerDown,
            );
            document.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener('scroll', handleScroll);
        };
    }, []);

    const handleGuideMouseEnter = () => {
        if (
            window.matchMedia(
                '(hover: hover) and (pointer: fine)',
            ).matches
        ) {
            setGuideOpen(true);
        }
    };

    const handleGuideMouseLeave = () => {
        if (
            window.matchMedia(
                '(hover: hover) and (pointer: fine)',
            ).matches
        ) {
            setGuideOpen(false);
        }
    };

    const handleGuideClick = () => {
        setGuideOpen((open) => !open);
    };

    const handleBackToTop = () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    return (
        <>
            <header
                ref={headerRef}
                className="public-site-header"
            >
                <Link
                    className="public-site-brand"
                    href="/"
                    aria-label="Lab Scheduling, Beranda"
                    onClick={closeMenus}
                >
                    <span
                        className="public-site-crest"
                        aria-hidden="true"
                    >
                        <FlaskConical
                            size={25}
                            strokeWidth={1.8}
                        />
                    </span>

                    <span className="public-site-university">
                        <strong>UNIVERSITAS BHAYANGKARA</strong>
                        <span>JAKARTA RAYA</span>
                    </span>

                    <span
                        className="public-site-divider"
                        aria-hidden="true"
                    />

                    <span className="public-site-product">
                        <b>Lab</b> Scheduling
                    </span>
                </Link>

                <button
                    className="public-site-menu-button"
                    type="button"
                    aria-label={
                        menuOpen ? 'Tutup menu' : 'Buka menu'
                    }
                    aria-expanded={menuOpen}
                    aria-controls="public-site-navigation"
                    onClick={() => {
                        setMenuOpen((open) => !open);
                        setGuideOpen(false);
                    }}
                >
                    {menuOpen ? (
                        <X size={22} />
                    ) : (
                        <Menu size={22} />
                    )}
                </button>

                <nav
                    id="public-site-navigation"
                    className={`public-site-nav${
                        menuOpen ? ' is-open' : ''
                    }`}
                    aria-label="Navigasi utama"
                >
                    {navigation.map((item) => (
                        <Link
                            key={item.page}
                            className={`public-site-nav-link${
                                item.page === active
                                    ? ' is-active'
                                    : ''
                            }`}
                            href={item.href}
                            aria-current={
                                item.page === active
                                    ? 'page'
                                    : undefined
                            }
                            onClick={closeMenus}
                        >
                            {item.label}
                        </Link>
                    ))}

                    <div
                        ref={guideMenuRef}
                        className={`public-site-guide-menu${
                            active === 'panduan'
                                ? ' is-active'
                                : ''
                        }${guideOpen ? ' is-open' : ''}`}
                        onMouseEnter={handleGuideMouseEnter}
                        onMouseLeave={handleGuideMouseLeave}
                    >
                        <button
                            type="button"
                            className={`public-site-nav-link public-site-guide-trigger${
                                active === 'panduan'
                                    ? ' is-active'
                                    : ''
                            }`}
                            aria-expanded={guideOpen}
                            aria-haspopup="true"
                            aria-controls="public-site-guide-dropdown"
                            onClick={handleGuideClick}
                        >
                            <span>Panduan</span>

                            <ChevronDown
                                size={14}
                                aria-hidden="true"
                            />
                        </button>

                        <div
                            id="public-site-guide-dropdown"
                            className="public-site-guide-dropdown"
                            aria-hidden={!guideOpen}
                            inert={!guideOpen}
                        >
                            <Link
                                href="/panduan"
                                onClick={closeMenus}
                            >
                                <span>SOP &amp; Tata Tertib</span>
                                <small>
                                    Aturan dan prosedur laboratorium
                                </small>
                            </Link>

                            <Link
                                href="/panduan/software"
                                onClick={closeMenus}
                            >
                                <span>Software Pendukung</span>
                                <small>
                                    Daftar perangkat lunak akademik
                                </small>
                            </Link>
                        </div>
                    </div>
                </nav>

                <Link
                    className="public-site-login"
                    href={auth.user ? dashboard() : login()}
                    onClick={closeMenus}
                >
                    <UsersRound size={17} />
                    <span>
                        {auth.user ? 'Dashboard' : 'Masuk'}
                    </span>
                </Link>
            </header>
            <div
                className="public-site-header-spacer"
                aria-hidden="true"
            />

            {showBackToTop && (
                <button
                    type="button"
                    className="public-site-back-to-top"
                    aria-label="Kembali ke atas"
                    title="Kembali ke atas"
                    onClick={handleBackToTop}
                >
                    <ArrowUp size={21} />
                </button>
            )}
        </>
    );
}
