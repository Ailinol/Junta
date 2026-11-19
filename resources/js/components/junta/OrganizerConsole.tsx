import type { AdminTask } from '@/types/admin';
import { relativeAge, maputoDate } from '@/lib/organizer-presentation';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { DeadlineLabel } from '@/components/junta/OrganizerUi';
import { organizerActionUrl } from '@/lib/organizer-routes';
import type { OrganizerCampaign } from '@/types/organizer';
export function ActionBanner(
    props:
        | {
              title: string;
              description: string;
              action?: { label: string; href: string };
          }
        | {
              view: OrganizerCampaign;
              section: 'resumo' | 'participantes' | 'propostas';
              onAction?: () => void;
          },
) {
    if ('title' in props)
        return (
            <section
                className="organizer-action-banner"
                aria-label="Próxima acção"
            >
                <div>
                    <h2>{props.title}</h2>
                    <p>{props.description}</p>
                </div>
                {props.action && (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-primary"
                        href={props.action.href}
                    >
                        {props.action.label}
                    </Link>
                )}
            </section>
        );
    return <OrganizerActionBanner {...props} />;
}
function OrganizerActionBanner({
    view,
    section,
    onAction,
}: {
    view: OrganizerCampaign;
    section: 'resumo' | 'participantes' | 'propostas';
    onAction?: () => void;
}) {
    const state = view.workspace;
    if (state.isFinal) return null;
    const action = state.primaryAction;
    if (!action)
        return section !== 'resumo' && state.waitingOn ? (
            <p className="organizer-console-waiting">
                {state.blocker ?? state.description}
            </p>
        ) : null;
    const inline =
        (section === 'resumo' &&
            action.target === 'purchase' &&
            !['close_interest', 'receive'].includes(action.code)) ||
        (section === 'propostas' && action.target === 'proposals');
    return (
        <section className="organizer-action-banner" aria-label="Próxima acção">
            <div>
                <h2>{action.label}</h2>
                <p>{state.description}</p>
            </div>
            {!inline &&
                (onAction ? (
                    <button
                        type="button"
                        className="junta-btn junta-btn-md junta-btn-primary"
                        onClick={onAction}
                    >
                        {view.list.action?.label ?? action.label}
                    </button>
                ) : (
                    <Link
                        className="junta-btn junta-btn-md junta-btn-primary"
                        href={organizerActionUrl(view.campaign.id, action)}
                    >
                        {view.list.action?.label ?? action.label}
                    </Link>
                ))}
        </section>
    );
}
export function FilterBar({ children }: { children: ReactNode }) {
    return <div className="organizer-console-filterbar">{children}</div>;
}
export function TaskInbox({
    tasks,
}: {
    tasks: OrganizerCampaign[] | AdminTask[];
}) {
    return (
        <ul className="organizer-console-tasks">
            {tasks.map((task) => {
                const view = 'workspace' in task ? task : null;
                const title =
                    view?.campaign.title ?? ('title' in task ? task.title : '');
                const description = view
                    ? `${view.workspace.primaryAction?.label ?? ''}${view.workspace.code === 'evaluation' ? ` · ${view.metrics.openProposals} propostas` : ''}`
                    : 'description' in task
                      ? task.description
                      : '';
                const href = view
                    ? organizerActionUrl(
                          view.campaign.id,
                          view.workspace.primaryAction,
                      )
                    : 'primaryAction' in task
                      ? task.primaryAction.href
                      : '';
                const label = view
                    ? (view.list.action?.label ??
                      view.workspace.primaryAction?.label)
                    : 'primaryAction' in task
                      ? task.primaryAction.label
                      : '';
                return (
                    <li
                        key={
                            view?.campaign.id ??
                            ('id' in task ? task.id : title)
                        }
                    >
                        <div>
                            <Link href={href}>{title}</Link>
                            <p>{description}</p>
                        </div>
                        {view ? (
                            <DeadlineLabel
                                deadline={view.workspace.deadline}
                                compact
                                blocked={view.workspace.deadline?.blocked}
                            />
                        ) : 'since' in task ? (
                            <time
                                dateTime={task.since}
                                title={maputoDate(task.since) + ' · Maputo'}
                            >
                                {relativeAge(task.since)}
                            </time>
                        ) : null}
                        <Link
                            href={href}
                            className="junta-btn junta-btn-sm junta-btn-outline"
                        >
                            {label}
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

export function Stepper({ step, labels }: { step: number; labels: string[] }) {
    return (
        <div className="organizer-console-stepper">
            <p>
                Passo {step + 1} de {labels.length}
            </p>
            <ol aria-label="Passos de criação">
                {labels.map((label, index) => (
                    <li
                        key={label}
                        aria-current={index === step ? 'step' : undefined}
                    >
                        <span>{index + 1}</span>
                        {label}
                    </li>
                ))}
            </ol>
        </div>
    );
}
