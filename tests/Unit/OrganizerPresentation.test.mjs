import { test } from 'node:test';
import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import ts from 'typescript';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
const root = path.resolve('resources/js');
registerHooks({
    resolve(specifier, context, next) {
        if (
            specifier.startsWith('@/') ||
            (specifier.startsWith('.') &&
                context.parentURL?.includes('/resources/js/'))
        ) {
            const base = specifier.startsWith('@/')
                ? path.join(root, specifier.slice(2))
                : fileURLToPath(new URL(specifier, context.parentURL));
            const found = ['', '.ts', '.tsx', '/index.ts', '/index.tsx']
                .map((suffix) => base + suffix)
                .find(
                    (file) => existsSync(file) && /\.[cm]?[jt]sx?$/.test(file),
                );
            if (found)
                return { url: pathToFileURL(found).href, shortCircuit: true };
        }
        return next(specifier, context);
    },
    load(url, context, next) {
        if (
            url.startsWith('file:') &&
            /\.tsx?$/.test(url) &&
            url.includes('/resources/js/')
        )
            return {
                format: 'module',
                source: ts.transpileModule(
                    readFileSync(fileURLToPath(url), 'utf8'),
                    {
                        compilerOptions: {
                            jsx: ts.JsxEmit.ReactJSX,
                            module: ts.ModuleKind.ESNext,
                            target: ts.ScriptTarget.ES2022,
                            verbatimModuleSyntax: false,
                        },
                    },
                ).outputText,
                shortCircuit: true,
            };
        return next(url, context);
    },
});
const { StatusPill } =
    await import('../../resources/js/components/junta/StatusBadge.tsx');
const {
    NextStepBanner,
    PhaseStepper,
    ProgressMeter,
    DeadlineLabel,
    DataTable,
    TechnicalDetails,
} = await import('../../resources/js/components/junta/OrganizerUi.tsx');
const { paymentExample, maputoInput, csvValue } =
    await import('../../resources/js/lib/organizer-presentation.ts');
const render = (component, props) =>
    renderToStaticMarkup(React.createElement(component, props));
const state = {
    code: 'payment',
    label: 'Pagamentos',
    tone: 'info',
    icon: 'wallet',
    isFinal: false,
    primaryAction: null,
    waitingOn: null,
    blocker: null,
    step: 4,
};
await test('state is presented with text and an icon rather than colour alone', () => {
    const html = render(StatusPill, { state });
    assert.match(html, /Pagamentos/);
    assert.match(html, /<svg/);
    assert.match(html, /data-tone="info"/);
});
await test('final campaigns have no next step even when a stale action is supplied', () => {
    assert.equal(
        render(NextStepBanner, {
            view: {
                state: {
                    ...state,
                    isFinal: true,
                    primaryAction: { label: 'Pagar', target: 'purchase' },
                },
                campaign: { id: 1 },
            },
        }),
        '',
    );
});
await test('waiting explains the overdue cause and actor without an admin link', () => {
    const html = render(NextStepBanner, {
        view: {
            campaign: { id: 1 },
            state: {
                ...state,
                waitingOn: {
                    label: 'A aguardar confirmação',
                    since: '2026-11-17T10:00:00Z',
                },
                blocker:
                    '2 pagamentos por confirmar impedem a verificação do mínimo.',
                description: 'O sistema está a confirmar os pagamentos.',
            },
        },
    });
    assert.match(html, /2 pagamentos por confirmar/);
    assert.match(html, /O sistema/);
    assert.match(html, /Maputo/);
    assert.doesNotMatch(html, /href=.*admin/);
});
await test('the phase stepper exposes the same current stage in both screen sizes', () => {
    const html = render(PhaseStepper, { state });
    assert.match(html, /Etapa 5 de 7 · Pagamentos/);
    assert.match(html, /aria-current="step"/);
});
await test('the payment indicator retains the actual number while clamping the bar', () => {
    const html = render(ProgressMeter, {
        value: 15,
        target: 10,
        label: 'Pagos 15 de 10 do mínimo',
    });
    assert.match(html, /Pagos 15 de 10 do mínimo/);
    assert.match(html, /<progress value="10" max="10"/);
});
await test('deadline provides human text and the full Maputo time', () => {
    const html = render(DeadlineLabel, {
        deadline: {
            value: '2026-11-17T16:00:00Z',
            label: 'Termina hoje às 18:00',
            detail: '17/11/2026 às 18:00 · Maputo',
            isPast: false,
        },
    });
    assert.match(html, /Termina hoje às 18:00/);
    assert.match(html, /17\/11\/2026 às 18:00 · Maputo/);
});
await test('table and mobile cards identify the person with the same business state', () => {
    const html = render(DataTable, {
        records: [{ id: 1, name: 'Ana Matavele' }],
        rowKey: (row) => row.id,
        label: 'Participantes',
        columns: [{ key: 'name', label: 'Nome', render: (row) => row.name }],
    });
    assert.match(html, /<table/);
    assert.match(html, /md:hidden/);
    assert.equal((html.match(/Ana Matavele/g) ?? []).length, 2);
});
await test('technical identifiers start inside a collapsed disclosure', () => {
    const html = render(TechnicalDetails, { children: 'support-reference' });
    assert.match(html, /<details[^>]*>/);
    assert.doesNotMatch(html, /<details[^>]*open/);
    assert.match(html, /Detalhes técnicos/);
});
await test('one unit preview rounds commission in cents and applies delivery once', () => {
    assert.deepEqual(paymentExample(10010, 200, '2.75', '25.00'), {
        commissionCents: 275,
        pickupCents: 10485,
        deliveryCents: 12985,
    });
    assert.equal(paymentExample(10010, 200, '101', '0'), null);
});
await test('editing converts an instant to Maputo wall time and CSV neutralises formulas', () => {
    assert.equal(maputoInput('2026-11-17T10:30:00Z'), '2026-11-17T12:30');
    assert.equal(csvValue('\t=SUM(A1)'), `"'\t=SUM(A1)"`);
    assert.equal(csvValue('=SUM(A1)'), `"'=SUM(A1)"`);
    assert.equal(csvValue('Ana "A"'), '"Ana ""A"""');
});

