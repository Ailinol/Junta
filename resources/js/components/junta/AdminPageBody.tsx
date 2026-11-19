import { router, usePage } from '@inertiajs/react';
import { CircleAlert, LoaderCircle, RefreshCw } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function AdminPageBody({ children }: { children: ReactNode }) {
    const { component, props } = usePage();
    const isAdministration = component.startsWith('admin/');
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const reading = useRef(false);

    useEffect(() => {
        if (!isAdministration) return;
        const subscriptions = [
            router.on('start', (event) => {
                if (event.detail.visit.method !== 'get') return;
                reading.current = true;
                setError('');
                setPending(true);
            }),
            router.on('finish', (event) => {
                if (event.detail.visit.method !== 'get') return;
                reading.current = false;
                setPending(false);
            }),
            router.on('networkError', (event) => {
                if (!reading.current) return;
                event.preventDefault();
                setPending(false);
                setError(
                    'Não foi possível carregar a informação. Verifique a ligação e volte a carregar esta página.',
                );
            }),
            router.on('httpException', (event) => {
                if (!reading.current) return;
                event.preventDefault();
                setPending(false);
                setError(
                    'O servidor não conseguiu carregar a informação. Volte a carregar esta página; os dados anteriores continuam abaixo.',
                );
            }),
        ];
        return () => subscriptions.forEach((unsubscribe) => unsubscribe());
    }, [isAdministration]);

    if (!isAdministration) return children;
    const loading = pending || !props.business;
    return (
        <>
            {error && (
                <div role="alert" className="junta-admin-read-error">
                    <CircleAlert
                        className="size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div className="min-w-0 flex-1">
                        <p>{error}</p>
                    </div>
                    <button
                        type="button"
                        className="junta-btn junta-btn-sm junta-btn-outline"
                        onClick={() => router.reload()}
                    >
                        <RefreshCw className="size-4" aria-hidden="true" />
                        Voltar a carregar
                    </button>
                </div>
            )}
            {loading && (
                <div
                    className="junta-admin-loading"
                    role="status"
                    aria-live="polite"
                >
                    <p className="mb-5 flex items-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle
                            className="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        A carregar informação…
                    </p>
                    <div aria-hidden="true" className="grid gap-5">
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            {[0, 1, 2, 3].map((key) => (
                                <Skeleton
                                    key={key}
                                    className="h-20 rounded-lg"
                                />
                            ))}
                        </div>
                        <Skeleton className="h-12 rounded-lg" />
                        <Skeleton className="h-64 rounded-lg" />
                    </div>
                </div>
            )}
            <div hidden={loading} aria-busy={loading}>
                {children}
            </div>
        </>
    );
}
