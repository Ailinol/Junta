import { Link } from '@inertiajs/react';
import { Page } from '@/components/junta/Page';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { RecordTable } from '@/components/junta/RecordTable';
import { usePrototypeStore } from '@/hooks/use-prototype-store';
import { formatDate } from '@/lib/formatters';
import { show } from '@/routes/fornecedor/oportunidades';
export default function Opportunities() {
    const { data } = usePrototypeStore();
    const records = data.campaigns.filter(
        (campaign) => campaign.acceptsProposals,
    );
    return (
        <Page
            title="Oportunidades de fornecimento"
            description="Consulte a procura e as condições antes de preparar uma proposta."
        >
            <RecordTable
                records={records}
                label="oportunidades"
                visual={(c) => <CampaignVisual campaign={c} thumbnail />}
                searchText={(c) => c.title + ' ' + c.location}
                filters={[
                    { value: 'construcao', label: 'Construção' },
                    { value: 'alimentacao', label: 'Alimentação' },
                    { value: 'eletronicos', label: 'Electrónica' },
                    { value: 'escritorio', label: 'Escritório' },
                    { value: 'agricola', label: 'Agrícola' },
                    { value: 'saude', label: 'Saúde' },
                    { value: 'servicos', label: 'Serviços' },
                    { value: 'outro', label: 'Outros' },
                ]}
                matchesFilter={(c, f) => c.category === f}
                columns={[
                    {
                        label: 'Oportunidade',
                        render: (c) => (
                            <div>
                                <Link
                                    href={show(c.id)}
                                    className="font-semibold hover:text-primary"
                                >
                                    {c.title}
                                </Link>
                                <p className="text-xs text-muted-foreground">
                                    {c.location}
                                </p>
                            </div>
                        ),
                    },
                    {
                        label: 'Procura agregada',
                        render: (c) => c.currentQuantity + ' ' + c.unit,
                    },
                    {
                        label: 'Objectivo',
                        render: (c) => c.targetQuantity + ' ' + c.unit,
                    },
                    {
                        label: 'Prazo da campanha',
                        render: (c) => formatDate(c.deadline),
                    },
                    {
                        label: 'Consultar',
                        render: (c) => (
                            <Link
                                href={show(c.id)}
                                className="text-xs font-semibold text-primary"
                            >
                                Ver condições
                            </Link>
                        ),
                    },
                ]}
            />
        </Page>
    );
}
