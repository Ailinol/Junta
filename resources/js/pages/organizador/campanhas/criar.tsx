import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { AiReviewPanel } from '@/components/junta/AiReviewPanel';
import { CampaignVisual } from '@/components/junta/CampaignVisual';
import { OperationalPanel } from '@/components/junta/OperationalPanel';
import { PageShell, SummaryCard } from '@/components/junta/OrganizerUi';
import { maputoInput, maputoDate } from '@/lib/organizer-presentation';
import { formatMZN } from '@/lib/formatters';
import { campaignCategories } from '@/lib/campaign-presentation';
import { mutate, errorMessage, ValidationFailure } from '@/lib/server-mutation';
import { store, update, index } from '@/routes/organizador/campanhas';
import type { Campaign } from '@/types/junta';
import type { OrganizerCampaign } from '@/types/organizer';

export default function CreateCampaign() {
    const { organizerCampaign } = usePage<{
        organizerCampaign?: OrganizerCampaign;
    }>().props;
    return (
        <CampaignEditor
            key={organizerCampaign?.campaign.id ?? 'new'}
            campaign={organizerCampaign?.campaign}
        />
    );
}
function CampaignEditor({ campaign }: { campaign?: Campaign }) {
    const [step, setStep] = useState(0);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [form, setForm] = useState({
        title: campaign?.title ?? '',
        description: campaign?.description ?? '',
        category: campaign?.category ?? 'outro',
        unit: campaign?.unit ?? '',
        imageUrl: campaign?.imageUrl ?? '',
        targetQuantity: campaign ? String(campaign.targetQuantity) : '',
        estimatedPrice:
            campaign?.estimatedPrice == null
                ? ''
                : String(campaign.estimatedPrice),
        deadline: campaign ? maputoInput(campaign.deadline) : '',
        location: campaign?.location ?? '',
        deliveryMode: campaign?.deliveryMode ?? 'recolha',
        context: campaign?.tags?.join('; ') ?? '',
    });
    const set = (key: keyof typeof form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const validate = (current: number) => {
        const next: Record<string, string> = {};
        const required =
            current === 0
                ? ['title', 'description', 'unit']
                : ['targetQuantity', 'deadline', 'location'];
        for (const key of required as (keyof typeof form)[]) {
            if (!form[key].trim()) next[key] = 'Preencha este campo.';
        }
        if (
            current === 1 &&
            form.targetQuantity &&
            (!Number.isInteger(Number(form.targetQuantity)) ||
                Number(form.targetQuantity) <= 0)
        )
            next.targetQuantity =
                'Indique uma quantidade inteira superior a zero.';
        if (
            current === 1 &&
            form.deadline &&
            new Date(form.deadline + ':00+02:00').getTime() <= Date.now()
        )
            next.deadline = 'Indique um prazo futuro em hora de Maputo.';
        setErrors(next);
        return !Object.keys(next).length;
    };
    const save = async (status: 'rascunho' | 'ativa') => {
        if (saving) return;
        setSaving(true);
        setErrors({});
        try {
            await mutate(campaign ? update(campaign.id) : store(), {
                ...form,
                status,
            });
        } catch (failure) {
            const next =
                failure instanceof ValidationFailure
                    ? failure.errors
                    : { campaign: errorMessage(failure) };
            setErrors(next);
            const productKeys = [
                'title',
                'description',
                'category',
                'unit',
                'imageUrl',
            ];
            setStep(
                Object.keys(next).some((key) => productKeys.includes(key))
                    ? 0
                    : 1,
            );
        } finally {
            setSaving(false);
        }
    };
    const field = (
        key: keyof typeof form,
        label: string,
        type = 'text',
        limit?: number,
        hint?: string,
        child?: ReactNode,
    ) => (
        <label className="organizer-field" key={key}>
            <span>{label}</span>
            {child ??
                (key === 'description' || key === 'context' ? (
                    <textarea
                        className="junta-input min-h-32"
                        value={form[key]}
                        maxLength={limit}
                        onChange={(event) => set(key, event.target.value)}
                        aria-invalid={!!errors[key]}
                        aria-describedby={
                            errors[key] ? `error-${key}` : undefined
                        }
                    />
                ) : (
                    <input
                        className="junta-input"
                        type={type}
                        value={form[key]}
                        maxLength={limit}
                        min={type === 'number' ? '0.01' : undefined}
                        step={
                            type === 'number'
                                ? key === 'targetQuantity'
                                    ? '1'
                                    : '0.01'
                                : undefined
                        }
                        onChange={(event) => set(key, event.target.value)}
                        aria-invalid={!!errors[key]}
                        aria-describedby={
                            errors[key] ? `error-${key}` : undefined
                        }
                    />
                ))}
            {limit && (
                <small className="text-muted-foreground">
                    {form[key].length}/{limit} caracteres
                </small>
            )}
            {hint && <small className="text-muted-foreground">{hint}</small>}
            {errors[key] && (
                <span
                    id={`error-${key}`}
                    className="text-destructive"
                    role="alert"
                >
                    {errors[key]}
                </span>
            )}
        </label>
    );
    const preview: Campaign = {
        ...(campaign ?? {
            id: 0,
            status: 'rascunho',
            organizerName: 'Organizador',
            participantCount: 0,
            currentQuantity: 0,
            createdAt: '',
            updatedAt: '',
        }),
        ...form,
        category: form.category as Campaign['category'],
        deliveryMode: form.deliveryMode as Campaign['deliveryMode'],
        targetQuantity: Number(form.targetQuantity),
        estimatedPrice: form.estimatedPrice
            ? Number(form.estimatedPrice)
            : undefined,
    };
    return (
        <PageShell
            title={campaign ? 'Editar campanha' : 'Criar campanha'}
            description="Prepare o produto, defina as condições e reveja antes de publicar."
            back={{ href: index.url(), label: 'Campanhas' }}
            actions={
                <button
                    type="button"
                    className="junta-btn junta-btn-md junta-btn-outline"
                    disabled={saving}
                    onClick={() => void save('rascunho')}
                >
                    {saving ? 'A guardar…' : 'Guardar rascunho'}
                </button>
            }
        >
            <ol
                className="organizer-wizard-steps"
                aria-label="Passos de criação"
            >
                {['Produto', 'Condições', 'Revisão'].map((label, position) => (
                    <li
                        key={label}
                        aria-current={step === position ? 'step' : undefined}
                    >
                        <span>{position + 1}</span>
                        {label}
                    </li>
                ))}
            </ol>
            {!!Object.keys(errors).length && (
                <div role="alert" className="organizer-alert">
                    <div>
                        <strong>Verifique os campos indicados.</strong>
                        <ul>
                            {Object.entries(errors).map(([key, message]) => (
                                <li key={key}>{message}</li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}
            <p className="organizer-caption">
                O rascunho fica privado. Para o guardar, as regras actuais
                exigem os dados do produto e das condições completos.
            </p>
            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    if (step < 2) {
                        if (validate(step)) setStep(step + 1);
                    } else void save('ativa');
                }}
            >
                {step === 0 && (
                    <OperationalPanel
                        title="1. Produto"
                        emphasis="brand"
                        description="Dê ao grupo os detalhes necessários para decidir."
                    >
                        <div className="grid gap-6">
                            {field('title', 'Nome da campanha', 'text', 255)}
                            {field(
                                'description',
                                'Descrição do produto',
                                'text',
                                10000,
                            )}
                            <div className="organizer-fields">
                                {field(
                                    'category',
                                    'Categoria',
                                    'text',
                                    undefined,
                                    undefined,
                                    <select
                                        className="junta-input"
                                        value={form.category}
                                        onChange={(event) =>
                                            set('category', event.target.value)
                                        }
                                    >
                                        {Object.entries(campaignCategories).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>,
                                )}
                                {field('unit', 'Unidade de medida', 'text', 60)}
                            </div>
                            {field(
                                'imageUrl',
                                'URL da fotografia (opcional)',
                                'url',
                                2048,
                                'Use uma ligação https:// ou http:// para uma fotografia do produto.',
                            )}
                            <div className="max-w-md">
                                <CampaignVisual
                                    key={form.imageUrl}
                                    campaign={preview}
                                />
                            </div>
                        </div>
                    </OperationalPanel>
                )}
                {step === 1 && (
                    <OperationalPanel
                        title="2. Condições"
                        emphasis="brand"
                        description="As datas e horas são sempre de Maputo."
                    >
                        <div className="grid gap-6">
                            <div className="organizer-fields">
                                {field(
                                    'targetQuantity',
                                    'Meta de interesse (unidades)',
                                    'number',
                                )}
                                {field(
                                    'estimatedPrice',
                                    'Preço de referência por unidade (MZN, estimativa)',
                                    'number',
                                    undefined,
                                    'Opcional. O preço final depende da proposta escolhida.',
                                )}
                                {field(
                                    'deadline',
                                    'Prazo de interesse · hora de Maputo',
                                    'datetime-local',
                                )}
                                {field('location', 'Localização', 'text', 255)}
                            </div>
                            {field(
                                'deliveryMode',
                                'Como receber',
                                'text',
                                undefined,
                                undefined,
                                <select
                                    className="junta-input"
                                    value={form.deliveryMode}
                                    onChange={(event) =>
                                        set('deliveryMode', event.target.value)
                                    }
                                >
                                    <option value="recolha">Recolha</option>
                                    <option value="entrega">Entrega</option>
                                    <option value="ambos">
                                        Recolha ou entrega
                                    </option>
                                </select>,
                            )}
                            {field(
                                'context',
                                'Contexto adicional (opcional)',
                                'text',
                                1000,
                            )}
                        </div>
                    </OperationalPanel>
                )}
                {step === 2 && (
                    <div className="organizer-columns">
                        <div className="grid gap-6">
                            <OperationalPanel
                                title="3. Como aparece no catálogo"
                                emphasis="brand"
                            >
                                <div className="grid gap-4">
                                    <CampaignVisual campaign={preview} />
                                    <p className="organizer-caption">
                                        {campaignCategories[preview.category]} ·{' '}
                                        {form.location}
                                    </p>
                                    <h2>{form.title}</h2>
                                    <p>
                                        {form.targetQuantity} {form.unit} · meta
                                        de interesse
                                    </p>
                                    <p>
                                        {form.estimatedPrice
                                            ? formatMZN(
                                                  Number(form.estimatedPrice),
                                              ) + ' por unidade · estimativa'
                                            : 'Preço a definir com o fornecedor'}
                                    </p>
                                    <details>
                                        <summary>
                                            Pré-visualizar detalhe
                                        </summary>
                                        <p className="mt-4 whitespace-pre-wrap">
                                            {form.description}
                                        </p>
                                        {form.context && (
                                            <p className="mt-4">
                                                {form.context}
                                            </p>
                                        )}
                                    </details>
                                </div>
                            </OperationalPanel>
                            <SummaryCard
                                title="Condições para publicar"
                                items={[
                                    {
                                        label: 'Prazo',
                                        value: form.deadline
                                            ? maputoDate(
                                                  form.deadline + ':00+02:00',
                                              ) + ' · Maputo'
                                            : 'Por indicar',
                                    },
                                    {
                                        label: 'Como receber',
                                        value:
                                            form.deliveryMode === 'ambos'
                                                ? 'Recolha ou entrega'
                                                : form.deliveryMode ===
                                                    'recolha'
                                                  ? 'Recolha'
                                                  : 'Entrega',
                                    },
                                    {
                                        label: 'Localização',
                                        value: form.location,
                                    },
                                ]}
                            />
                        </div>
                        <aside>
                            <details className="organizer-technical">
                                <summary>
                                    Revisão de IA · apoio consultivo opcional
                                </summary>
                                <AiReviewPanel kind="campaign" payload={form} />
                            </details>
                        </aside>
                    </div>
                )}
                <div className="mt-6 flex flex-wrap items-center gap-4">
                    {step > 0 && (
                        <button
                            type="button"
                            onClick={() => setStep(step - 1)}
                            className="junta-btn junta-btn-md junta-btn-outline"
                            disabled={saving}
                        >
                            Anterior
                        </button>
                    )}
                    <button
                        type="submit"
                        className="junta-btn junta-btn-md junta-btn-primary"
                        disabled={saving}
                    >
                        {saving
                            ? 'A guardar…'
                            : step < 2
                              ? 'Continuar'
                              : campaign?.status === 'ativa'
                                ? 'Guardar alterações'
                                : 'Publicar campanha'}
                    </button>
                    <Link href={index()} className="junta-link">
                        Voltar às campanhas
                    </Link>
                </div>
            </form>
        </PageShell>
    );
}
