import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { ProposalRecommendation } from '@/components/junta/ProposalRecommendation';
import AiReviewController from '@/actions/App/Http/Controllers/AiReviewController';
import { HttpResponseError } from '@inertiajs/core';
import { useHttp, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';

type Weights = {
    cost: number;
    delivery: number;
    clarity: number;
    history: number;
};
type Payload = Record<string, string | number | boolean | null | Weights>;
export type AiAnalysis = {
    analysisId: number;
    model: string;
    createdAt: string;
    expiresAt: string;
    clarity?: number;
    confidence?: number;
    quantity?: number;
    unit?: string;
    recommendedProposalId?: number | null;
    recommendation?: {
        status: 'recommended' | 'equivalent' | 'blocked';
        message: string;
        reasons: string[];
        warnings: string[];
        closeCall: boolean;
        alternativeProposalId: number | null;
        priceDifferenceCents: number | null;
    };
    findings: string[];
    suggestion: string;
    rows?: {
        proposalId: number;
        supplierName: string;
        totalCents: number | null;
        deliveryDays: number;
        clarity: number;
        confidence: number;
        findings: string[];
        warnings: string[];
        eligible: boolean;
        score: number | null;
        costScore: number | null;
        deliveryScore: number | null;
        productTotalCents: number | null;
        transportTotalCents: number | null;
        historyScore: number;
        supplierHistory: {
            orders: number;
            fullyReceived: number;
            partial: number;
            finished: number;
            inProgress: number;
            onTimeRate: null;
        };
    }[];
};

function failureMessage(failure: unknown): string {
    if (failure instanceof HttpResponseError) {
        const data = failure.response.data as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        if (data.errors) return Object.values(data.errors).flat().join(' ');
        if (data.message) return data.message;
    }
    return 'Não foi possível contactar o serviço. Pode continuar a revisão manual.';
}

export function AiReviewPanel({
    kind,
    payload,
    campaignId,
    sourceVersion = '',
    proposalNames = {},
    selectableProposalIds = [],
    onChooseProposal,
}: {
    kind: 'campaign' | 'proposal' | 'comparison';
    payload: Payload;
    campaignId?: number;
    sourceVersion?: string;
    proposalNames?: Record<number, string>;
    selectableProposalIds?: number[];
    onChooseProposal?: (proposalId: number) => void;
}) {
    const api = useHttp<Payload, AiAnalysis>({});
    const { ai } = usePage<{ ai: { enabled: boolean } }>().props;
    const decision = useHttp<{ decision: string }, { message: string }>({
        decision: '',
    });
    const [weights, setWeights] = useState<Weights>({
        cost: 45,
        delivery: 20,
        clarity: 20,
        history: 15,
    });
    const [saved, setSaved] = useState<{
        result: AiAnalysis;
        fingerprint: string;
    } | null>(null);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [now, setNow] = useState(() => Date.now());
    const fingerprint = JSON.stringify([payload, weights, sourceVersion]);
    const result = saved?.result;
    const stale =
        !!saved &&
        (saved.fingerprint !== fingerprint ||
            new Date(saved.result.expiresAt).getTime() <= now);
    const totalWeight =
        weights.cost + weights.delivery + weights.clarity + weights.history;

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 10000);
        return () => window.clearInterval(timer);
    }, []);

    const analyse = async () => {
        setError('');
        setMessage('');
        setSaved(null);
        api.transform(() => (kind === 'comparison' ? { weights } : payload));
        const endpoint =
            kind === 'comparison'
                ? AiReviewController.compare(campaignId!).url
                : kind === 'campaign'
                  ? AiReviewController.campaign().url
                  : AiReviewController.proposal().url;
        try {
            const response = await api.post(endpoint);
            setNow(Date.now());
            setSaved({ result: response, fingerprint });
        } catch (failure) {
            setError(failureMessage(failure));
        }
    };
    const record = async (value: 'reviewed' | 'dismissed') => {
        if (!result || stale) return;
        setError('');
        decision.transform(() => ({ decision: value }));
        try {
            const response = await decision.post(
                AiReviewController.decision(result.analysisId).url,
            );
            setMessage(response.message);
        } catch (failure) {
            setError(failureMessage(failure));
        }
    };

    return (
        <OperationalPanel
            className="my-6"
            title={
                kind === 'comparison'
                    ? 'IA para comparar propostas'
                    : kind === 'proposal'
                      ? 'IA para melhorar a sua proposta'
                      : 'Revisor de campanha com IA'
            }
        >
            <div className="space-y-4 text-sm">
                <p className="leading-6 text-muted-foreground">
                    {kind === 'comparison'
                        ? 'Obtenha uma recomendação da melhor proposta, com custo total, prazo e motivos da escolha. Pode ajustar o peso de cada critério.'
                        : kind === 'proposal'
                          ? 'Identifique condições pouco claras e pontos que pode melhorar antes de submeter. O assistente usa a sua oferta e a campanha, sem consultar propostas concorrentes.'
                          : 'Reveja a clareza da especificação e a coerência das condições antes de publicar.'}
                </p>
                {kind === 'comparison' && (
                    <fieldset
                        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        disabled={api.processing}
                    >
                        <legend className="mb-2 font-medium">
                            Peso dos critérios (%)
                        </legend>
                        {(
                            [
                                ['cost', 'Custo conhecido'],
                                ['delivery', 'Prazo'],
                                ['clarity', 'Clareza (IA)'],
                                ['history', 'Histórico de recepção'],
                            ] as const
                        ).map(([key, label]) => (
                            <label key={key} className="space-y-1">
                                <span>{label}</span>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="1"
                                    value={weights[key]}
                                    onChange={(event) =>
                                        setWeights({
                                            ...weights,
                                            [key]: Number(event.target.value),
                                        })
                                    }
                                    className="w-full rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                                />
                            </label>
                        ))}
                        <p className="text-muted-foreground sm:col-span-2 lg:col-span-4">
                            Total: {totalWeight}%. Os pesos devem somar 100%.
                        </p>
                    </fieldset>
                )}
                <p className="text-xs leading-5 text-muted-foreground">
                    Ao pedir a análise, os textos e as condições comerciais são
                    enviados à OpenAI. Evite dados pessoais nas descrições. A IA
                    pode errar; reveja as sugestões. A análise não altera nem
                    submete os seus dados.
                </p>
                {!ai.enabled && (
                    <p
                        role="status"
                        className="rounded-lg border border-border bg-muted p-3"
                    >
                        A integração de IA está preparada, mas ainda não foi
                        activada. Pode continuar a revisão manual.
                    </p>
                )}
                <button
                    type="button"
                    disabled={
                        !ai.enabled ||
                        api.processing ||
                        (kind === 'comparison' && totalWeight !== 100)
                    }
                    onClick={() => void analyse()}
                    className="junta-btn junta-btn-md gap-2 junta-btn-outline"
                >
                    <Sparkles className="size-4" aria-hidden="true" />
                    {api.processing
                        ? 'A analisar…'
                        : result
                          ? 'Actualizar análise'
                          : 'Pedir análise à IA'}
                </button>
                {api.processing && (
                    <p
                        role="status"
                        className="animate-pulse text-muted-foreground"
                    >
                        A rever as condições. Aguarde alguns segundos…
                    </p>
                )}
                {error && (
                    <p
                        role="alert"
                        className="rounded-lg border border-border bg-muted p-3"
                    >
                        {error}
                    </p>
                )}
                {result && (
                    <div className="space-y-4" aria-live="polite">
                        <p className="text-xs text-muted-foreground">
                            OpenAI · {result.model} ·{' '}
                            {new Date(result.createdAt).toLocaleString('pt-MZ')}
                            . Válida por até 5 minutos, se os dados se
                            mantiverem.
                        </p>
                        {stale ? (
                            <p
                                role="status"
                                className="font-medium text-destructive"
                            >
                                Os dados mudaram ou a análise expirou. Actualize
                                a análise antes de a utilizar.
                            </p>
                        ) : kind === 'comparison' ? (
                            <ProposalRecommendation
                                result={result}
                                proposalNames={proposalNames}
                                selectableProposalIds={selectableProposalIds}
                                onChoose={onChooseProposal}
                            />
                        ) : (
                            <>
                                {result.clarity != null && (
                                    <p>
                                        Clareza:{' '}
                                        <strong>{result.clarity}/100</strong> ·
                                        Confiança do modelo: {result.confidence}
                                        % (não garante exactidão).
                                    </p>
                                )}
                                {result.findings.length > 0 ? (
                                    <ul className="list-disc space-y-2 pl-5">
                                        {result.findings.map((finding) => (
                                            <li key={finding}>{finding}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p>
                                        Não foram assinalados problemas nesta
                                        análise. Confirme as condições antes de
                                        avançar.
                                    </p>
                                )}
                                <p className="leading-6 text-muted-foreground">
                                    {result.suggestion}
                                </p>
                                <p className="font-medium">
                                    Faça as correcções nos campos do formulário
                                    e reveja os dados antes de submeter.
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        disabled={
                                            decision.processing || !!message
                                        }
                                        onClick={() => void record('reviewed')}
                                        className="junta-btn junta-btn-sm junta-btn-outline"
                                    >
                                        Registar como revista
                                    </button>
                                    <button
                                        type="button"
                                        disabled={
                                            decision.processing || !!message
                                        }
                                        onClick={() => void record('dismissed')}
                                        className="junta-btn junta-btn-sm junta-btn-outline"
                                    >
                                        Ignorar sugestões
                                    </button>
                                </div>
                            </>
                        )}
                        {message && <p role="status">{message}</p>}
                    </div>
                )}
            </div>
        </OperationalPanel>
    );
}
