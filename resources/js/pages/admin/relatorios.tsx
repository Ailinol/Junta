import { Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    ArrowDownToLine,
    ChartNoAxesCombined,
    ChartPie,
    Images,
    Layers,
    MapPin,
    PackageSearch,
    Users,
    Wallet,
} from 'lucide-react';
import { Page } from '@/components/junta/Page';
import { SegmentedControl } from '@/components/junta/ConsumerUi';
import { ReportBlock } from '@/components/junta/AdminFinance';
import {
    ReportDonut,
    ReportTrend,
    ReportRegionChart,
} from '@/components/junta/ReportCharts';
import { DataTable, ProgressMeter } from '@/components/junta/OrganizerUi';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { EmptyState } from '@/components/junta/EmptyState';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignCategories } from '@/lib/campaign-presentation';
import {
    buildAdminReport,
    campaignEstimate,
    reportPhases,
    topParticipatedCampaigns,
} from '@/lib/admin-reporting';
import { csvValue } from '@/lib/organizer-presentation';
import { formatMZN } from '@/lib/formatters';
import { index as finance } from '@/routes/admin/compras';
import { index as campaignList } from '@/routes/admin/campanhas';
import type { CampaignCategory } from '@/types/junta';
const categoryImages: Record<CampaignCategory, string> = {
    alimentacao: '/images/campaigns/food-illustration.jpg',
    construcao: '/images/campaigns/construction-illustration.jpg',
    eletronicos: '/images/campaigns/solar-illustration.jpg',
    escritorio: '/images/campaigns/office-illustration.jpg',
    saude: '/images/campaigns/health-illustration.jpg',
    agricola: '/images/campaigns/agriculture-illustration.jpg',
    servicos: '/images/campaigns/office-illustration.jpg',
    outro: '/images/campaigns/construction-illustration.jpg',
};
function downloadCsv(
    name: string,
    headings: string[],
    rows: (string | number)[][],
) {
    const content =
        '\uFEFF' +
        [headings, ...rows]
            .map((row) => row.map(csvValue).join(';'))
            .join('\r\n');
    const url = URL.createObjectURL(
        new Blob([content], { type: 'text/csv;charset=utf-8;' }),
    );
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
export default function Reports() {
    const { data } = usePrototypeStore();
    const [period, setPeriod] = useState('6');
    const [allCategories, setAllCategories] = useState(false);
    const report = buildAdminReport(
        data.campaigns,
        Number(period) as 3 | 6 | 12,
    );
    const phases = reportPhases(report.campaigns);
    const featured = topParticipatedCampaigns(report.campaigns);
    const visibleCategories = allCategories
        ? report.categories
        : report.categories.slice(0, 4);
    const heroCategories: CampaignCategory[] = report.categories.length
        ? report.categories.slice(0, 3).map((category) => category.key)
        : ['alimentacao', 'construcao', 'escritorio'];
    const exportMonthly = () =>
        downloadCsv(
            'junta-evolucao-' + period + '-meses.csv',
            [
                'Mês',
                'Campanhas criadas',
                'Participações actuais nessas campanhas',
                'Procura estimada MT',
            ],
            report.months.map((month) => [
                month.longLabel,
                month.campaigns,
                month.participants,
                month.estimatedVolume.toFixed(2),
            ]),
        );
    return (
        <Page
            className="admin-reports"
            title="Relatórios"
            description="Explore a actividade e a procura nas compras em grupo."
            actions={
                <SegmentedControl
                    label="Período do relatório"
                    value={period}
                    onChange={setPeriod}
                    options={['3', '6', '12'].map((value) => ({
                        value,
                        label: value + ' meses',
                    }))}
                />
            }
        >
            <section className="admin-report-hero">
                <div>
                    <span className="report-eyebrow">
                        <Activity className="size-4" /> Análise da plataforma
                    </span>
                    <h2>Uma visão das compras em grupo</h2>
                    <p>
                        Compare os meses e descubra as categorias e regiões com
                        maior actividade.
                    </p>
                    <span className="report-period">
                        <span /> {report.periodLabel}
                    </span>
                </div>
                <div
                    className="report-hero-images"
                    aria-label="Imagens ilustrativas das categorias"
                >
                    {heroCategories.map((key) => (
                        <div key={key}>
                            <img
                                src={categoryImages[key]}
                                alt=""
                                decoding="async"
                            />
                            <span>{campaignCategories[key]}</span>
                        </div>
                    ))}
                </div>
            </section>
            <p className="admin-estimate-notice">
                <Wallet className="size-5 shrink-0" />
                <span>
                    <strong>Estimativas de actividade.</strong> Os volumes
                    representam preço estimado × interesse; não são receita nem
                    pagamentos confirmados.{' '}
                    <Link href={finance()}>Consultar financeiro</Link>
                </span>
            </p>
            <dl className="report-metrics">
                {[
                    {
                        label: 'Campanhas no período',
                        value: report.campaigns.length.toLocaleString('pt-MZ'),
                        detail: 'Por mês de criação',
                        icon: Layers,
                    },
                    {
                        label: 'Participações',
                        value: report.participants.toLocaleString('pt-MZ'),
                        detail: 'Adesões às campanhas, não pessoas únicas',
                        icon: Users,
                    },
                    {
                        label: 'Categorias representadas',
                        value: report.categories.length.toLocaleString('pt-MZ'),
                        detail: report.regions.length + ' localizações',
                        icon: PackageSearch,
                    },
                    {
                        label: 'Procura estimada',
                        value: formatMZN(report.estimatedVolume),
                        detail:
                            report.pricedCampaigns +
                            ' de ' +
                            report.campaigns.length +
                            ' campanhas com preço estimado',
                        icon: Wallet,
                    },
                ].map((item) => (
                    <div key={item.label}>
                        <div>
                            <dt>{item.label}</dt>
                            <span className="report-metric-icon">
                                <item.icon className="size-5" />
                            </span>
                        </div>
                        <dd title={item.value}>{item.value}</dd>
                        <p>{item.detail}</p>
                    </div>
                ))}
            </dl>
            {!report.campaigns.length ? (
                <EmptyState
                    icon={ChartNoAxesCombined}
                    title="Ainda não há actividade neste período"
                    description="Experimente 6 ou 12 meses. Os gráficos aparecem quando existem campanhas no intervalo seleccionado."
                />
            ) : (
                <>
                    <div className="report-overview-grid">
                        <ReportBlock
                            title="Evolução mensal"
                            icon={<ChartNoAxesCombined className="size-5" />}
                            description="Explore a actividade das campanhas ao longo do período."
                            actions={
                                <button
                                    className="report-icon-action"
                                    aria-label="Exportar evolução mensal em CSV"
                                    title="Exportar CSV"
                                    onClick={exportMonthly}
                                >
                                    <ArrowDownToLine className="size-4" />
                                </button>
                            }
                        >
                            <ReportTrend key={period} months={report.months} />
                            <details className="report-data-details">
                                <summary>Ver dados do gráfico</summary>
                                <DataTable
                                    records={report.months}
                                    rowKey={(month) => month.key}
                                    label="Dados da evolução mensal"
                                    columns={[
                                        {
                                            key: 'month',
                                            label: 'Mês',
                                            render: (month) => month.longLabel,
                                        },
                                        {
                                            key: 'campaigns',
                                            label: 'Campanhas',
                                            render: (month) => month.campaigns,
                                        },
                                        {
                                            key: 'participations',
                                            label: 'Participações actuais',
                                            render: (month) =>
                                                month.participants,
                                        },
                                    ]}
                                />
                            </details>
                        </ReportBlock>
                        <ReportBlock
                            title="Campanhas por fase"
                            icon={<ChartPie className="size-5" />}
                            description="Fases actuais. Seleccione uma legenda para destacar."
                        >
                            <ReportDonut key={period} items={phases} />
                        </ReportBlock>
                    </div>
                    <ReportBlock
                        title="Procura por categoria"
                        icon={<Images className="size-5" />}
                        description="Imagens e estimativas das categorias representadas no período."
                        actions={
                            <button
                                className="junta-btn junta-btn-sm junta-btn-outline"
                                onClick={() =>
                                    downloadCsv(
                                        'junta-categorias-' +
                                            period +
                                            '-meses.csv',
                                        [
                                            'Categoria',
                                            'Campanhas',
                                            'Participações',
                                            'Procura estimada MT',
                                        ],
                                        report.categories.map((category) => [
                                            campaignCategories[category.key],
                                            category.campaigns,
                                            category.participants,
                                            category.estimatedVolume.toFixed(2),
                                        ]),
                                    )
                                }
                            >
                                <ArrowDownToLine className="size-4" /> Exportar
                                CSV
                            </button>
                        }
                    >
                        <div className="report-category-grid">
                            {visibleCategories.map((category) => (
                                <article
                                    key={category.key}
                                    className="report-category-card"
                                >
                                    <div className="report-category-image">
                                        <CampaignVisual
                                            campaign={{
                                                ...category.sample,
                                                title: category.sample.imageUrl
                                                    ? category.sample.title
                                                    : campaignCategories[
                                                          category.key
                                                      ] +
                                                      ' · imagem ilustrativa',
                                                imageUrl:
                                                    category.sample.imageUrl ||
                                                    categoryImages[
                                                        category.key
                                                    ],
                                            }}
                                            categoryFallback
                                        />
                                        <span>
                                            {category.sample.imageUrl
                                                ? 'Imagem da campanha'
                                                : 'Imagem da categoria'}
                                        </span>
                                    </div>
                                    <div className="report-category-body">
                                        <h3>
                                            {campaignCategories[category.key]}
                                        </h3>
                                        <p>
                                            {category.campaigns} campanhas ·{' '}
                                            {category.participants}{' '}
                                            participações
                                        </p>
                                        <strong>
                                            {formatMZN(
                                                category.estimatedVolume,
                                            )}
                                        </strong>
                                        <small>Procura estimada</small>
                                        <svg
                                            viewBox="0 0 100 5"
                                            preserveAspectRatio="none"
                                            role="img"
                                            aria-label={
                                                campaignCategories[
                                                    category.key
                                                ] +
                                                ': ' +
                                                (report.estimatedVolume
                                                    ? Math.round(
                                                          (category.estimatedVolume /
                                                              report.estimatedVolume) *
                                                              100,
                                                      )
                                                    : 0) +
                                                '% do volume estimado'
                                            }
                                        >
                                            <rect
                                                width="100"
                                                height="5"
                                                rx="2"
                                                fill="var(--muted)"
                                            />
                                            <rect
                                                width={
                                                    report.estimatedVolume
                                                        ? (category.estimatedVolume /
                                                              report.estimatedVolume) *
                                                          100
                                                        : 0
                                                }
                                                height="5"
                                                rx="2"
                                                fill="var(--primary)"
                                            />
                                        </svg>
                                    </div>
                                </article>
                            ))}
                        </div>
                        {report.categories.length > 4 && (
                            <button
                                className="report-show-more junta-btn junta-btn-sm junta-btn-outline"
                                aria-expanded={allCategories}
                                onClick={() => setAllCategories(!allCategories)}
                            >
                                {allCategories
                                    ? 'Mostrar menos'
                                    : 'Ver todas as categorias (' +
                                      report.categories.length +
                                      ')'}
                            </button>
                        )}
                    </ReportBlock>
                    <ReportBlock
                        title="Actividade por região"
                        icon={<MapPin className="size-5" />}
                        description="Localizações declaradas nas campanhas, agrupadas pela região indicada."
                        actions={
                            <button
                                className="junta-btn junta-btn-sm junta-btn-outline"
                                onClick={() =>
                                    downloadCsv(
                                        'junta-regioes-' +
                                            period +
                                            '-meses.csv',
                                        [
                                            'Localização',
                                            'Campanhas',
                                            'Participações',
                                            'Procura estimada MT',
                                        ],
                                        report.regions.map((region) => [
                                            region.label,
                                            region.campaigns,
                                            region.participants,
                                            region.estimatedVolume.toFixed(2),
                                        ]),
                                    )
                                }
                            >
                                <ArrowDownToLine className="size-4" /> Exportar
                                CSV
                            </button>
                        }
                    >
                        <ReportRegionChart
                            key={period}
                            regions={report.regions}
                        />
                    </ReportBlock>
                    <ReportBlock
                        title="Campanhas com maior participação"
                        icon={<Users className="size-5" />}
                        description="As cinco campanhas com mais adesões. A procura é comparada com a meta de cada campanha."
                        actions={
                            <Link
                                className="junta-btn junta-btn-sm junta-btn-outline"
                                href={campaignList()}
                            >
                                Ver campanhas
                            </Link>
                        }
                    >
                        <DataTable
                            records={featured}
                            rowKey={(campaign) => campaign.id}
                            label="Campanhas com maior participação"
                            columns={[
                                {
                                    key: 'campaign',
                                    label: 'Campanha',
                                    render: (campaign) => (
                                        <div className="report-campaign-cell">
                                            <CampaignVisual
                                                campaign={campaign}
                                                thumbnail
                                                hideFallback
                                            />
                                            <span title={campaign.title}>
                                                {campaign.title}
                                            </span>
                                        </div>
                                    ),
                                },
                                {
                                    key: 'participants',
                                    label: 'Participações',
                                    render: (campaign) =>
                                        campaign.participantCount,
                                },
                                {
                                    key: 'demand',
                                    label: 'Procura / meta',
                                    render: (campaign) => (
                                        <ProgressMeter
                                            compact
                                            value={campaign.currentQuantity}
                                            target={campaign.targetQuantity}
                                            label={
                                                'Procura / meta · ' +
                                                campaign.unit
                                            }
                                        />
                                    ),
                                },
                                {
                                    key: 'estimate',
                                    label: 'Procura estimada',
                                    render: (campaign) =>
                                        campaign.estimatedPrice
                                            ? formatMZN(
                                                  campaignEstimate(campaign),
                                              )
                                            : 'Sem preço estimado',
                                },
                            ]}
                        />
                    </ReportBlock>
                </>
            )}
        </Page>
    );
}
