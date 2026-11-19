# Guia dos testes de validação do Junta

**Verificação:** 30 de Outubro de 2026. PHP 8.5, Laravel 13.38, Fortify, Inertia/React e Pest 5. Execute os comandos na raiz do projecto.

## Onde encontrar cada regra

| Tela                     | Campo no pedido | Ficheiro da regra                                                      | Método                                        | Ficheiro do teste unitário                                                    | Identificador          |
| ------------------------ | --------------- | ---------------------------------------------------------------------- | --------------------------------------------- | ----------------------------------------------------------------------------- | ---------------------- |
| Criar conta              | name            | [RegistrationValidation.php](app/Support/RegistrationValidation.php)   | RegistrationValidation::rules() e messages()  | [SelectedValidationRulesTest.php](tests/Unit/SelectedValidationRulesTest.php) | UT01, UT02             |
| Criar conta              | email           | [RegistrationValidation.php](app/Support/RegistrationValidation.php)   | RegistrationValidation::rules() e messages()  | [SelectedValidationRulesTest.php](tests/Unit/SelectedValidationRulesTest.php) | UT03                   |
| Participar numa campanha | quantity        | [ParticipationValidation.php](app/Support/ParticipationValidation.php) | ParticipationValidation::rules() e messages() | [SelectedValidationRulesTest.php](tests/Unit/SelectedValidationRulesTest.php) | UT04, UT05, UT06, UT07 |
| Preparar proposta        | unitPrice       | [ProposalValidation.php](app/Support/ProposalValidation.php)           | ProposalValidation::rules() e messages()      | [SelectedValidationRulesTest.php](tests/Unit/SelectedValidationRulesTest.php) | UT08, UT09, UT10       |

Os dez unitários usam o motor real de validação do Laravel, com uma Factory e um Translator construídos directamente. O código dos unitários não inicializa a aplicação Laravel nem usa a base de dados, navegador ou pedidos HTTP. O teste de email mantém o nome válido. UT07 usa o número decimal 1.5; a integração verifica também as strings "1.5" e "1,5", sem truncar.

## Executar exactamente os dez unitários

```powershell
php artisan test --compact tests/Unit/SelectedValidationRulesTest.php
```

**Resultado observado:** 10 aprovados, 16 asserções.

## Ligação ao servidor e às telas

- **Conta:** [CreateNewUser.php](app/Actions/Fortify/CreateNewUser.php), método create(), chama as regras de [ProfileValidationRules.php](app/Concerns/ProfileValidationRules.php), que reutilizam RegistrationValidation. A unicidade do email e as regras de palavra-passe existentes foram preservadas.
- **Participação:** [ParticipationRequest.php](app/Http/Requests/ParticipationRequest.php), métodos rules()/messages(), é validado antes de ParticipationController::store(). O formulário envia a quantidade original como string; a conversão não antecede a validação.
- **Proposta:** [ProposalRequest.php](app/Http/Requests/ProposalRequest.php), métodos rules()/messages(), é validado antes de ProposalController::store(). Mantém preço em meticais, ponto decimal, até duas casas decimais e os limites existentes.
- **Interface:** [register.tsx](resources/js/pages/auth/register.tsx), [ParticipationFlow.tsx](resources/js/components/junta/ParticipationFlow.tsx) e [criar.tsx](resources/js/pages/fornecedor/propostas/criar.tsx).
- **Feedback local:** [selected-form-validation.ts](resources/js/lib/selected-form-validation.ts), funções quantityError(), priceError() e proposalReviewErrors(). São auxiliares de experiência; a decisão definitiva pertence ao servidor.
- **Erros do servidor:** [server-mutation.ts](resources/js/lib/server-mutation.ts), classe ValidationFailure, preserva os nomes dos campos. Falhas de rede usam erro geral, separado dos erros de validação.

As três operações já tinham persistência real. Essa persistência foi preservada; não se substituíram gravações por validações simuladas. As confirmações de participação/proposta só aparecem após resposta bem-sucedida do servidor.

