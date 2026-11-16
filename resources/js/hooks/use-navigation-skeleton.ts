import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export type SkeletonVariant = 'table' | 'form' | 'detail' | 'dashboard' | 'calendar';

/** Skeleton baru muncul jika perpindahan lebih lama dari ini, supaya tidak berkedip. */
const SHOW_AFTER_MS = 150;

/** Setelah muncul, skeleton tampil minimal selama ini. */
const MIN_VISIBLE_MS = 300;

type VisitLike = {
    method?: string;
    url?: URL | string;
    only?: string[];
    except?: string[];
    prefetch?: boolean;
};

export function skeletonVariantFor(pathname: string): SkeletonVariant {
    if (pathname === '/calendar') {
        return 'calendar';
    }
    
    if (pathname === '/dashboard') {
        return 'dashboard';
    }

    if (/^\/reservations\/\d+$/.test(pathname)) {
        return 'detail';
    }

    if (
        /\/(create|edit)$/.test(pathname) ||
        pathname.startsWith('/settings') ||
        pathname === '/students/import'
    ) {
        return 'form';
    }

    return 'table';
}

/**
 * Hanya perpindahan halaman penuh (GET) ke path lain di situs yang sama.
 * Filter, paginasi, reload parsial, prefetch, dan kirim form diabaikan.
 */
function pageNavigationUrl(visit: VisitLike): URL | null {
    if ((visit.method ?? 'get').toLowerCase() !== 'get' || visit.prefetch) {
        return null;
    }

    if (visit.only?.length || visit.except?.length || !visit.url) {
        return null;
    }

    const target = new URL(String(visit.url), window.location.href);

    if (target.origin !== window.location.origin || target.pathname === window.location.pathname) {
        return null;
    }

    return target;
}

export function useNavigationSkeleton(): SkeletonVariant | null {
    const [variant, setVariant] = useState<SkeletonVariant | null>(null);

    useEffect(() => {
        let showTimer: ReturnType<typeof setTimeout> | undefined;
        let hideTimer: ReturnType<typeof setTimeout> | undefined;
        let shownAt: number | null = null;
        let activeUrl: string | null = null;

        const hide = () => {
            clearTimeout(showTimer);

            if (shownAt === null) {
                return;
            }

            const remaining = MIN_VISIBLE_MS - (Date.now() - shownAt);

            hideTimer = setTimeout(() => {
                shownAt = null;
                setVariant(null);
            }, Math.max(remaining, 0));
        };

        const offStart = router.on('start', (event) => {
            const visit = event.detail.visit as unknown as VisitLike;
            const target = pageNavigationUrl(visit);

            if (!target) {
                return;
            }

            const next = skeletonVariantFor(target.pathname);

            activeUrl = String(visit.url);
            clearTimeout(hideTimer);
            clearTimeout(showTimer);

            // Sudah tampil dari perpindahan sebelumnya: cukup ganti bentuknya.
            if (shownAt !== null) {
                setVariant(next);

                return;
            }

            showTimer = setTimeout(() => {
                shownAt = Date.now();
                setVariant(next);
            }, SHOW_AFTER_MS);
        });

        const offFinish = router.on('finish', (event) => {
            const visit = event.detail.visit as unknown as VisitLike;

            // Abaikan penutupan kunjungan lama yang sudah digantikan kunjungan baru.
            if (activeUrl === null || String(visit.url) !== activeUrl) {
                return;
            }

            activeUrl = null;
            hide();
        });

        return () => {
            offStart();
            offFinish();
            clearTimeout(showTimer);
            clearTimeout(hideTimer);
        };
    }, []);

    return variant;
}