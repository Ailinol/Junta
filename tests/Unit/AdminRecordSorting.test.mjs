import assert from 'node:assert/strict';
import { it } from 'node:test';
import {
    canSortAdminColumn,
    sortAdminRecords,
} from '../../resources/js/lib/admin-record-sorting.ts';

void it('orders prices by their numeric value rather than formatted currency', () => {
    const records = [
        { unitPrice: 100.2 },
        { unitPrice: 100.09 },
        { unitPrice: 100.1 },
    ];

    const result = sortAdminRecords(records, 'admin/propostas/index', {
        column: 'Preço unitário',
        direction: 'asc',
    });

    assert.deepEqual(
        result.map((row) => row.unitPrice),
        [100.09, 100.1, 100.2],
    );
});

void it('orders dates chronologically and keeps missing or invalid dates last in either direction', () => {
    const records = [
        { id: 1, createdAt: null },
        { id: 2, createdAt: '2026-02-01T00:00:00Z' },
        { id: 3, createdAt: '2025-12-31T22:00:00Z' },
        { id: 4, createdAt: 'invalid' },
    ];

    const ids = (direction) =>
        sortAdminRecords(records, 'admin/candidaturas', {
            column: 'Enviada',
            direction,
        }).map((row) => row.id);

    assert.deepEqual(ids('asc'), [3, 2, 1, 4]);
    assert.deepEqual(ids('desc'), [2, 3, 1, 4]);
});

void it('orders Portuguese names naturally and preserves the order of equal values', () => {
    const records = [
        { id: 1, fullName: 'Zena' },
        { id: 2, fullName: 'Álvaro' },
        { id: 3, fullName: 'alvaro' },
        { id: 4, fullName: 'Beatriz' },
    ];

    const result = sortAdminRecords(records, 'admin/candidaturas', {
        column: 'Candidato',
        direction: 'asc',
    });

    assert.deepEqual(
        result.map((row) => row.id),
        [2, 3, 4, 1],
    );
});

void it('restores the received order without mutating records and limits sorting to known columns', () => {
    const records = Object.freeze([
        { id: 2, name: 'Zena' },
        { id: 1, name: 'Ana' },
    ]);

    sortAdminRecords(records, 'admin/utilizadores/index', {
        column: 'Nome',
        direction: 'asc',
    });

    assert.deepEqual(
        sortAdminRecords(records, 'admin/utilizadores/index', null),
        records,
    );
    assert.equal(canSortAdminColumn('admin/historico', 'Registo'), false);
    assert.equal(
        canSortAdminColumn('consumidor/campanhas/index', 'Campanha'),
        false,
    );
    assert.deepEqual(
        sortAdminRecords(records, 'admin/utilizadores/index', {
            column: 'Acessos',
            direction: 'asc',
        }),
        records,
    );
});

void it('orders proposal campaign titles without confusing supplier names with the campaign', () => {
    const records = [
        { id: 1, campaign: { title: 'Óleo' } },
        { id: 2, campaign: { title: 'Arroz' } },
    ];

    const result = sortAdminRecords(records, 'admin/propostas/index', {
        column: 'Campanha',
        direction: 'asc',
    });

    assert.deepEqual(
        result.map((row) => row.id),
        [2, 1],
    );
});