const { organizerStateColor, organizerProgressColor } =
    await import('../../resources/js/lib/organizer-colors.ts');
await test('one colour vocabulary distinguishes unpaid confirming ready terminal and problem states', () => {
    for (const [code, color] of [
        ['payment', 'warning'],
        ['payment_pending', 'info'],
        ['paid', 'success'],
        ['ready', 'brand'],
        ['completed', 'success-deep'],
        ['refunded', 'refunded'],
        ['cancelled', 'neutral'],
        ['expired', 'danger'],
        ['issue', 'danger'],
    ]) {
        assert.equal(
            organizerStateColor({ code, tone: 'neutral' }),
            color,
            code,
        );
    }
    assert.equal(
        organizerStateColor({ code: 'payment', step: 4, tone: 'info' }),
        'brand',
    );
});
await test('the ready badge retains its text and icon while requesting the solid brand treatment', () => {
    const html = render(StatusPill, {
        state: {
            code: 'ready',
            label: 'Pronto',
            tone: 'success',
            icon: 'package',
        },
    });
    assert.match(html, /Pronto/);
    assert.match(html, /<svg/);
    assert.match(html, /data-color="brand"/);
    assert.match(html, /data-solid="true"/);
});
await test('progress signals a short deadline without hiding that the minimum was achieved', () => {
    const now = Date.parse('2026-11-17T10:00:00Z');
    const deadline = { value: '2026-11-18T09:59:00Z', isPast: false };
    assert.equal(organizerProgressColor(2, 3, deadline, now), 'warning');
    assert.equal(organizerProgressColor(3, 3, deadline, now), 'success');
    assert.equal(
        organizerProgressColor(
            2,
            3,
            { ...deadline, value: '2026-11-18T10:00:00Z' },
            now,
        ),
        'brand',
    );
    assert.equal(organizerProgressColor(0, 0, null, now), 'brand');
    assert.equal(
        organizerProgressColor(2, 3, { ...deadline, isPast: true }, now),
        'warning',
    );
});

await test('a pending campaign task marks its desktop row and mobile card for attention', () => {
    const html = render(DataTable, {
        records: [{ id: 1, name: 'Arroz' }],
        rowKey: (row) => row.id,
        rowState: () => ({
            ...state,
            code: 'interest',
            step: 1,
            attention: true,
        }),
        label: 'Campanhas',
        columns: [
            { key: 'name', label: 'Campanha', render: (row) => row.name },
        ],
    });
    assert.equal((html.match(/data-attention="true"/g) ?? []).length, 2);
});

