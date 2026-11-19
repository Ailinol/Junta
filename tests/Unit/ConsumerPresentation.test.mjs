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

const { paymentFormReady, localPhone, humanDeadline } =
    await import('../../resources/js/lib/consumer-presentation.ts');
const { PaymentMethodPicker, PhoneField, OrderTimeline, OrderCard } =
    await import('../../resources/js/components/junta/ConsumerUi.tsx');
const { PurchaseCheckout, WaitingScreen, ResultScreen } =
    await import('../../resources/js/components/junta/PurchaseCheckout.tsx');
const render = (component, props) =>
    renderToStaticMarkup(React.createElement(component, props));
const methods = [
    { code: 'mpesa', label: 'M-Pesa', requiresPhone: true },
    { code: 'emola', label: 'e-Mola', requiresPhone: true },
];
const valid = {
    paymentMethod: 'mpesa',
    paymentPhone: '+258841231234',
    acceptTerms: true,
    deliveryMethod: 'recolha',
    deliveryAddress: '',
};
await test('payment requires an available method nine phone digits accepted terms and a delivery address when applicable', () => {
    assert.equal(paymentFormReady(valid, methods), true);
    for (const patch of [
        { paymentMethod: '' },
        { paymentMethod: 'card' },
        { paymentPhone: '841231234' },
        { paymentPhone: '+25884123123' },
        { acceptTerms: false },
        { deliveryMethod: 'entrega' },
    ])
        assert.equal(paymentFormReady({ ...valid, ...patch }, methods), false);
    assert.equal(
        paymentFormReady(
            { ...valid, deliveryMethod: 'entrega', deliveryAddress: 'Rua 24' },
            methods,
        ),
        true,
    );
});
await test('phone formatting accepts profile numbers and preserves exactly nine national digits', () => {
    assert.equal(localPhone('+258 84 123 1234'), '841231234');
    assert.equal(localPhone('84 123 1234'), '841231234');
    assert.equal(localPhone('+258'), '');
    assert.equal(localPhone('258123456'), '258123456');
    assert.equal(localPhone('+258841231234999'), '841231234');
});
await test('multiple payment methods begin unselected and one selection produces one checked radio', () => {
    const empty = render(PaymentMethodPicker, {
        methods,
        value: '',
        onChange: () => {},
    });
    assert.equal((empty.match(/type="radio"/g) ?? []).length, 2);
    assert.doesNotMatch(empty, /checked=""|data-selected/);
    const selected = render(PaymentMethodPicker, {
        methods,
        value: 'emola',
        onChange: () => {},
    });
    assert.equal((selected.match(/checked=""/g) ?? []).length, 1);
    assert.equal((selected.match(/data-selected="true"/g) ?? []).length, 1);
});
await test('phone field displays a fixed country prefix numeric keyboard and an inline accessible error', () => {
    const html = render(PhoneField, {
        value: '+258841231234',
        onChange: () => {},
        error: 'Use nove dígitos.',
    });
    assert.match(html, /inputMode="numeric"/);
    assert.match(html, /value="84 123 1234"/);
    assert.match(html, /aria-invalid="true"/);
    assert.match(html, /role="alert"/);
    assert.match(html, /\+258/);
});
const state = {
    code: 'payment',
    label: 'A pagar',
    tone: 'neutral',
    isFinal: false,
    primaryAction: { code: 'pay', label: 'Pagar' },
    deadline: null,
    steps: [],
    description: 'Escolha como pagar.',
    canEditInterest: false,
    canReportIssue: false,
};
await test('order cards use one business state and one contextual payment destination', () => {
    const html = render(OrderCard, {
        order: {
            id: 1,
            campaign: {
                id: 42,
                title: 'Arroz',
                category: 'alimentacao',
                unit: 'sacos',
            },
            quantity: 3,
            orderId: 7,
            totalCents: 722625,
            state,
        },
    });
    assert.match(html, /A pagar/);
    assert.match(html, /pedidos\/7\/pagar/);
    assert.doesNotMatch(html, /JNT-|UUID|<svg/);
});
await test('a refunded timeline disappears and completed steps expose completion and the current step', () => {
    assert.equal(
        render(OrderTimeline, {
            state: { ...state, code: 'refunded', isFinal: true },
        }),
        '',
    );
    const html = render(OrderTimeline, {
        state: {
            ...state,
            steps: [
                { label: 'Interesse registado', status: 'complete' },
                { label: 'Pagamento', status: 'current' },
                { label: 'Mínimo atingido', status: 'upcoming' },
            ],
        },
    });
    assert.match(html, /aria-current="step"/);
    assert.match(html, /data-status="complete"/);
    assert.match(html, /data-status="upcoming"/);
});
await test('human deadlines use Maputo calendar days across UTC midnight', () => {
    assert.equal(
        humanDeadline(
            '2026-11-18T22:30:00Z',
            Date.parse('2026-11-18T21:30:00Z'),
        ).label,
        'Termina amanhã',
    );
    assert.equal(
        humanDeadline(
            '2026-11-05T10:00:00Z',
            Date.parse('2026-11-18T21:30:00Z'),
        ).label,
        'Terminou há 13 dias',
    );
});
await test('checkout keeps all sections visible and disables payment before a method and terms are selected', () => {
    const order = {
        id: 7,
        quantity: 3,
        delivery_method: 'recolha',
        delivery_address: null,
        accepted_at: null,
        conditions: {
            terms_version: 'v1',
            terms: 'Termos',
            pickup_point: 'Mercado',
            minimum_quantity: 3,
        },
        total_cents: 722625,
        merchandise_cents: 700000,
        commission_cents: 22625,
        supplier_transport_cents: 0,
        delivery_fee_cents: 0,
    };
    const html = render(PurchaseCheckout, {
        purchase: {
            id: 42,
            view: state,
            order,
            config: order.conditions,
            paymentMethods: methods,
            quote: order,
        },
        campaign: {
            id: 42,
            title: 'Arroz',
            unit: 'sacos',
            category: 'alimentacao',
        },
    });
    assert.match(html, /Método de pagamento/);
    assert.match(html, /Dados do pagamento/);
    assert.match(html, /Termos da compra/);
    assert.match(html, /<button[^>]*type="submit"[^>]*disabled/);
    assert.equal((html.match(/type="submit"/g) ?? []).length, 1);
});

