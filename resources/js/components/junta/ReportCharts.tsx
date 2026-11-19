import { useEffect, useId, useRef, useState } from 'react';
import { BarChart3, ChartNoAxesCombined } from 'lucide-react';
import { EmptyState } from '@/components/junta/EmptyState';
import { SegmentedControl } from '@/components/junta/ConsumerUi';
import { formatMZN } from '@/lib/formatters';
import type { ReportMonth, ReportRegion } from '@/lib/admin-reporting';
const phaseColors: Record<string, string> = {
    draft: 'var(--state-neutral-solid)',
    interest: 'var(--primary)',
    evaluation: 'var(--state-warning-solid)',
    selection: 'var(--state-info-solid)',
    confirmed: 'var(--brand-800)',
    payment: 'var(--color-junta-info)',
    review: 'var(--color-junta-warning)',
    supply: 'var(--color-junta-success)',
    completed: 'var(--brand-600)',
    cancelled: 'var(--state-danger-solid)',
};
export function ReportDonut({
    items,
}: {
    items: { key: string; label: string; value: number }[];
}) {
    const [selected, setSelected] = useState<string | null>(null);
    const total = items.reduce((sum, item) => sum + item.value, 0);
    const active = items.find((item) => item.key === selected);
    let offset = 0;
    const segments = items.map((item) => {
        const length = (item.value / Math.max(total, 1)) * 100;
        const segment = { ...item, length, offset };
        offset += length;
        return segment;
    });
    if (!total)
        return (
            <EmptyState
                icon={BarChart3}
                title="Sem campanhas neste período"
                description="Escolha um período mais alargado para explorar a actividade."
            />
        );
    return (
        <div className="report-donut-layout">
            <div className="report-donut">
                <svg
                    viewBox="0 0 220 220"
                    role="img"
                    aria-label={
                        'Distribuição de ' + total + ' campanhas por fase'
                    }
                >
                    <circle
                        cx="110"
                        cy="110"
                        r="84"
                        fill="none"
                        stroke="var(--muted)"
                        strokeWidth="23"
                    />
                    {segments.map((item) => (
                        <circle
                            key={item.key}
                            cx="110"
                            cy="110"
                            r="84"
                            fill="none"
                            stroke={phaseColors[item.key] ?? 'var(--primary)'}
                            strokeWidth={active?.key === item.key ? 29 : 23}
                            pathLength={100}
                            strokeDasharray={`${Math.max(0, item.length - (segments.length > 1 ? Math.min(0.8, item.length * 0.12) : 0))} 100`}
                            strokeDashoffset={-item.offset}
                            transform="rotate(-90 110 110)"
                            opacity={
                                active && active.key !== item.key ? 0.28 : 1
                            }
                            onClick={() =>
                                setSelected(
                                    selected === item.key ? null : item.key,
                                )
                            }
                            className="report-donut-segment"
                        >
                            <title>
                                {item.label}: {item.value} (
                                {Math.round(item.length)}%)
                            </title>
                        </circle>
                    ))}
                </svg>
                <div className="report-donut-center">
                    <strong>{active?.value ?? total}</strong>
                    <span>{active?.label ?? 'campanhas'}</span>
                </div>
            </div>
            <ul className="report-chart-legend" aria-label="Explorar fases">
                {items.map((item) => (
                    <li key={item.key}>
                        <button
                            type="button"
                            aria-pressed={selected === item.key}
                            onClick={() =>
                                setSelected(
                                    selected === item.key ? null : item.key,
                                )
                            }
                        >
                            <span
                                className="report-legend-dot"
                                style={{
                                    background:
                                        phaseColors[item.key] ??
                                        'var(--primary)',
                                }}
                            />
                            <span>{item.label}</span>
                            <strong>{item.value}</strong>
                            <small>
                                {Math.round((item.value / total) * 100)}%
                            </small>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
function useChartWidth() {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(640);
    useEffect(() => {
        if (!ref.current) return;
        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];
            if (entry)
                setWidth(Math.max(260, Math.round(entry.contentRect.width)));
        });
        observer.observe(ref.current);
        return () => observer.disconnect();
    }, []);
    return { ref, width };
}
export function ReportTrend({ months }: { months: ReportMonth[] }) {
    const [metric, setMetric] = useState('campaigns');
    const [active, setActive] = useState(months.length - 1);
    const { ref, width } = useChartWidth();
    const id = useId().replaceAll(':', '');
    const values = months.map((month) =>
        metric === 'campaigns' ? month.campaigns : month.participants,
    );
    const max = Math.max(4, Math.ceil(Math.max(0, ...values) / 4) * 4);
    const left = 38,
        right = 14,
        top = 18,
        bottom = 34,
        height = 242;
    const plotWidth = width - left - right,
        plotHeight = height - top - bottom;
    const points = values.map((value, index) => ({
        x:
            left +
            (months.length > 1 ? index / (months.length - 1) : 0.5) * plotWidth,
        y: top + (1 - value / max) * plotHeight,
    }));
    const line = points
        .map((point, index) => `${index ? 'L' : 'M'}${point.x},${point.y}`)
        .join(' ');
    const area =
        line +
        ` L${points.at(-1)!.x},${height - bottom} L${points[0].x},${height - bottom} Z`;
    const selected = Math.min(active, months.length - 1);
    const every = width < 440 ? Math.ceil(months.length / 4) : 1;
    return (
        <div className="report-trend">
            <div className="report-chart-toolbar">
                <SegmentedControl
                    label="Medida da evolução"
                    value={metric}
                    onChange={setMetric}
                    options={[
                        { value: 'campaigns', label: 'Campanhas' },
                        { value: 'participants', label: 'Participações' },
                    ]}
                />
                <span className="report-series-key">
                    <span />{' '}
                    {metric === 'campaigns'
                        ? 'Campanhas criadas'
                        : 'Participações actuais'}
                </span>
            </div>
            <div ref={ref} className="report-trend-plot">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    role="group"
                    aria-label={
                        'Evolução mensal de ' +
                        (metric === 'campaigns'
                            ? 'campanhas criadas'
                            : 'participações nas campanhas criadas em cada mês')
                    }
                >
                    <defs>
                        <linearGradient id={id} x1="0" x2="0" y1="0" y2="1">
                            <stop
                                offset="0%"
                                stopColor="var(--primary)"
                                stopOpacity=".22"
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--primary)"
                                stopOpacity=".02"
                            />
                        </linearGradient>
                    </defs>
                    {Array.from({ length: 5 }, (_, index) => {
                        const y = top + (index / 4) * plotHeight;
                        return (
                            <g key={index}>
                                <line
                                    x1={left}
                                    y1={y}
                                    x2={width - right}
                                    y2={y}
                                    className="report-grid-line"
                                />
                                <text
                                    x={left - 10}
                                    y={y + 4}
                                    textAnchor="end"
                                    className="report-axis-label"
                                >
                                    {(max - (index / 4) * max).toLocaleString(
                                        'pt-MZ',
                                    )}
                                </text>
                            </g>
                        );
                    })}
                    <path d={area} fill={`url(#${id})`} />
                    <path
                        d={line}
                        fill="none"
                        stroke="var(--primary)"
                        strokeWidth="3"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                    {points.map((point, index) => (
                        <g key={months[index].key}>
                            {(index % every === 0 ||
                                index === months.length - 1) && (
                                <text
                                    x={point.x}
                                    y={height - 10}
                                    textAnchor="middle"
                                    className="report-axis-label"
                                >
                                    {months[index].label}
                                </text>
                            )}
                            <circle
                                cx={point.x}
                                cy={point.y}
                                r={index === selected ? 6 : 4}
                                fill="var(--card)"
                                stroke="var(--primary)"
                                strokeWidth="2"
                            />
                            <circle
                                cx={point.x}
                                cy={point.y}
                                r="13"
                                fill="transparent"
                                tabIndex={0}
                                role="button"
                                aria-label={
                                    months[index].longLabel +
                                    ': ' +
                                    values[index] +
                                    ' ' +
                                    (metric === 'campaigns'
                                        ? 'campanhas'
                                        : 'participações')
                                }
                                aria-pressed={selected === index}
                                onFocus={() => setActive(index)}
                                onMouseEnter={() => setActive(index)}
                                onClick={() => setActive(index)}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        event.preventDefault();
                                        setActive(index);
                                    }
                                }}
                            >
                                <title>
                                    {months[index].longLabel}: {values[index]}
                                </title>
                            </circle>
                        </g>
                    ))}
                </svg>
            </div>
            <p className="report-chart-reading" aria-live="polite">
                <strong>{months[selected].longLabel}</strong>
                <span>
                    {values[selected].toLocaleString('pt-MZ')}{' '}
                    {metric === 'campaigns'
                        ? 'campanhas criadas'
                        : 'participações actuais nessas campanhas'}
                </span>
            </p>
            <p className="report-chart-note">
                {metric === 'campaigns'
                    ? 'Campanhas agrupadas pelo mês de criação, em hora de Maputo.'
                    : 'Participações actuais nas campanhas criadas em cada mês; não são novas adesões nesse mês nem pessoas únicas.'}
            </p>
        </div>
    );
}
export function ReportRegionChart({ regions }: { regions: ReportRegion[] }) {
    const [metric, setMetric] = useState('estimatedVolume');
    const [expanded, setExpanded] = useState(false);
    const ranked = [...regions].sort(
        (a, b) =>
            (metric === 'campaigns'
                ? b.campaigns - a.campaigns
                : b.estimatedVolume - a.estimatedVolume) ||
            a.label.localeCompare(b.label),
    );
    const visible = expanded ? ranked : ranked.slice(0, 6);
    const value = (region: ReportRegion) =>
        metric === 'campaigns' ? region.campaigns : region.estimatedVolume;
    const max = Math.max(1, ...regions.map(value));
    if (!regions.length)
        return (
            <EmptyState
                icon={ChartNoAxesCombined}
                title="Sem actividade por região"
                description="Os resultados aparecem quando existem campanhas no período."
            />
        );
    return (
        <>
            <div className="report-chart-toolbar">
                <p className="admin-muted">
                    {regions.length} localizações representadas
                </p>
                <select
                    className="junta-input"
                    aria-label="Medida por região"
                    value={metric}
                    onChange={(event) => setMetric(event.target.value)}
                >
                    <option value="estimatedVolume">
                        Volume estimado (MT)
                    </option>
                    <option value="campaigns">Número de campanhas</option>
                </select>
            </div>
            <ol className="report-region-ranking">
                {visible.map((region, index) => (
                    <li key={region.label}>
                        <span className="report-rank">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <div>
                            <div className="report-region-heading">
                                <span title={region.label}>{region.label}</span>
                                <strong>
                                    {metric === 'campaigns'
                                        ? region.campaigns.toLocaleString(
                                              'pt-MZ',
                                          )
                                        : formatMZN(region.estimatedVolume)}
                                </strong>
                            </div>
                            <svg
                                viewBox="0 0 100 10"
                                preserveAspectRatio="none"
                                role="img"
                                aria-label={
                                    region.label +
                                    ': ' +
                                    (metric === 'campaigns'
                                        ? region.campaigns + ' campanhas'
                                        : formatMZN(region.estimatedVolume) +
                                          ' de procura estimada')
                                }
                            >
                                <rect
                                    width="100"
                                    height="10"
                                    rx="3"
                                    fill="var(--muted)"
                                />
                                <rect
                                    width={(value(region) / max) * 100}
                                    height="10"
                                    rx="3"
                                    fill="var(--primary)"
                                />
                            </svg>
                            <small>
                                {region.campaigns} campanhas ·{' '}
                                {region.participants} participações
                            </small>
                        </div>
                    </li>
                ))}
            </ol>
            {regions.length > 6 && (
                <button
                    type="button"
                    className="junta-btn junta-btn-sm junta-btn-outline"
                    aria-expanded={expanded}
                    onClick={() => setExpanded(!expanded)}
                >
                    {expanded ? 'Mostrar menos' : 'Ver todas as regiões'}
                </button>
            )}
        </>
    );
}
