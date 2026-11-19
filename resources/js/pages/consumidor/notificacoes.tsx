import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bell,
    CheckCircle2,
    CircleAlert,
    Trash2,
    MoreHorizontal,
} from 'lucide-react';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Page } from '@/components/junta/Page';
import { EmptyState } from '@/components/junta/EmptyState';
import { SegmentedControl } from '@/components/junta/ConsumerUi';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { mutate, errorMessage } from '@/lib/server-mutation';
import { readAll, update, destroy } from '@/routes/consumidor/notificacoes';
export default function Notifications() {
    const { data } = usePrototypeStore();
    const [filter, setFilter] = useState('all');
    const [busy, setBusy] = useState(false);
    const today = new Date().toLocaleDateString('en-CA', {
        timeZone: 'Africa/Maputo',
    });
    const week = Date.parse(today) - 6 * 86400000;
    const groups: Record<string, typeof data.notifications> = {
        Hoje: [],
        'Esta semana': [],
        Antes: [],
    };
    data.notifications
        .filter((n) => filter === 'all' || !n.read)
        .forEach((n) => {
            const day = new Date(n.createdAt).toLocaleDateString('en-CA', {
                timeZone: 'Africa/Maputo',
            });
            groups[
                day === today
                    ? 'Hoje'
                    : Date.parse(day) >= week
                      ? 'Esta semana'
                      : 'Antes'
            ].push(n);
        });
    const perform = (task: () => Promise<void>) => {
        if (busy) return;
        setBusy(true);
        void task()
            .catch((failure) => toast.error(errorMessage(failure)))
            .finally(() => setBusy(false));
    };
    return (
        <Page
            className="consumer-page"
            title="Notificações"
            actions={
                data.notifications.some((n) => !n.read) && (
                    <button
                        type="button"
                        className="junta-btn junta-btn-sm junta-btn-outline"
                        disabled={busy}
                        onClick={() => perform(() => mutate(readAll()))}
                    >
                        Marcar todas como lidas
                    </button>
                )
            }
        >
            <SegmentedControl
                label="Filtrar notificações"
                value={filter}
                onChange={setFilter}
                options={[
                    { value: 'all', label: 'Todas' },
                    { value: 'unread', label: 'Não lidas' },
                ]}
            />
            {Object.entries(groups).some(([, items]) => items.length) ? (
                Object.entries(groups)
                    .filter(([, items]) => items.length)
                    .map(([label, items]) => (
                        <section
                            key={label}
                            className="consumer-notification-group"
                        >
                            <h2>{label}</h2>
                            <ul>
                                {items.map((n) => {
                                    const Icon =
                                        n.type === 'success'
                                            ? CheckCircle2
                                            : ['error', 'warning'].includes(
                                                    n.type,
                                                )
                                              ? CircleAlert
                                              : Bell;
                                    return (
                                        <li
                                            key={n.id}
                                            data-unread={!n.read || undefined}
                                        >
                                            <Icon
                                                className="size-5 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <button
                                                type="button"
                                                className="consumer-notification-open"
                                                disabled={busy}
                                                onClick={() =>
                                                    perform(async () => {
                                                        if (!n.read)
                                                            await mutate(
                                                                update(n.id),
                                                                { read: true },
                                                            );
                                                        if (n.link)
                                                            router.visit(
                                                                n.link,
                                                            );
                                                    })
                                                }
                                            >
                                                <strong>{n.title}</strong>
                                                <span>{n.message}</span>
                                                <time dateTime={n.createdAt}>
                                                    {new Date(
                                                        n.createdAt,
                                                    ).toLocaleTimeString(
                                                        'pt-PT',
                                                        {
                                                            timeZone:
                                                                'Africa/Maputo',
                                                            hour: '2-digit',
                                                            minute: '2-digit',
                                                        },
                                                    )}
                                                </time>
                                                {!n.read && (
                                                    <i aria-label="Não lida" />
                                                )}
                                            </button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="consumer-notification-delete"
                                                        aria-label={`Opções: ${n.title}`}
                                                        disabled={busy}
                                                    >
                                                        <MoreHorizontal className="size-4" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            perform(() =>
                                                                mutate(
                                                                    destroy(
                                                                        n.id,
                                                                    ),
                                                                ),
                                                            )
                                                        }
                                                        disabled={busy}
                                                    >
                                                        <Trash2 className="size-4" />
                                                        Eliminar
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))
            ) : (
                <EmptyState
                    title={
                        filter === 'unread'
                            ? 'Sem notificações por ler'
                            : 'Ainda não tem notificações'
                    }
                    description="As novidades das suas compras aparecem aqui."
                />
            )}
        </Page>
    );
}
