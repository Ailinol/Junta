import type { AiAnalysis } from '@/components/junta/AiReviewPanel';
import { formatMZN } from '@/lib/formatters';
import { CheckCircle2 } from 'lucide-react';

export function ProposalRecommendation({
    result,
    proposalNames,
    selectableProposalIds,
    onChoose,
}: {
    result: AiAnalysis;
    proposalNames: Record<number, string>;
    selectableProposalIds: number[];
    onChoose?: (proposalId: number) => void;
}) {
    const rows = result.rows ?? [];
    const best = rows.find(
        (row) => row.proposalId === result.recommendedProposalId,
    );
    const recommendation = result.recommendation;
    const supplier = best
        ? (proposalNames[best.proposalId] ?? best.supplierName)
        : '';
    const alternative = rows.find(
        (row) => row.proposalId === recommendation?.alternativeProposalId,
    );
    const difference = recommendation?.priceDifferenceCents ?? 0;

    return (
        <div className="grid gap-4">
            {best ? (
                <article
                    aria-label="Proposta recomendada"
                    className="grid gap-4 rounded-xl border border-primary/40 bg-primary/5 p-4 sm:p-5"
                >
                    <p className="flex items-center gap-2 font-semibold text-primary">
                        <CheckCircle2
                            className="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        Escolha recomendada
                    </p>
                    <div className="grid gap-2">
                        <h3 className="text-xl font-semibold">
                            Escolha {supplier}
                        </h3>
                        <p>
                            É a melhor opção com os critérios definidos para
                            esta campanha. Proposta #{best.proposalId}, para a
                            procura registada de {result.quantity}{' '}
                            {result.quantity === 1 ? 'unidade' : 'unidades'}.
                        </p>
                    </div>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">
                                Total com transporte declarado
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold text-primary">
                                {best.totalCents == null
                                    ? 'Por confirmar'
                                    : formatMZN(best.totalCents / 100)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Prazo de entrega
                            </dt>
                            <dd className="mt-1 text-2xl font-semibold">
                                {best.deliveryDays}{' '}
                                {best.deliveryDays === 1 ? 'dia' : 'dias'}
                            </dd>
                        </div>
                    </dl>
                    {difference !== 0 && alternative && (
                        <p className="font-medium">
                            {difference > 0
                                ? `Poupa ${formatMZN(difference / 100)}`
                                : `Custa mais ${formatMZN(-difference / 100)}`}{' '}
                            face à próxima opção da classificação,{' '}
                            {proposalNames[alternative.proposalId] ??
                                alternative.supplierName}
                            .
                        </p>
                    )}
                    {recommendation?.reasons && (
                        <ul className="list-disc space-y-1 pl-5">
                            {recommendation.reasons.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    )}
                    {recommendation?.closeCall && (
                        <p className="text-muted-foreground">
                            As pontuações estão próximas. A recomendação
                            considera o primeiro lugar e, em empate de
                            pontuação, o custo total, o prazo e o histórico.
                        </p>
                    )}
                    {(best.warnings?.length ?? 0) > 0 && (
                        <details className="rounded-lg border border-border bg-background p-3">
                            <summary className="cursor-pointer font-medium">
                                Pontos a esclarecer com o fornecedor (
                                {best.warnings!.length})
                            </summary>
                            <ul className="mt-3 list-disc space-y-1 pl-5">
                                {best.warnings!.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                            <p className="mt-3 text-muted-foreground">
                                São avisos da análise do texto. Confirme estes
                                pontos antes de aceitar as condições.
                            </p>
                        </details>
                    )}
                    {onChoose && (
                        <button
                            type="button"
                            className="junta-btn junta-btn-md justify-self-start junta-btn-primary"
                            disabled={
                                !selectableProposalIds.includes(best.proposalId)
                            }
                            onClick={() => onChoose(best.proposalId)}
                        >
                            Escolher esta proposta
                        </button>
                    )}
                    {onChoose &&
                        !selectableProposalIds.includes(best.proposalId) && (
                            <p className="text-muted-foreground">
                                A selecção já não está disponível nas condições
                                actuais. Actualize a página para rever o estado
                                da proposta.
                            </p>
                        )}
                </article>
            ) : (
                <div
                    className="grid gap-2 rounded-xl border border-border bg-muted p-4"
                    role="status"
                >
                    <h3 className="font-semibold">
                        {recommendation?.status === 'equivalent'
                            ? 'As melhores propostas estão empatadas'
                            : 'É necessário corrigir os impedimentos comerciais'}
                    </h3>
                    <p>
                        {recommendation?.message ?? result.findings.join(' ')}
                    </p>
                </div>
            )}
            <details
                className="rounded-xl border border-border bg-background p-4"
                open={!best}
            >
                <summary className="cursor-pointer font-medium">
                    Ver comparação completa ({rows.length} propostas)
                </summary>
                <div className="mt-4 grid gap-4">
                    {rows.map((row) => (
                        <article
                            key={row.proposalId}
                            className="grid gap-2 rounded-lg border border-border p-3 sm:p-4"
                        >
                            <h4 className="font-semibold">
                                #{row.proposalId} ·{' '}
                                {proposalNames[row.proposalId] ??
                                    row.supplierName}{' '}
                                ·{' '}
                                {row.proposalId === best?.proposalId
                                    ? 'Recomendada'
                                    : row.eligible
                                      ? 'Alternativa comparável'
                                      : 'Não elegível'}
                            </h4>
                            <p>
                                Total:{' '}
                                {row.totalCents == null
                                    ? 'Por confirmar'
                                    : formatMZN(row.totalCents / 100)}{' '}
                                · Entrega: {row.deliveryDays}{' '}
                                {row.deliveryDays === 1 ? 'dia' : 'dias'}.
                            </p>
                            <p className="text-muted-foreground">
                                Produto:{' '}
                                {row.productTotalCents == null
                                    ? 'Por confirmar'
                                    : formatMZN(
                                          row.productTotalCents / 100,
                                      )}{' '}
                                · Transporte:{' '}
                                {row.transportTotalCents == null
                                    ? 'Por confirmar'
                                    : formatMZN(row.transportTotalCents / 100)}
                                .
                            </p>
                            <p className="text-muted-foreground">
                                {row.supplierHistory.finished === 0
                                    ? 'Sem ordens terminadas no histórico; pontuação neutra.'
                                    : `${row.supplierHistory.fullyReceived} ${row.supplierHistory.fullyReceived === 1 ? 'recepção completa' : 'recepções completas'} em ${row.supplierHistory.finished} ${row.supplierHistory.finished === 1 ? 'ordem terminada' : 'ordens terminadas'}.`}{' '}
                                {row.supplierHistory.inProgress > 0 &&
                                    `${row.supplierHistory.inProgress} ${row.supplierHistory.inProgress === 1 ? 'ordem em curso' : 'ordens em curso'}. `}
                                A pontualidade ainda não tem dados verificáveis.
                            </p>
                            {row.score != null && (
                                <p className="text-muted-foreground">
                                    Pontuação: {row.score}/100 · Custo:{' '}
                                    {row.costScore}/100 · Prazo:{' '}
                                    {row.deliveryScore}/100 · Clareza:{' '}
                                    {row.clarity}/100 · Histórico:{' '}
                                    {row.historyScore}/100.
                                </p>
                            )}
                            {row.findings.length > 0 && (
                                <ul className="list-disc space-y-1 pl-5">
                                    {row.findings.map((finding) => (
                                        <li key={finding}>{finding}</li>
                                    ))}
                                </ul>
                            )}
                            {(row.warnings?.length ?? 0) > 0 && (
                                <div className="text-muted-foreground">
                                    <p>Pontos a esclarecer:</p>
                                    <ul className="mt-1 list-disc space-y-1 pl-5">
                                        {row.warnings!.map((warning) => (
                                            <li key={warning}>{warning}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </article>
                    ))}
                    <details>
                        <summary className="cursor-pointer font-medium">
                            Como foi calculada a recomendação
                        </summary>
                        <p className="mt-3 leading-6 text-muted-foreground">
                            {result.suggestion}
                        </p>
                        <p className="mt-3 text-muted-foreground">
                            Os avisos sobre o texto não excluem uma proposta por
                            si só. Propostas expiradas, stock insuficiente e
                            condições comerciais por confirmar ficam fora da
                            recomendação.
                        </p>
                    </details>
                </div>
            </details>
        </div>
    );
}
