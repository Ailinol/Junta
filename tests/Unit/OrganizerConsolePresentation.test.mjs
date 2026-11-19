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

const { ActionBanner, Stepper } =
    await import('../../resources/js/components/junta/OrganizerConsole.tsx');
const { ProgressMeter, DeadlineLabel } =
    await import('../../resources/js/components/junta/OrganizerUi.tsx');
const { OrganizerParticipantsTable } =
    await import('../../resources/js/components/junta/OrganizerParticipantsTable.tsx');
const render = (component, props) =>
    renderToStaticMarkup(React.createElement(component, props));
const state = {
    code: 'payment',
    label: 'Pagamentos',
    tone: 'info',
    isFinal: false,
    primaryAction: null,
    waitingOn: { label: 'Pagamentos a decorrer', since: null },
    blocker: null,
    description: 'Os participantes podem pagar.',
    progress: { value: 6, target: 32, label: 'Pagos 6 de 32 do mínimo' },
    deadline: null,
};
const view = {
    campaign: { id: 12, title: 'Arroz do bairro', unit: 'sacos' },
    workspace: state,
    list: { action: null },
    metrics: { openProposals: 3 },
};
await test('only an organizer task produces an action banner and final states are silent', () => {
    assert.equal(render(ActionBanner, { view, section: 'resumo' }), '');
    const final = {
        ...view,
        workspace: {
            ...state,
            isFinal: true,
            primaryAction: {
                code: 'close_interest',
                label: 'Fechar interesse',
                target: 'purchase',
            },
        },
    };
    assert.equal(render(ActionBanner, { view: final, section: 'resumo' }), '');
    const active = {
        ...view,
        workspace: {
            ...state,
            primaryAction: {
                code: 'proposals',
                label: 'Escolher fornecedor',
                target: 'proposals',
            },
        },
    };
    const html = render(ActionBanner, { view: active, section: 'resumo' });
    assert.match(html, /Escolher fornecedor/);
    assert.equal((html.match(/junta-btn-primary/g) ?? []).length, 1);
    assert.doesNotMatch(html, /\/admin\//);
});
await test('phase progress contains one measurement without the duplicated goal legend', () => {
    const html = render(ProgressMeter, { ...state.progress, showGoal: false });
    assert.match(html, /Pagos 6 de 32 do mínimo/);
    assert.equal((html.match(/<progress/g) ?? []).length, 1);
    assert.doesNotMatch(html, /organizer-progress-goal|Meta ·|Mínimo ·/);
});
await test('expired deadlines turn red only for a blocked task and keep the full date in a tooltip', () => {
    const deadline = {
        value: '2026-11-05T12:00:00+02:00',
        label: 'terminou há 13 dias',
        detail: '05/11/2026 às 12:00 · Maputo',
        isPast: true,
    };
    assert.doesNotMatch(
        render(DeadlineLabel, { deadline, compact: true, blocked: false }),
        /data-overdue/,
    );
    const html = render(DeadlineLabel, {
        deadline,
        compact: true,
        blocked: true,
    });
    assert.match(html, /data-overdue/);
    assert.match(html, /title="05\/11\/2026/);
    assert.match(html, /terminou há 13 dias/);
});
await test('participants show four interest columns and five payment columns with names and one state', () => {
    const participant = {
        id: 1,
        name: 'Ana Matavele',
        phone: '+258841234567',
        quantity: 3,
        joinedAt: '2026-11-18T12:00:00+02:00',
        notes: 'Depois das 16h',
        state: { ...state, code: 'unpaid', label: 'Por pagar' },
        orderId: null,
        amountCents: null,
    };
    for (const [paid, count] of [
        [false, 4],
        [true, 5],
    ]) {
        const html = render(OrganizerParticipantsTable, {
            view,
            participants: [
                {
                    ...participant,
                    orderId: paid ? 1 : null,
                    amountCents: paid ? 30000 : null,
                },
            ],
        });
        const head = html.match(/<thead>([\s\S]*?)<\/thead>/)[1];
        assert.equal((head.match(/<th /g) ?? []).length, count);
        assert.match(html, /Ana Matavele/);
        assert.match(html, /tel:\+258841234567/);
        assert.doesNotMatch(
            html,
            />Pedido<|tentativa|Ver pedido|>Observações<|organizer-kpi/,
        );
    }
});
await test('wizard stepper describes the current step without a second page title', () => {
    const html = render(Stepper, {
        step: 1,
        labels: ['Produto', 'Condições', 'Revisão'],
    });
    assert.match(html, /Passo 2 de 3/);
    assert.equal((html.match(/aria-current="step"/g) ?? []).length, 1);
    assert.doesNotMatch(html, /<h1/);
});
