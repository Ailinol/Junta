# Relatório de testes do Junta

Período: 19 a 20 de novembro de 2026. Referência inicial: `53be2ad`, branch
`railway-deploy`. Plano associado: [PLANO-DE-TESTES.md](PLANO-DE-TESTES.md).
As correções descritas neste relatório estão no workspace local; não foram
publicadas no GitHub nem aplicadas ao Railway nesta auditoria.

## 1. Conclusão

A regressão final aprovou 333 testes PHP e 54 testes JavaScript; três testes
PHP foram ignorados. A linha de base tinha 328 testes PHP aprovados.
Foram identificados quatro defeitos: um erro funcional
na eliminação de contas com histórico, duas lacunas na execução automática
do CI e uma falha de formatação que bloqueava o controlo de qualidade.

O erro funcional foi reproduzido em quatro casos novos antes da correção.
Depois da correção, os dez testes de perfil passaram, incluindo os casos
que preservam a conta, a sessão e o histórico. Foram adicionados cinco casos
de regressão no total. As alterações não mudam os cálculos nem as regras de
pagamento, não alteram migrations e não apagam dados existentes.

Não se declara homologação visual, compatibilidade MySQL completa, cobertura
percentual nem execução bem-sucedida dos workflows no GitHub: esses resultados
não foram medidos nesta execução.

## 2. Ambiente e método

| Item                      | Verificado                                                                                       |
| ------------------------- | ------------------------------------------------------------------------------------------------ |
| Sistema local             | Windows / PowerShell                                                                             |
| PHP                       | 8.5.11                                                                                           |
| Laravel                   | 13.38.0                                                                                          |
| Pest / plugin Laravel     | 5.3.3 / 5.0.1                                                                                    |
| Fortify / Inertia Laravel | 1.41.0 / 3.5.1                                                                                   |
| Larastan / Pint           | 3.13.0 / 1.32.1                                                                                  |
| Node                      | 24.20.0                                                                                          |
| Base dos testes PHP       | SQLite `:memory:`; o teste de concorrência usa um ficheiro SQLite temporário próprio             |
| Integrações               | OpenAI desativado no processo de teste; HTTP simulado nos testes de integração; correio em array |
| Dados publicados          | MySQL do Railway preservado; nenhum teste destrutivo ou migração de produção executado           |

Foram revistos controladores, validações, modelos, restrições de integridade,
testes existentes e scripts de CI. A execução começou com a suíte intacta;
depois foram acrescentadas regressões antes de alterar o controlador.

## 3. Resultados executados

| Execução                                           | Resultado                                                          | Observação                                                                      |
| -------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| Linha de base: `php artisan test --compact`        | 331 casos: 328 aprovados, 3 ignorados, 0 falhados; 2 816 asserções | 151,588 s                                                                       |
| Linha de base: `node --test tests/Unit/*.test.mjs` | 54 aprovados, 0 falhados ou ignorados                              | 21,649 s                                                                        |
| Linha de base: `npm run types:check`               | Aprovado                                                           | TypeScript sem erros                                                            |
| Linha de base: análise estática completa           | Aprovada, 0 erros                                                  | Processo local com 512 MB e `--debug`                                           |
| Linha de base: `npm run check`                     | Reprovado                                                          | Formatação em seis ficheiros; BUG-004                                           |
| TDD Red: testes de perfil com regressões novas     | 4 falhados, 6 aprovados; 32 asserções                              | Falha esperada: HTTP 500 por chave estrangeira; 5,58 s                          |
| TDD Green: os mesmos testes após correção          | 10 aprovados; 70 asserções                                         | 4,32 s                                                                          |
| Análise estática do controlador corrigido          | Aprovada                                                           | Sem erros                                                                       |
| Verificações frontend finais e Compose             | Aprovados: 54 testes Node; tipos, lint e formatação sem erros      | Ambos os Compose válidos; configurações equivalentes antes/depois da formatação |
| Build Vite final                                   | Aprovado: 2 385 módulos; manifesto e assets gerados                | Cerca de 71 s; aviso de tempo de plugins, sem falha                             |
| Regressão PHP completa final                       | 336 casos: 333 aprovados, 3 ignorados, 0 falhados; 2 865 asserções | 87,37 s; motivos dos skips confirmados com `--display-skipped`                  |

As durações são observações deste computador, não benchmarks da aplicação.
O crescimento das asserções no Green ocorre porque a resposta correta permite
executar as verificações de sessão e preservação que antes não eram alcançadas.

### Testes ignorados

Os três casos ignorados pertencem a `Auth/AuthenticationTest.php` e
`Settings/SecurityTest.php`. São condicionados por
`Features::twoFactorAuthentication()`, funcionalidade desativada na aplicação.
Dois são cenários de 2FA. O terceiro chama-se
`security page renders without two factor when feature is disabled` e também
é ignorado pela mesma condição: constitui uma lacuna de cobertura, descrita
em MEL-001. Ignorado não significa aprovado.