await test('waiting displays the masked phone countdown and verification fallback without checkout fields', () => {
    const html = render(WaitingScreen, {
        purchase: {
            id: 42,
            serverNow: '2026-11-18T12:00:00Z',
            order: { id: 7 },
            confirmation: {
                method: 'M-Pesa',
                phone: '+258 84 *** 1234',
                expiresAt: '2026-11-18T12:02:45Z',
            },
        },
    });
    assert.match(html, /Confirme no seu telemóvel/);
    assert.match(html, /\+258 84 \*\*\* 1234/);
    assert.match(html, /02:45/);
    assert.match(html, /Já confirmei/);
    assert.doesNotMatch(html, /type="radio"|type="tel"|Pagar agora/);
});
await test('payment results separate success navigation from retrying a confirmed failure', () => {
    const purchase = {
        id: 42,
        title: 'Arroz',
        view: state,
        order: { id: 7, quantity: 3, total_cents: 722625 },
        confirmation: { canRetry: true },
    };
    const success = render(ResultScreen, { purchase, success: true });
    assert.match(success, /Pagamento recebido/);
    assert.match(success, /Ver a minha compra/);
    assert.doesNotMatch(success, /Tentar novamente/);
    const failure = render(ResultScreen, { purchase, success: false });
    assert.match(failure, /Tentar novamente/);
    assert.match(failure, /Escolher outro método/);
    assert.match(failure, /retry=1/);
});