Os formulários usam noValidate para permitir mensagens personalizadas, aria-invalid e aria-describedby. Os erros aparecem em vermelho imediatamente abaixo do campo, com borda vermelha e preservação do valor. Após corrigir e voltar a validar, os erros são retirados.

## Verificações separadas dos unitários

```powershell
php artisan test --compact tests/Feature/SelectedValidationIntegrationTest.php
```

Há quatro testes de integração: mensagens de registo/espaços/palavra-passe; quantidades inválidas e gravação corrigida; preços inválidos e gravação com duas casas decimais; preservação da unicidade de email. Estes usam a base SQLite em memória configurada para os testes, com factories.

Conjunto afectado e regressões verificadas:

```powershell
php artisan test --compact tests/Unit/SelectedValidationRulesTest.php tests/Feature/SelectedValidationIntegrationTest.php tests/Feature/CommercePermissionsTest.php tests/Feature/AdministrationTest.php tests/Feature/Settings/ProfileUpdateTest.php
```

**Resultado observado:** 35 aprovados, 197 asserções. TypeScript, lint, compilação, Pint e PHPStan/Larastan também passaram.

Foram ainda aprovadas **19 verificações no navegador**, separadas dos dez unitários: mensagens reais de nome/email, erros locais e de servidor para quantidade/preço, cor, atributos acessíveis, manutenção dos valores, correcção, falhas de rede nas três telas e sucesso apenas após resposta do servidor. A injecção de valores inválidos nos pedidos serviu para exercitar a validação real; não foram simuladas respostas de sucesso.

Evidência: [selected-validation-browser.json](storage/app/selected-validation-browser.json). As operações do navegador usaram exclusivamente uma base SQLite temporária; os dados locais de utilização não foram alterados.

Para executar a suite completa do projecto:

```powershell
php artisan test --compact
```

## TDD: o que foi feito e o que já existia

As regras básicas de nome obrigatório, email válido, inteiro positivo e preço positivo já existiam. Foram reutilizadas e extraídas para módulos pequenos; não se declara TDD retroactivo para essas regras.

Para corrigir as mensagens e o contrato dos campos, escreveu-se primeiro SelectedValidationIntegrationTest.php. Antes da implementação, **3 testes falharam e 1 passou**, pelos motivos esperados: mensagens antigas, incluindo as mensagens padrão em inglês. Depois da correcção, os quatro passaram. O teste existente de unicidade já passava e continuou a passar.

Registos: [fase vermelha](storage/app/selected-validation-red.json), [fase verde inicial](storage/app/selected-validation-green.json), [verificação final](storage/app/selected-validation-tests.json).

## Cobertura das regras seleccionadas

A configuração [phpunit.validations.xml](phpunit.validations.xml) limita a cobertura aos **três ficheiros de regras da tabela**, não à aplicação inteira nem ao código interno de validação do Laravel.

Com Xdebug instalado e carregado no PHP da linha de comandos:

```powershell
php -d xdebug.mode=coverage vendor/bin/pest --configuration=phpunit.validations.xml --coverage --min=80
```

A configuração prepara estes relatórios:

- HTML: storage/app/coverage-validations/html/index.html
- Clover: storage/app/coverage-validations/clover.xml
- Texto: storage/app/coverage-validations/coverage.txt

**Resultado real desta tentativa:** "No code coverage driver is available." O PHP disponível não tem Xdebug nem PCOV carregado. Por isso, os relatórios com percentagens não puderam ser gerados e a meta de 80% não pôde ser verificada.

| Módulo seleccionado         | Cobertura de linhas medida |
| --------------------------- | -------------------------- |
| RegistrationValidation.php  | N/D — driver indisponível  |
| ParticipationValidation.php | N/D — driver indisponível  |
| ProposalValidation.php      | N/D — driver indisponível  |
| Meta de 80%                 | Não verificada             |

O facto de os testes passarem não foi convertido numa percentagem de cobertura. É necessário habilitar um driver compatível para executar a medição. Não foram instaladas novas dependências.

Evidência da tentativa: [selected-validation-coverage.log](storage/app/selected-validation-coverage.log).
