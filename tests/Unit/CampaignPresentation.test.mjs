import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    campaignUnit,
    campaignDate,
} from '../../resources/js/lib/campaign-presentation.ts';
import { addDemoParticipation } from '../../resources/js/lib/demo-participation.ts';

function store(overrides = {}) {
    return {
        campaigns: [
            {
                id: 1,
                status: 'ativa',
                deadline: '2999-01-01T00:00:00Z',
                currentQuantity: 320,
                participantCount: 18,
                ...overrides,
            },
            { id: 2, status: 'concluida', currentQuantity: 200 },
        ],
        participations: [{ id: 7, campaignId: 2, quantity: 4 }],
        proposals: [{ id: 1, campaignId: 1 }],
    };
}

void describe('campaign presentation', () => {
    void it('uses a singular purchasing unit for reference prices and one requested item', () => {
        assert.equal(campaignUnit('sacos'), 'saco');
        assert.equal(campaignUnit('litros', 1), 'litro');
        assert.equal(campaignUnit('sacos', 3), 'sacos');
        assert.equal(campaignUnit('kg'), 'kg');
        assert.equal(
            campaignUnit('embalagens especiais'),
            'embalagens especiais',
        );
    });

    void it('shows a deadline in Maputo even when UTC falls on the previous day', () => {
        assert.equal(campaignDate('2026-11-15T23:59:00Z'), '16 de novembro');
    });
});

void describe('local demonstration participation', () => {
    void it('adds a pending participation and updates only its campaign without mutating the original store', () => {
        const original = store();
        const before = structuredClone(original);

        const result = addDemoParticipation(original, 1, 3, 'Recolha própria');

        assert.deepEqual(original, before);
        assert.equal(result.campaigns[0].currentQuantity, 323);
        assert.equal(result.campaigns[0].participantCount, 19);
        assert.deepEqual(result.campaigns[1], before.campaigns[1]);
        assert.deepEqual(result.proposals, before.proposals);
        assert.equal(result.participations.length, 2);
        assert.equal(result.participations[0].id, 8);
        assert.equal(result.participations[0].campaignId, 1);
        assert.equal(result.participations[0].status, 'pendente');
        assert.equal(result.participations[0].quantity, 3);
        assert.equal(result.participations[0].notes, 'Recolha própria');
        assert.equal(result.participations[0].campaign.currentQuantity, 323);
        assert.deepEqual(result.participations[1], before.participations[0]);
    });

    void it('rejects zero, negative, fractional and non-finite quantities without changing the store', () => {
        for (const quantity of [
            0,
            -1,
            1.5,
            NaN,
            Infinity,
            Number.MAX_SAFE_INTEGER + 1,
        ]) {
            const original = store();
            const before = structuredClone(original);

            assert.throws(() =>
                addDemoParticipation(original, 1, quantity, ''),
            );

            assert.deepEqual(original, before);
        }
    });

    void it('rejects an unknown campaign, a closed campaign and an expired campaign', () => {
        assert.throws(() => addDemoParticipation(store(), 999, 1, ''));
        assert.throws(() =>
            addDemoParticipation(store({ status: 'concluida' }), 1, 1, ''),
        );
        assert.throws(() =>
            addDemoParticipation(
                store({ deadline: '2000-01-01T00:00:00Z' }),
                1,
                1,
                '',
            ),
        );
    });
});
