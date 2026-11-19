import { Link, router, useRemember } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { PageShell } from '@/components/junta/OrganizerUi';
import { CompactCampaignTable } from '@/components/junta/OrganizerCampaignTable';
import { EmptyState } from '@/components/junta/EmptyState';
import { criar, index } from '@/routes/organizador/campanhas';
import type {
    OrganizerCampaignSection,
    OrganizerFilters,
} from '@/types/organizer';

function CampaignSection({ section }: { section: OrganizerCampaignSection }) {
    const [open, setOpen] = useRemember(
        false,
        `organizer-campaign-section-${section.key}`,
    );
    const page = section.campaigns;
    const heading = (
        <h2>
            {section.label} <span>({page.total})</span>
        </h2>
    );
    const contents = (
        <>
            <CompactCampaignTable records={page.data} />
            {page.last_page > 1 && (
                <nav
                    className="organizer-list-pagination"
                    aria-label={`Paginação: ${section.label}`}
                >
                    <span>
                        Página {page.current_page} de {page.last_page}
                    </span>
                    {page.prev_page_url && (
                        <Link
                            href={page.prev_page_url}
                            preserveState
                            preserveScroll
                        >
                            Anterior
                        </Link>
                    )}
                    {page.next_page_url && (
                        <Link
                            href={page.next_page_url}
                            preserveState
                            preserveScroll
                        >
                            Seguinte
                        </Link>
                    )}
                </nav>
            )}
        </>
    );
    return section.collapsed ? (
        <details
            className="organizer-list-section"
            data-section={section.key}
            open={open}
            onToggle={(event) => setOpen(event.currentTarget.open)}
        >
            <summary>{heading}</summary>
            {contents}
        </details>
    ) : (
        <section className="organizer-list-section" data-section={section.key}>
            {heading}
            {contents}
        </section>
    );
}

export default function Campaigns({
    organizerCampaignSections: sections,
    organizerFilters,
}: {
    organizerCampaignSections: OrganizerCampaignSection[];
    organizerFilters: OrganizerFilters;
}) {
    const [filters, setFilters] = useRemember(
        { search: organizerFilters.search, phase: organizerFilters.phase },
        'organizer-campaign-list-filters',
    );
    const apply = (next = filters) =>
        router.get(index.url(), next, {
            preserveState: true,
            preserveScroll: true,
        });
    const clear = () => {
        const defaults = { search: '', phase: '' };
        setFilters(defaults);
        apply(defaults);
    };
    return (
        <PageShell
            className="organizer-campaign-index"
            title="Campanhas"
            description="Da primeira intenção de compra à entrega de cada pedido."
            actions={
                <Link
                    href={criar()}
                    className="junta-btn junta-btn-md junta-btn-primary"
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Criar campanha
                </Link>
            }
        >
            <form
                className="organizer-list-filters"
                onSubmit={(event) => {
                    event.preventDefault();
                    apply();
                }}
            >
                <label>
                    Pesquisar
                    <div className="organizer-list-search">
                        <input
                            className="junta-input"
                            type="search"
                            maxLength={120}
                            placeholder="Produto, referência ou local"
                            value={filters.search}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    search: event.target.value,
                                })
                            }
                        />
                        <button type="submit" aria-label="Pesquisar campanhas">
                            <Search className="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </label>
                <label>
                    Fase
                    <select
                        className="junta-input"
                        value={filters.phase}
                        onChange={(event) => {
                            const next = {
                                ...filters,
                                phase: event.target.value,
                            };
                            setFilters(next);
                            apply(next);
                        }}
                    >
                        <option value="">Todas as fases</option>
                        {[
                            ['draft', 'Rascunho'],
                            ['interest', 'Interesse'],
                            ['proposals', 'Propostas'],
                            ['payment', 'Pagamentos'],
                            ['supply', 'Fornecimento'],
                            ['completed', 'Concluída'],
                            ['cancelled', 'Cancelada'],
                        ].map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                </label>
                {(filters.search || filters.phase) && (
                    <button
                        type="button"
                        className="junta-btn-ghost junta-btn junta-btn-sm"
                        onClick={clear}
                    >
                        Limpar
                    </button>
                )}
            </form>
            {sections.length ? (
                <div className="organizer-campaign-sections">
                    {sections.map((section) => (
                        <CampaignSection key={section.key} section={section} />
                    ))}
                </div>
            ) : (
                <EmptyState
                    icon={Search}
                    title={
                        filters.search || filters.phase
                            ? 'Nenhuma campanha com estes filtros'
                            : 'Ainda não criou uma campanha'
                    }
                    description={
                        filters.search || filters.phase
                            ? 'Experimente outro produto, fase ou local.'
                            : 'Comece por definir o produto e as condições para reunir interesse.'
                    }
                    action={
                        filters.search || filters.phase
                            ? { label: 'Limpar filtros', onClick: clear }
                            : undefined
                    }
                    actionHref={
                        !filters.search && !filters.phase
                            ? criar.url()
                            : undefined
                    }
                    actionLabel="Criar a primeira campanha"
                />
            )}
        </PageShell>
    );
}
