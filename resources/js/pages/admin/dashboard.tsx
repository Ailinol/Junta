import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Page } from '@/components/junta/Page';
import { TaskInbox } from '@/components/junta/OrganizerConsole';
import { EmptyState } from '@/components/junta/EmptyState';
import { PurchasePagination } from '@/components/junta/PurchaseForms';
import { Age } from '@/components/junta/AdminFinance';
import { auditLabels } from '@/lib/audit-labels';
import { historico } from '@/routes/admin';
import type { AdminTask, AuditRecord } from '@/types/admin';
import type { PurchasePage } from '@/types/purchase';
export default function Dashboard({
    adminDashboard,
}: {
    adminDashboard: { tasks: PurchasePage<AdminTask>; activity: AuditRecord[] };
}) {
    return (
        <Page
            title="Início"
            description="As tarefas que precisam da sua intervenção."
        >
            <section className="admin-panel">
                <h2>Precisa de si ({adminDashboard.tasks.total})</h2>
                {adminDashboard.tasks.total ? (
                    <>
                        <TaskInbox tasks={adminDashboard.tasks.data} />
                        <PurchasePagination page={adminDashboard.tasks} />
                    </>
                ) : (
                    <EmptyState
                        icon={CheckCircle2}
                        title="Tudo em dia"
                        description="Não existem intervenções pendentes. As novas tarefas aparecerão aqui."
                    />
                )}
            </section>
            <section className="admin-panel">
                <div className="admin-section-heading">
                    <h2>Actividade recente</h2>
                    <Link href={historico()}>Ver histórico</Link>
                </div>
                <ul className="admin-activity">
                    {adminDashboard.activity.map((entry) => (
                        <li key={entry.id}>
                            <div>
                                <strong>
                                    {auditLabels[entry.action] ??
                                        'Registo actualizado'}
                                </strong>
                                <p>{entry.actor}</p>
                            </div>
                            <Age value={entry.createdAt} />
                        </li>
                    ))}
                </ul>
                {!adminDashboard.activity.length && (
                    <p className="admin-muted">
                        Ainda não há actividade registada.
                    </p>
                )}
            </section>
        </Page>
    );
}