## 4. Bugs encontrados

### BUG-001 — Eliminar conta com histórico provoca HTTP 500 e termina a sessão

- **Prioridade:** P1, operação de gestão da conta indisponível para utilizadores
  com histórico. Não foi demonstrada perda de dados: a restrição da base
  impede a eliminação.
- **Estado:** reproduzido e corrigido localmente; regressões aprovadas.
- **Área:** `app/Http/Controllers/Settings/ProfileController.php`, método `destroy`.
- **Condição inicial:** utilizador verificado com pedido associado, ou autor de
  uma ocorrência num pedido de outra pessoa.
- **Passos:** autenticar; abrir definições do perfil; pedir eliminação com a
  password correta; confirmar.
- **Esperado:** manter os registos protegidos, devolver uma explicação legível
  no formulário e conservar a sessão quando a operação é recusada.
- **Observado antes:** HTTP 500 com `SQLSTATE[23000]` e
  `FOREIGN KEY constraint failed`; o controlador chamava `Auth::logout()`
  antes de executar a eliminação que falhava.
- **Causa:** `purchase_orders.user_id` e `purchase_issues.user_id` têm
  `restrictOnDelete()`. O controlador não tratava essa restrição já existente.
- **Correção:** transação com bloqueio do utilizador; verificação do histórico
  de pedidos/ocorrências; erro de validação visível no formulário. Logout e
  invalidação da sessão apenas depois de uma eliminação bem-sucedida.
- **Proteção de dados:** as restrições permanecem; nenhum cascade adicional,
  anonimização, soft delete ou migration foi introduzido.
- **Evidência:** quatro casos Red falharam com HTTP 500; os mesmos casos
  passaram no Green. A ausência de histórico próprio continua a permitir a
  eliminação sem afetar pedidos de terceiros.

Regressões em `tests/Feature/Settings/ProfileUpdateTest.php`:

| Caso novo                                       | Antes    | Depois   | Contrato protegido                                    |
| ----------------------------------------------- | -------- | -------- | ----------------------------------------------------- |
| Pedido a aguardar pagamento                     | HTTP 500 | Aprovado | Conta, pedido e sessão preservados                    |
| Pedido reembolsado                              | HTTP 500 | Aprovado | Histórico continua protegido após o reembolso         |
| Ocorrência aberta num pedido de outra pessoa    | HTTP 500 | Aprovado | Autor e ocorrência preservados                        |
| Ocorrência resolvida num pedido de outra pessoa | HTTP 500 | Aprovado | Resolução não elimina a ligação histórica             |
| Só existe pedido de outra pessoa                | Aprovado | Aprovado | Verificação não bloqueia contas sem histórico próprio |

### BUG-002 — Os testes JavaScript não eram executados pelo CI habitual

- **Prioridade:** P2, regressões frontend podiam passar sem serem verificadas.
- **Estado:** corrigido localmente; os 54 testes passaram pelo novo comando.
- **Reprodução:** executar/inspecionar `composer ci:check` e os scripts originais.
  Havia testes `.test.mjs`, mas nenhum comando de testes Node na sequência.
- **Esperado:** executar a suíte existente de JavaScript no controlo de CI.
- **Observado antes:** formatação, TypeScript e PHP eram executados; os 54
  testes Node ficavam fora desse fluxo.
- **Correção:** `npm run test:unit` em `package.json`, incluído em
  `composer.json` → `ci:check`.
- **Limite da evidência:** o comando foi preparado e executado localmente;
  não foi disparada uma execução remota do GitHub Actions nesta auditoria.

### BUG-003 — Push no branch de deploy não iniciava o workflow de testes

- **Prioridade:** P2, branch publicado sem a mesma verificação automática de `main`.
- **Estado:** configuração corrigida localmente; validação remota pendente.
- **Reprodução:** comparar o branch usado no deploy, `railway-deploy`, com
  `.github/workflows/tests.yml` → `on.push.branches`.
- **Esperado:** push nesse branch inicia os testes de CI.
- **Observado antes:** apenas `main` e pull requests iniciavam esse workflow.
- **Correção:** acrescentado `railway-deploy` aos branches de push.
- **Limite:** isto não ativa a opção Railway de esperar pelo CI. A política
  de bloquear deploy por checks falhados continua a exigir configuração e
  validação operacional separadas.

### BUG-004 — Controlo de qualidade reprovava por formatação

- **Prioridade:** P2, `npm run check` impedia a aprovação do estado inicial.
- **Estado:** corrigido e validado localmente; `npm run check` aprovado.
- **Reprodução:** executar `npm run check` no commit de referência.
- **Esperado:** nenhum erro de formatação.
- **Observado antes:** seis ficheiros assinalados:
  `AUDITORIA-PRODUCAO.md`, `DEPLOY-PRODUCAO.md`, `DEPLOY-RAILWAY.md`,
  `FLUXO-ACTUAL-DO-PROJECTO.md`, `docker-compose.demo.yml` e `docker-compose.yml`.
