<?php

test('redirects guests over https when the request comes from a configured proxy', function () {
    config(['trustedproxy.proxies' => '10.20.0.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.10'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://junta.test/admin/dashboard')
        ->assertRedirect('https://junta.test/login');
});

test('ignores forwarded https from an untrusted address', function () {
    config(['trustedproxy.proxies' => '10.20.0.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.11'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://junta.test/admin/dashboard')
        ->assertRedirect('http://junta.test/login');
});

test('does not use forwarded host or port to generate authentication links', function () {
    config(['trustedproxy.proxies' => '10.20.0.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.0.10'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'attacker.example',
            'X-Forwarded-Port' => '4444',
        ])
        ->get('http://junta.test/admin/dashboard')
        ->assertRedirect('https://junta.test/login');
});
