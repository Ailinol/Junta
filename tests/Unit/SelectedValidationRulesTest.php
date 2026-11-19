<?php

use App\Support\ParticipationValidation;
use App\Support\ProposalValidation;
use App\Support\RegistrationValidation;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

beforeEach(function () {
    $this->validation = new Factory(new Translator(new ArrayLoader, 'pt'));
});

test('UT01 — Nome Ana é válido', function () {
    $validator = $this->validation->make(['name' => 'Ana', 'email' => 'ana@example.test'], RegistrationValidation::rules(), RegistrationValidation::messages());
    expect($validator->passes())->toBeTrue();
});
test('UT02 — Nome vazio é inválido', function () {
    $validator = $this->validation->make(['name' => '', 'email' => 'ana@example.test'], RegistrationValidation::rules(), RegistrationValidation::messages());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toBe('Informe o seu nome.');
});
test('UT03 — Email ana@ é inválido com os restantes dados válidos', function () {
    $validator = $this->validation->make(['name' => 'Ana', 'email' => 'ana@'], RegistrationValidation::rules(), RegistrationValidation::messages());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('email'))->toBe('Introduza um email válido.');
    expect($validator->errors()->has('name'))->toBeFalse();
});
test('UT04 — Quantidade 1 é válida', function () {
    $validator = $this->validation->make(['quantity' => 1], ParticipationValidation::rules(), ParticipationValidation::messages());
    expect($validator->passes())->toBeTrue();
});
test('UT05 — Quantidade zero é inválida', function () {
    $validator = $this->validation->make(['quantity' => 0], ParticipationValidation::rules(), ParticipationValidation::messages());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('quantity'))->toBe('A quantidade deve ser um número inteiro maior que zero.');
});
test('UT06 — Quantidade negativa é inválida', function () {
    $validator = $this->validation->make(['quantity' => -1], ParticipationValidation::rules(), ParticipationValidation::messages());
    expect($validator->fails())->toBeTrue();
});
test('UT07 — Quantidade decimal 1,5 é inválida', function () {
    $validator = $this->validation->make(['quantity' => 1.5], ParticipationValidation::rules(), ParticipationValidation::messages());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('quantity'))->toBe('A quantidade deve ser um número inteiro maior que zero.');
});
test('UT08 — Preço 800 é válido', function () {
    $validator = $this->validation->make(['unitPrice' => 800], ProposalValidation::rules(), ProposalValidation::messages());
    expect($validator->passes())->toBeTrue();
});
test('UT09 — Preço zero é inválido', function () {
    $validator = $this->validation->make(['unitPrice' => 0], ProposalValidation::rules(), ProposalValidation::messages());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('unitPrice'))->toBe('O preço deve ser um número maior que zero.');
});
test('UT10 — Preço negativo é inválido', function () {
    $validator = $this->validation->make(['unitPrice' => -1], ProposalValidation::rules(), ProposalValidation::messages());
    expect($validator->fails())->toBeTrue();
});
