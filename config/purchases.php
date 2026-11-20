<?php

return [
    'provider' => env('PURCHASE_PAYMENT_PROVIDER', 'disabled'),
    'test_enabled' => env('PURCHASE_TEST_ENABLED', false),
    'test_webhook_secret' => env('PURCHASE_TEST_WEBHOOK_SECRET'),
    'default_commission_percent' => env('PURCHASE_COMMISSION_PERCENT', '2.50'),
    'terms_version' => 'pilot-2026-11-v1',
    'uncertain_escalation_minutes' => (int) env('PURCHASE_UNCERTAIN_ESCALATION_MINUTES', 30),
    'lookup_before_deadline_seconds' => 300,
    'lookup_after_deadline_seconds' => 120,
    'lookup_urgent_seconds' => 60,
    'urgent_after_minutes' => 10,
    'terms' => 'A compra depende do mínimo de unidades pagas. Se o mínimo falhar, o pedido cobrado é reembolsado integralmente, incluindo comissão e transporte. A recolha ou entrega exige o código de uso único. Problemas podem ser reportados no acompanhamento.',
];