- **Correção:** formatador já instalado aplicado apenas a esses ficheiros,
  aos documentos novos e ao manifesto alterado. Não foram substituídos
  serviços, volumes, credenciais ou comandos de deploy.
- **Validação:** `npm run check` aprovado; ambos os Compose validados com
  `config --quiet`, usando o ficheiro de exemplo e valores fictícios, sem
  iniciar containers. As configurações normalizadas antes/depois da formatação
  foram comparadas em memória e são equivalentes nos dois modos.

## 5. Ciclo TDD aplicado

1. **Red:** adicionados cinco casos ao teste de perfil, sem alterar o
   controlador. Quatro reproduziram a falha de chave estrangeira; o controlo
   de isolamento entre utilizadores já passou.
2. **Green:** ajustada a eliminação para respeitar as restrições antes do
   logout. Os dez testes do ficheiro passaram com 70 asserções.
3. **Refactor e verificação:** mantida a estrutura existente; usados modelos,
   factories, validação e transação Laravel; Pint e análise estática aprovados
   para a alteração.
4. **Regressão:** execução final registada na tabela de resultados.

Não foi necessário modificar o frontend: o formulário já apresenta
`errors.password`, onde é enviada a explicação da recusa. Não foram criados
testes para repetir a implementação linha a linha nem foram eliminados
testes existentes. As correções de scripts e formatação foram verificadas
com os comandos reais; não são apresentadas como TDD de comportamento.

## 6. Melhorias e lacunas pendentes

| ID      | Prioridade                       | Melhoria                                                         | Evidência e critério de aceitação                                                                                                                                                                                           |
| ------- | -------------------------------- | ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| MEL-001 | P2                               | Cobrir a página de segurança com a configuração real sem 2FA     | O teste de funcionalidade desativada é ignorado; rever também as expectativas antigas de props 2FA face ao contrato atual `passwordRules`. Não foi alterado um teste existente para o fazer passar                          |
| MEL-002 | P1 antes de homologação integral | Repetir integridade e concorrência num MySQL isolado             | A suíte atual usa SQLite, incluindo o teste com dois processos; confirmar limites, locks, chaves estrangeiras e transações em MySQL 8.4 sem usar a base publicada                                                           |
| MEL-003 | P2                               | Aceitação visual em navegador real                               | Testes PHP HTTP e renderização Node não demonstram interação de browser, navegação Inertia completa, foco ou responsividade; executar UX-01 a UX-03                                                                         |
| MEL-004 | P2                               | Smoke test após publicação e política de espera por CI           | Validar `/up`, login, assets e leitura de campanhas na versão publicada; confirmar que o Railway aguarda checks obrigatórios                                                                                                |
| MEL-005 | P2                               | Medir cobertura e, seletivamente, qualidade das regressões       | Não foi recolhida percentagem de cobertura nem executada análise de mutação; não confundir quantidade de testes com cobertura funcional completa                                                                            |
| MEL-006 | P2                               | Rever retenção/anonimização e comunicação da eliminação de conta | O histórico protegido impede a eliminação física; esclarecer o procedimento administrativo e a mensagem do produto antes de definir uma política nova                                                                       |
| MEL-007 | P2                               | Validar limites monetários de ponta a ponta                      | Na revisão, os máximos declarados em validações de preços são superiores ao formato aceite por `Money::cents`. Não foi reproduzido o fluxo completo nesta auditoria; tratar como hipótese a testar, não como bug confirmado |

MEL-002 não significa contratar outro serviço: pode usar um MySQL isolado
local ou recursos já disponíveis. Nenhuma dependência de teste nova foi
instalada e nenhum recurso pago foi criado.

## 7. Limitações e rastreabilidade

- Os resultados são locais e relativos ao commit de referência mais o diff
  desta auditoria. Não provam que o Railway já tenha recebido as correções.
- Nenhuma conta publicada foi eliminada para reproduzir o bug; os casos usam
  factories e base descartável.
- Não foram executados testes de carga, penetração, leitor de ecrã, browsers
  múltiplos, serviços financeiros reais ou envio real de email.
- O modo de demonstração continua separado de uma operação comercial.
- O executor Pao apresentou bloqueio ao formatar falhas esperadas. A fase
  Red foi repetida com `PAO_DISABLE=true` e `APP_DEBUG=false`, sem desativar
  middleware ou o tratamento normal de exceções Laravel.
- A análise estática local foi executada com `--debug` e 512 MB para evitar
  o worker paralelo/limite de memória deste Windows. Não se alterou a
  configuração global do PHP nem os recursos do Railway.

Referências para os contratos usados nos testes e correção:
[testes HTTP Laravel 13](https://laravel.com/framework/docs/13.x/http-tests),
[validação Laravel 13](https://laravel.com/framework/docs/13.x/validation).
Os resultados numéricos acima provêm das execuções locais, não da documentação.
