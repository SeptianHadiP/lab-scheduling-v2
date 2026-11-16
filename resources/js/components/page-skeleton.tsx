import { Skeleton } from '@/components/ui/skeleton';
import type { SkeletonVariant } from '@/hooks/use-navigation-skeleton';

const box = 'overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border';

function TableSkeleton() {
    return (
        <>
            <div className="flex items-center justify-between">
                <Skeleton className="h-7 w-48" />
                <Skeleton className="h-9 w-36" />
            </div>
            <div className={box}>
                <div className="flex gap-4 border-b p-3">
                    {Array.from({ length: 5 }, (_, i) => (
                        <Skeleton key={i} className="h-4 flex-1" />
                    ))}
                </div>
                {Array.from({ length: 7 }, (_, row) => (
                    <div key={row} className="flex gap-4 border-b p-3 last:border-0">
                        {Array.from({ length: 5 }, (_, col) => (
                            <Skeleton key={col} className={col === 0 ? 'h-4 flex-[2]' : 'h-4 flex-1'} />
                        ))}
                    </div>
                ))}
            </div>
        </>
    );
}

function FormSkeleton() {
    return (
        <div className="max-w-lg space-y-5">
            <Skeleton className="h-7 w-56" />
            {Array.from({ length: 4 }, (_, i) => (
                <div key={i} className="grid gap-2">
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-9 w-full" />
                </div>
            ))}
            <div className="flex gap-2">
                <Skeleton className="h-9 w-24" />
                <Skeleton className="h-9 w-20" />
            </div>
        </div>
    );
}

function DetailSkeleton() {
    return (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-2">
                    <Skeleton className="h-7 w-72 max-w-full" />
                    <Skeleton className="h-4 w-40" />
                </div>
                <Skeleton className="h-6 w-28 rounded-full" />
            </div>
            <div className={`${box} grid gap-4 p-4 md:grid-cols-2`}>
                {Array.from({ length: 6 }, (_, i) => (
                    <div key={i} className="space-y-2">
                        <Skeleton className="h-3 w-24" />
                        <Skeleton className="h-4 w-48 max-w-full" />
                    </div>
                ))}
            </div>
            <div className={box}>
                {Array.from({ length: 4 }, (_, row) => (
                    <div key={row} className="flex gap-4 border-b p-3 last:border-0">
                        {Array.from({ length: 4 }, (_, col) => (
                            <Skeleton key={col} className="h-4 flex-1" />
                        ))}
                    </div>
                ))}
            </div>
            <div className="flex gap-2">
                <Skeleton className="h-9 w-36" />
                <Skeleton className="h-9 w-20" />
                <Skeleton className="h-9 w-24" />
            </div>
        </>
    );
}

function DashboardSkeleton() {
    return (
        <>
            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                {Array.from({ length: 3 }, (_, i) => (
                    <Skeleton key={i} className="aspect-video rounded-xl" />
                ))}
            </div>
            <Skeleton className="h-96 rounded-xl" />
        </>
    );
}

function CalendarSkeleton() {
    return (
        <>
            <div className="flex items-center justify-between">
                <Skeleton className="h-7 w-48" />
                <Skeleton className="h-9 w-56" />
            </div>
            <div className={`${box} grid grid-cols-7`}>
                {Array.from({ length: 35 }, (_, i) => (
                    <div key={i} className="min-h-20 space-y-2 border-b border-r p-2">
                        <Skeleton className="h-3 w-5" />
                        {i % 3 === 0 && <Skeleton className="h-4 w-full" />}
                    </div>
                ))}
            </div>
        </>
    );
}

export function PageSkeleton({ variant }: { variant: SkeletonVariant }) {
    return (
        <div role="status" aria-busy="true" aria-live="polite" className="space-y-4 p-4">
            <span className="sr-only">Memuat halaman…</span>
            {variant === 'dashboard' && <DashboardSkeleton />}
            {variant === 'form' && <FormSkeleton />}
            {variant === 'detail' && <DetailSkeleton />}
            {variant === 'table' && <TableSkeleton />}
            {variant === 'calendar' && <CalendarSkeleton />}
        </div>
    );
}