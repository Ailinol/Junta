import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    buildAdminReport,
    reportPhases,
    topParticipatedCampaigns,
} from '../../resources/js/lib/admin-reporting.ts';
const now = Date.parse('2026-11-18T10:00:00Z');
const campaign = (values = {}) => ({
    id: 1,
    title: 'Compra do bairro',
    description: 'Produtos para o bairro',
    category: 'alimentacao',
    status: 'ativa',
    organizerName: 'Lina',
    targetQuantity: 100,
    currentQuantity: 10,
    unit: 'sacos',
    participantCount: 2,
    deadline: '2026-12-01T10:00:00Z',
    location: 'Maputo',
    createdAt: '2026-11-10T10:00:00Z',
    updatedAt: '2026-11-10T10:00:00Z',
    ...values,
});
void test('the reporting window uses Maputo months and excludes invalid or future campaign dates', () => {
    const report = buildAdminReport(
        [
            campaign({ id: 1, createdAt: '2026-08-31T22:00:00Z' }),
            campaign({ id: 2, createdAt: '2026-08-31T21:59:59Z' }),
            campaign({ id: 3, createdAt: '2026-11-18T10:00:01Z' }),
            campaign({ id: 4, createdAt: 'invalid' }),
            campaign({ id: 5, createdAt: '2026-11-18T10:00:00Z' }),
        ],
        3,
        now,
    );
    assert.deepEqual(
        report.campaigns.map((item) => item.id),
        [1, 5],
    );
    assert.deepEqual(
        report.months.map((month) => [month.key, month.campaigns]),
        [
            ['2026-09', 1],
            ['2026-10', 0],
            ['2026-11', 1],
        ],
    );
});
void test('monthly windows cross the year boundary without dropping empty months', () => {
    const report = buildAdminReport(
        [campaign({ createdAt: '2025-12-31T22:30:00Z' })],
        3,
        Date.parse('2026-01-15T10:00:00Z'),
    );
    assert.deepEqual(
        report.months.map((month) => month.key),
        ['2025-11', '2025-12', '2026-01'],
    );
    assert.deepEqual(
        report.months.map((month) => month.campaigns),
        [0, 0, 1],
    );
});
void test('estimates use declared prices and interest while missing prices do not become revenue', () => {
    const report = buildAdminReport(
        [
            campaign({
                id: 1,
                category: 'construcao',
                estimatedPrice: 500,
                currentQuantity: 2,
                unit: 'sacos',
                participantCount: 2,
            }),
            campaign({
                id: 2,
                estimatedPrice: 100,
                currentQuantity: 3,
                unit: 'caixas',
                participantCount: 1,
            }),
            campaign({
                id: 3,
                estimatedPrice: undefined,
                currentQuantity: 1000,
                participantCount: 4,
            }),
            campaign({ id: 4, estimatedPrice: NaN, participantCount: 1 }),
        ],
        3,
        now,
    );
    assert.equal(report.estimatedVolume, 1300);
    assert.equal(report.participants, 8);
    assert.equal(report.pricedCampaigns, 2);
    assert.deepEqual(
        report.categories.map((item) => [item.key, item.estimatedVolume]),
        [
            ['construcao', 1000],
            ['alimentacao', 300],
        ],
    );
    assert.equal(report.months[2].participants, 8);
});
void test('regional grouping trims declared locality names and keeps unnamed locations visible', () => {
    const report = buildAdminReport(
        [
            campaign({
                id: 1,
                location: ' Maputo, Centro',
                estimatedPrice: 10,
            }),
            campaign({ id: 2, location: 'Maputo', estimatedPrice: 20 }),
            campaign({ id: 3, location: ' ' }),
        ],
        3,
        now,
    );
    assert.deepEqual(
        report.regions.map((region) => [
            region.label,
            region.campaigns,
            region.estimatedVolume,
        ]),
        [
            ['Maputo', 2, 300],
            ['Localização não indicada', 1, 0],
        ],
    );
});
void test('phase charts trust the phase supplied by the server rather than the commercial status', () => {
    const phases = reportPhases([
        campaign({ status: 'confirmada', effectiveStage: 'payment' }),
        campaign({ id: 2, status: 'confirmada', effectiveStage: 'supply' }),
    ]);
    assert.deepEqual(
        phases.map((phase) => [phase.key, phase.label, phase.value]),
        [
            ['payment', 'Pagamentos', 1],
            ['supply', 'Fornecimento', 1],
        ],
    );
});
void test('campaign rankings compare participation instead of incompatible quantities and preserve source order', () => {
    const source = [
        campaign({
            id: 1,
            currentQuantity: 1000,
            unit: 'litros',
            participantCount: 2,
        }),
        campaign({
            id: 2,
            currentQuantity: 4,
            unit: 'caixas',
            participantCount: 8,
        }),
    ];
    assert.deepEqual(
        topParticipatedCampaigns(source).map((item) => item.id),
        [2, 1],
    );
    assert.deepEqual(
        source.map((item) => item.id),
        [1, 2],
    );
});