const { CompactCampaignTable, CampaignListDeadline, CampaignListNextStep } =
    await import('../../resources/js/components/junta/OrganizerCampaignTable.tsx');
const compactView = {
    campaign: {
        id: 42,
        title: 'Papel A4',
        reference: 'JNT-000042',
        location: 'Matola',
        imageUrl: null,
    },
    state: {
        ...state,
        code: 'supply',
        label: 'Em distribuição',
        icon: 'package',
    },
    list: {
        section: 'attention',
        action: {
            code: 'handoff',
            label: 'Entregar pedidos',
            target: 'purchase',
        },
        waiting: null,
        progress: {
            value: 48,
            target: 48,
            label: 'Recebidas: 48 de 48 caixas',
        },
        deadline: null,
    },
};
await test('compact progress shows numbers once and keeps units only in its tooltip and accessible label', () => {
    const html = render(ProgressMeter, {
        compact: true,
        value: 10,
        target: 300,
        label: 'Procura: 10 de 300 caixas',
    });
    assert.match(html, /<p>10 \/ 300<\/p>/);
    assert.match(html, /title="Procura: 10 de 300 caixas"/);
    assert.doesNotMatch(
        html,
        /organizer-progress-goal|organizer-progress-marker|Meta|Mínimo|<svg/,
    );
    assert.doesNotMatch(html.replace(/<[^>]*>/g, ''), /caixas|Procura/);
});
await test('compact phase uses a single dot and label without an icon', () => {
    const html = render(StatusPill, {
        state: compactView.state,
        compact: true,
    });
    assert.equal((html.match(/organizer-status-dot/g) ?? []).length, 1);
    assert.match(html, /Em distribuição/);
    assert.doesNotMatch(html, /<svg/);
});
await test('compact campaign table hides references locations generic images and arrow icons', () => {
    const html = render(CompactCampaignTable, { records: [compactView] });
    assert.equal((html.match(/scope="col"/g) ?? []).length, 4);
    assert.match(html, /title="Papel A4"/);
    assert.match(html, /48 \/ 48/);
    assert.match(html, /Entregar pedidos/);
    assert.doesNotMatch(
        html,
        /JNT-000042|Matola|<svg|<img|Em preparação|organizer-progress-goal|data-attention/,
    );
});
await test('waiting rows expose only a brief reason even if a stale action is present', () => {
    const html = render(CampaignListNextStep, {
        view: {
            ...compactView,
            list: {
                ...compactView.list,
                section: 'waiting',
                waiting: '1 pagamento por confirmar',
            },
        },
    });
    assert.match(html, /1 pagamento por confirmar/);
    assert.doesNotMatch(html, /<a|<button|Entregar pedidos/);
});
await test('deadline colour follows the server blocking flag instead of elapsed time alone', () => {
    const view = {
        ...compactView,
        list: {
            ...compactView.list,
            deadline: {
                value: '2026-11-05T10:00:00Z',
                label: 'terminou há 13 dias',
                detail: '05/11/2026 às 12:00 · Maputo',
                blocked: false,
            },
        },
    };
    const neutral = render(CampaignListDeadline, { view });
    assert.match(neutral, /terminou há 13 dias/);
    assert.match(neutral, /title="05\/11\/2026 às 12:00 · Maputo"/);
    assert.doesNotMatch(neutral, /data-blocked/);
    assert.match(
        render(CampaignListDeadline, {
            view: {
                ...view,
                list: {
                    ...view.list,
                    deadline: { ...view.list.deadline, blocked: true },
                },
            },
        }),
        /data-blocked="true"/,
    );
});

await test('compact thumbnails appear only for campaign image sources', () => {
    const html = render(CompactCampaignTable, {
        records: [
            {
                ...compactView,
                campaign: {
                    ...compactView.campaign,
                    imageUrl: '/storage/campaigns/papel.jpg',
                },
            },
        ],
    });
    assert.equal((html.match(/<img/g) ?? []).length, 2);
    assert.match(html, /src="\/storage\/campaigns\/papel.jpg"/);
    assert.doesNotMatch(html, /<svg/);
});
