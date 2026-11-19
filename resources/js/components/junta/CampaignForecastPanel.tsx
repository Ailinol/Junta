import { useEffect, useState } from 'react';
import { HttpResponseError } from '@inertiajs/core';
import { useHttp, usePage, usePoll } from '@inertiajs/react';
import { ChartNoAxesCombined } from 'lucide-react';
import { forecast } from '@/actions/App/Http/Controllers/AiReviewController';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { campaignCategories } from '@/lib/campaign-presentation';
import { maputoDate } from '@/lib/organizer-presentation';
import type { Campaign } from '@/types/junta';

type Forecast = {
    state: 'estimated' | 'insufficient_data' | 'target_reached' | 'closed';
    model: string | null;
    createdAt: string;
    expiresAt: string;
    probabilityPercent: number | null;
    expectedQuantity: number | null;
    lowerQuantity: number | null;
    upperQuantity: number | null;
    method: string;
    findings: string[];
    features: {
        asOf: string;
        deadline: string;
        startedAt: string;
        targetQuantity: number;
        currentQuantity: number;
        participantCount: number;
        category: Campaign['category'];
        location: string;
        unit: string;
        remainingHours: number;
        averageUnitsPerDay: number;
        recentUnitsPerDay: number;
        previousUnitsPerDay: number | null;
        linearProjection: number;
        retainedInterestSeries: { date: string; quantity: number }[];
        historicalContext: { campaignCount: number; sameLocationCount: number };
    };
};

const number = (value: number) =>
    value.toLocaleString('pt-MZ', { maximumFractionDigits: 1 });

function ForecastChart({ result }: { result: Forecast }) {
    const f = result.features;
    const start = new Date(f.startedAt).getTime();
    const end = new Date(f.deadline).getTime();
    const current = new Date(f.asOf).getTime();
    const max =
        Math.max(
            f.targetQuantity,
            result.upperQuantity ?? 0,
            f.currentQuantity,
            1,
        ) * 1.12;
    const x = (time: number) =>
        44 +
        Math.max(0, Math.min(1, (time - start) / Math.max(1, end - start))) *
            530;
    const y = (quantity: number) => 186 - (quantity / max) * 158;
    const points = [
        `${x(start)},${y(0)}`,
        ...f.retainedInterestSeries.map(
            (p) =>
                `${x(new Date(`${p.date}T23:59:59Z`).getTime() > current ? current : new Date(`${p.date}T23:59:59Z`).getTime())},${y(p.quantity)}`,
        ),
        `${x(current)},${y(f.currentQuantity)}`,
    ].join(' ');
    return (
        <figure className="rounded-xl border border-border bg-background p-3">
            <p className="text-xs text-muted-foreground">
                Linha de meta: {number(f.targetQuantity)} {f.unit}
            </p>
            <svg
                viewBox="0 0 620 202"
                role="img"
                aria-label={`Procura registada: ${f.currentQuantity}; previsão: ${result.expectedQuantity}; meta: ${f.targetQuantity} ${f.unit}.`}
                className="w-full text-primary"
            >
                <title>Evolução da procura e cenários até ao prazo</title>
                <line
                    x1="44"
                    x2="574"
                    y1={y(f.targetQuantity)}
                    y2={y(f.targetQuantity)}
                    stroke="var(--muted-foreground)"
                    strokeDasharray="4 5"
                />
                <line
                    x1="44"
                    x2="574"
                    y1="186"
                    y2="186"
                    stroke="var(--border)"
                />
                {result.lowerQuantity != null &&
                    result.upperQuantity != null && (
                        <polygon
                            points={`${x(current)},${y(f.currentQuantity)} ${x(end)},${y(result.upperQuantity)} ${x(end)},${y(result.lowerQuantity)}`}
                            fill="currentColor"
                            opacity="0.12"
                        />
                    )}
                <polyline
                    points={points}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="3"
                    strokeLinejoin="round"
                />
                <line
                    x1={x(current)}
                    x2={x(end)}
                    y1={y(f.currentQuantity)}
                    y2={y(result.expectedQuantity ?? f.currentQuantity)}
                    stroke="currentColor"
                    strokeWidth="3"
                    strokeDasharray="6 5"
                />
                <circle
                    cx={x(current)}
                    cy={y(f.currentQuantity)}
                    r="4"
                    fill="currentColor"
                />
            </svg>
            <div className="mb-3 flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
                <span>Início</span>
                <span>Prazo {maputoDate(f.deadline)}</span>
            </div>
            <figcaption className="text-xs leading-5 text-muted-foreground">
                Linha contínua: quantidades actualmente activas por data de
                registo. Tracejado: projecção da IA. Área verde: cenários. O
                gráfico não reconstrói alterações nem desistências passadas.
            </figcaption>
        </figure>
    );
}

