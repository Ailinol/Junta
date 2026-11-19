import { test } from 'node:test';
import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import path from 'node:path';
import ts from 'typescript';
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

const { proposalReviewErrors } =
    await import('../../resources/js/lib/selected-form-validation.ts');
const { nextCampaignAction } =
    await import('../../resources/js/lib/campaign-management.ts');
const complete = {
    unitPrice: '50.50',
    availableQuantity: '30',
    minimumQuantity: '10',
    deliveryDays: '3',
    validUntil: '2099-12-10',
    reservedUntil: '2099-12-12',
    transportIncluded: '0',
    transportUnitPrice: '1.25',
};
await test('proposal review requires commercial fields before the review step', () => {
    assert.deepEqual(proposalReviewErrors(complete), {});
    for (const field of [
        'minimumQuantity',
        'reservedUntil',
        'transportUnitPrice',
    ])
        assert.ok(proposalReviewErrors({ ...complete, [field]: '' })[field]);
    assert.ok(
        proposalReviewErrors({ ...complete, minimumQuantity: '31' })
            .minimumQuantity,
    );
    assert.ok(
        proposalReviewErrors({ ...complete, transportIncluded: '1' })
            .transportUnitPrice,
    );
    assert.deepEqual(
        proposalReviewErrors({
            ...complete,
            transportIncluded: '1',
            transportUnitPrice: '0',
        }),
        {},
    );
});
await test('validity and reservation keep independent future windows', () => {
    assert.deepEqual(
        proposalReviewErrors({ ...complete, reservedUntil: '2099-12-09' }),
        {},
    );
    assert.ok(
        proposalReviewErrors({ ...complete, validUntil: '2000-01-01' })
            .validUntil,
    );
    assert.ok(
        proposalReviewErrors({ ...complete, reservedUntil: '2000-01-01' })
            .reservedUntil,
    );
});
await test('a selected proposal immediately points the organizer to payment preparation', () => {
    const action = nextCampaignAction({
        id: 5,
        status: 'proposta_selecionada',
        purchasePhase: 'interest',
    });
    assert.equal(action.label, 'Preparar pagamentos');
    assert.doesNotMatch(action.description, /confirmar|confirmação|aguardar/i);
});
