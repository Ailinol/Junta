import { Head, useRemember } from '@inertiajs/react';
import { useState } from 'react';
import { Search } from 'lucide-react';
import { CampaignCard } from '@/components/junta/CampaignCard';
import { EmptyState } from '@/components/junta/EmptyState';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { campaignCategories } from '@/lib/campaign-presentation';
export default function Catalogue() {
    const { data, isLoaded } = usePrototypeStore();
    const [filters, setFilters] = useRemember(
        { search: '', category: '', sort: 'deadline' },
        'consumer-catalogue-filters',
    );
    const [limit, setLimit] = useState(12);
    const records = data.campaigns
        .filter(
            (c) =>
                c.status !== 'rascunho' &&
                (!filters.category || c.category === filters.category) &&
                `${c.title} ${c.location} ${c.reference ?? ''}`
                    .toLocaleLowerCase('pt')
                    .includes(filters.search.trim().toLocaleLowerCase('pt')),
        )
        .sort((a, b) => {
            const open = (campaign: typeof a) =>
                campaign.status === 'ativa' &&
                campaign.purchasePhase === 'interest' &&
                new Date(campaign.deadline).getTime() > Date.now();
            return (
                Number(open(b)) - Number(open(a)) ||
                (filters.sort === 'recent'
                    ? b.id - a.id
                    : filters.sort === 'progress'
                      ? b.currentQuantity / Math.max(1, b.targetQuantity) -
                        a.currentQuantity / Math.max(1, a.targetQuantity)
                      : new Date(a.deadline).getTime() -
                        new Date(b.deadline).getTime())
            );
        });
    const clear = () => {
        setFilters({ search: '', category: '', sort: 'deadline' });
        setLimit(12);
    };
    return (
        <main className="consumer-page consumer-public">
            <Head title="Explorar campanhas" />
            <h1>Explorar campanhas</h1>
            <form
                className="consumer-catalogue-search"
                onSubmit={(event) => event.preventDefault()}
            >
                <Search className="size-5" aria-hidden="true" />
                <input
                    type="search"
                    className="junta-input"
                    placeholder="O que procura?"
                    aria-label="Pesquisar produto ou localização"
                    value={filters.search}
                    onChange={(event) => {
                        setFilters({ ...filters, search: event.target.value });
                        setLimit(12);
                    }}
                />
            </form>
            <div className="consumer-catalogue-controls">
                <div
                    className="consumer-category-chips"
                    role="group"
                    aria-label="Categorias"
                >
                    <button
                        type="button"
                        aria-pressed={!filters.category}
                        onClick={() => {
                            setFilters({ ...filters, category: '' });
                            setLimit(12);
                        }}
                    >
                        Todas
                    </button>
                    {Object.entries(campaignCategories).map(
                        ([value, label]) => (
                            <button
                                type="button"
                                key={value}
                                aria-pressed={filters.category === value}
                                onClick={() => {
                                    setFilters({ ...filters, category: value });
                                    setLimit(12);
                                }}
                            >
                                {label}
                            </button>
                        ),
                    )}
                </div>
                <select
                    aria-label="Ordenação"
                    className="junta-input"
                    value={filters.sort}
                    onChange={(event) =>
                        setFilters({ ...filters, sort: event.target.value })
                    }
                >
                    <option value="deadline">Prazo mais próximo</option>
                    <option value="progress">Maior procura</option>
                    <option value="recent">Mais recentes</option>
                </select>
            </div>
            {!isLoaded ? (
                <div
                    className="consumer-catalogue-grid"
                    role="status"
                    aria-label="A carregar campanhas"
                >
                    {[1, 2, 3].map((n) => (
                        <div
                            key={n}
                            className="h-96 animate-pulse rounded-xl bg-muted"
                        />
                    ))}
                </div>
            ) : records.length ? (
                <>
                    <div className="consumer-catalogue-grid">
                        {records.slice(0, limit).map((c) => (
                            <CampaignCard key={c.id} campaign={c} />
                        ))}
                    </div>
                    {records.length > limit && (
                        <button
                            type="button"
                            className="junta-btn mx-auto mt-6 flex junta-btn-md junta-btn-outline"
                            onClick={() => setLimit(limit + 12)}
                        >
                            Ver mais
                        </button>
                    )}
                </>
            ) : (
                <EmptyState
                    title="Não encontrámos campanhas"
                    description="Experimente outra pesquisa ou categoria."
                    actionLabel="Limpar filtros"
                    action={{ label: 'Limpar filtros', onClick: clear }}
                />
            )}
        </main>
    );
}