export function CampaignForecastPanel({
    campaign,
    sourceVersion,
}: {
    campaign: Campaign;
    sourceVersion: string;
}) {
    const api = useHttp<Record<string, never>, Forecast>({});
    const { ai } = usePage<{ ai: { enabled: boolean } }>().props;
    usePoll(60000, { only: ['organizerCampaign'] });
    const [saved, setSaved] = useState<{
        result: Forecast;
        version: string;
    } | null>(null);
    const [error, setError] = useState('');
    const [now, setNow] = useState(() => Date.now());
    const version = JSON.stringify([
        campaign.id,
        sourceVersion,
        campaign.updatedAt,
        campaign.currentQuantity,
        campaign.participantCount,
        campaign.deadline,
        campaign.targetQuantity,
    ]);
    const result = saved?.result;
    const stale =
        !!saved &&
        (saved.version !== version ||
            new Date(saved.result.expiresAt).getTime() <= now);
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 10000);
        return () => window.clearInterval(timer);
    }, []);
    const analyse = async () => {
        setError('');
        setSaved(null);
        try {
            const response = await api.post(forecast(campaign.id).url);
            setSaved({ result: response, version });
            setNow(Date.now());
        } catch (failure) {
            const data =
                failure instanceof HttpResponseError
                    ? (failure.response.data as {
                          message?: string;
                          errors?: Record<string, string[]>;
                      })
                    : null;
            setError(
                data?.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data?.message ??
                          'Não foi possível obter a previsão. Tente novamente mais tarde.'),
            );
        }
    };
    return (
        <OperationalPanel title="Previsão da campanha" emphasis="brand">
            <div className="grid min-w-0 gap-4 text-sm">
                <p className="leading-6 text-muted-foreground">
                    Estime a possibilidade de atingir a meta de interesse e a
                    quantidade provável até ao prazo, a partir da procura,
                    participantes, ritmo das adesões, categoria, localização e
                    tempo restante.
                </p>
                <p className="text-xs leading-5 text-muted-foreground">
                    A previsão é actualizada a pedido. São enviados à OpenAI
                    apenas dados agregados e a localização pública da campanha.
                    A leitura das adesões é actualizada a cada minuto, sem novos
                    pedidos à IA.
                </p>
                {!ai.enabled && (
                    <p role="status">
                        A IA está desactivada. Pode continuar a acompanhar a
                        procura registada.
                    </p>
                )}
                <button
                    type="button"
                    disabled={!ai.enabled || api.processing}
                    onClick={() => void analyse()}
                    className="junta-btn junta-btn-md gap-2 justify-self-start junta-btn-outline"
                >
                    <ChartNoAxesCombined
                        className="size-4"
                        aria-hidden="true"
                    />
                    {api.processing
                        ? 'A estimar…'
                        : saved
                          ? 'Actualizar previsão'
                          : 'Obter previsão com IA'}
                </button>
                {api.processing && (
                    <p
                        role="status"
                        className="animate-pulse text-muted-foreground"
                    >
                        A analisar a evolução das adesões…
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
                    <div className="grid gap-4" aria-live="polite">
                        <p className="text-xs text-muted-foreground">
                            {result.model ? `OpenAI · ${result.model} · ` : ''}
                            {maputoDate(result.createdAt)} · fotografia dos
                            dados no momento do pedido.
                        </p>
                        {stale ? (
                            <p
                                role="status"
                                className="font-medium text-destructive"
                            >
                                Os dados mudaram ou a previsão expirou.
                                Actualize antes de a utilizar.
                            </p>
                        ) : (
                            <>
                                {result.state === 'estimated' && (
                                    <>
                                        <dl className="grid gap-3 sm:grid-cols-2">
                                            <div className="rounded-xl border border-border bg-background p-4">
                                                <dt className="text-muted-foreground">
                                                    Probabilidade estimada de
                                                    atingir a meta
                                                </dt>
                                                <dd className="mt-2 text-3xl font-semibold text-primary">
                                                    {result.probabilityPercent}%
                                                </dd>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Estimativa inicial, ainda
                                                    sem validação histórica.
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-border bg-background p-4">
                                                <dt className="text-muted-foreground">
                                                    Quantidade provável até ao
                                                    prazo
                                                </dt>
                                                <dd className="mt-2 text-3xl font-semibold">
                                                    {number(
                                                        result.expectedQuantity!,
                                                    )}{' '}
                                                    <span className="text-base font-normal">
                                                        {result.features.unit}
                                                    </span>
                                                </dd>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Cenários:{' '}
                                                    {number(
                                                        result.lowerQuantity!,
                                                    )}
                                                    –
                                                    {number(
                                                        result.upperQuantity!,
                                                    )}{' '}
                                                    {result.features.unit}.
                                                </p>
                                            </div>
                                        </dl>
                                        <ForecastChart result={result} />
                                    </>
                                )}
                                {result.state === 'insufficient_data' && (
                                    <p className="font-semibold">
                                        Ainda não há dados suficientes para
                                        prever.
                                    </p>
                                )}
                                {result.state === 'target_reached' && (
                                    <p className="font-semibold text-primary">
                                        A procura registada já atingiu a meta.
                                    </p>
                                )}
                                {result.state === 'closed' && (
                                    <p className="font-semibold">
                                        A fase de interesse está fechada.
                                    </p>
                                )}
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    {[
                                        [
                                            'Procura / meta',
                                            `${number(result.features.currentQuantity)} / ${number(result.features.targetQuantity)} ${result.features.unit}`,
                                        ],
                                        [
                                            'Participantes activos',
                                            number(
                                                result.features
                                                    .participantCount,
                                            ),
                                        ],
                                        [
                                            'Ritmo médio',
                                            `${number(result.features.averageUnitsPerDay)} ${result.features.unit}/dia`,
                                        ],
                                        [
                                            'Últimas 24 h / 24 h anteriores',
                                            `${number(result.features.recentUnitsPerDay)} / ${result.features.previousUnitsPerDay == null ? 'Sem observação' : number(result.features.previousUnitsPerDay)} ${result.features.unit}/dia`,
                                        ],
                                        [
                                            'Tempo restante',
                                            `${number(result.features.remainingHours)} horas`,
                                        ],
                                        [
                                            'Categoria · localização',
                                            `${campaignCategories[result.features.category] ?? result.features.category} · ${result.features.location}`,
                                        ],
                                    ].map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="font-medium">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <ul className="list-disc space-y-2 pl-5">
                                    {result.findings.map((finding) => (
                                        <li key={finding}>{finding}</li>
                                    ))}
                                </ul>
                                <details className="rounded-lg border border-border p-3">
                                    <summary className="cursor-pointer font-medium">
                                        Como interpretar a previsão
                                    </summary>
                                    <div className="mt-3 grid gap-2 leading-6 text-muted-foreground">
                                        <p>{result.method}</p>
                                        <p>
                                            Amostra de contexto:{' '}
                                            {
                                                result.features
                                                    .historicalContext
                                                    .campaignCount
                                            }{' '}
                                            campanhas anteriores da mesma
                                            categoria e unidade, das quais{' '}
                                            {
                                                result.features
                                                    .historicalContext
                                                    .sameLocationCount
                                            }{' '}
                                            na mesma localização. Estes registos
                                            actuais não são resultados
                                            comprovados no prazo.
                                        </p>
                                        <p>
                                            O início usa o registo de
                                            publicação, quando disponível; nos
                                            restantes casos usa a criação como
                                            aproximação. As adesões representam
                                            interesse e podem mudar; não
                                            representam pagamentos.
                                        </p>
                                    </div>
                                </details>
                            </>
                        )}
                    </div>
                )}
            </div>
        </OperationalPanel>
    );
}
