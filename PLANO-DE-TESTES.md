# Plano de testes do Junta

Atualização: 20 de novembro de 2026. Versão de referência: commit `53be2ad`, branch
`railway-deploy`. Execução e resultados: [RELATORIO-DE-TESTES.md](RELATORIO-DE-TESTES.md).

## 1. Objetivo e âmbito

Verificar os acessos, a integridade das compras coletivas e a capacidade de
publicar a demonstração Laravel/React/Inertia com MySQL. Encontrar defeitos
reproduzíveis, proteger as correções com testes e separar os resultados
executados dos testes ainda planeados.

Inclui autenticação, permissões, fornecedores, candidaturas, campanhas,
participações, propostas, pagamentos simulados, fornecimento, recolha,
reembolsos, relatórios, dados de demonstração e configuração de deploy.

Não inclui certificação de segurança, dinheiro real, serviços pagos,
alterações de regras comerciais nem testes destrutivos em produção.

## 2. Ambientes e dados

| Ambiente                | Uso                                                     | Proteção                                                                     |
| ----------------------- | ------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Local automatizado      | Pest, Node, tipos, análise estática e build             | `APP_ENV=testing`, SQLite em memória, correio em array e pagamentos de teste |
| MySQL isolado           | Compatibilidade SQL, concorrência, locks e persistência | Base exclusiva de testes; nunca indicar a base do Railway ao Pest            |
| Railway de demonstração | Smoke test e aceitação manual                           | Apenas dados `[DEMO]`; credenciais privadas; sem apagar contas ou histórico  |

Usar factories nos testes e tempo controlado nos cenários com prazos. Simular
HTTP externo nos testes de integrações. Não copiar passwords, cookies,
tokens, ficheiros `.env` nem dumps para os relatórios ou para o Git.

Perfis de aceitação: administrador, organizador, fornecedor aprovado,
fornecedor pendente, consumidor e candidato a organizador pendente/rejeitado.
As credenciais ficam exclusivamente no ficheiro privado de acessos.

SQLite permite regressões rápidas; não substitui a validação de locks,
concorrência, tipos e restrições no MySQL usado em produção.

## 3. Estratégia e prioridades

1. Executar a suíte existente antes de alterar código e registar a linha de base.
2. Priorizar autorização, preservação de histórico, valores monetários,
   duplicação de cobranças e isolamento entre utilizadores.
3. Reproduzir cada defeito novo num teste que falhe pelo motivo esperado.
4. Aplicar a menor correção compatível com as regras existentes e executar
   primeiro o teste afetado.
5. Executar regressão, verificações de tipos, formatação e build.
6. Fazer aceitação manual dos fluxos visuais antes da publicação das correções.

Prioridades: P1 impede uma operação importante ou afeta integridade/acesso;
P2 afeta qualidade, automação ou uma alternativa de utilização; P3 é melhoria
de manutenção ou apresentação. A prioridade não equivale a perda de dados
comprovada.

## 4. Matriz funcional e rastreabilidade

`A` identifica cobertura automatizada existente a executar; `M` identifica
aceitação manual planeada. A presença na matriz não significa aprovação:
consultar os resultados e limitações do relatório.

| ID     | Prioridade | Cenário e resultado esperado                                                                     | Tipo / referência                                                                   |
| ------ | ---------- | ------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| AUT-01 | P1         | Login válido cria sessão; password incorreta não autentica                                       | A: `Auth/AuthenticationTest.php`                                                    |
| AUT-02 | P1         | Tentativas excessivas de login são limitadas                                                     | A: `Auth/AuthenticationTest.php`                                                    |
| AUT-03 | P1         | Link válido verifica email; assinatura ou utilizador incorreto é recusado                        | A: `Auth/EmailVerificationTest.php`                                                 |
| AUT-04 | P1         | Reset válido altera a password; token inválido não altera a conta                                | A: `Auth/PasswordResetTest.php`                                                     |
| AUT-05 | P1         | Alterar password exige a atual e confirmação correta                                             | A: `Settings/SecurityTest.php`                                                      |
| AUT-06 | P1         | Conta com compras/ocorrências recebe explicação ao tentar eliminar; histórico e sessão mantêm-se | A: regressões em `Settings/ProfileUpdateTest.php`                                   |
| AUT-07 | P1         | Conta sem histórico pode ser eliminada; não afeta compras de terceiros                           | A: `Settings/ProfileUpdateTest.php`                                                 |
| ACE-01 | P1         | Consumidor não entra nas áreas de administrador, organizador ou fornecedor                       | A: `MainApplicationAccessTest.php`, `AdminSupervisionTest.php`                      |
| ACE-02 | P1         | Utilizador não consulta nem altera pedidos/campanhas privados de outra conta                     | A: `PurchaseConsistencyTest.php`, `OrganizerConsoleTest.php`                        |
| FOR-01 | P1         | Adesão comercial nasce pendente e não permite autoaprovação                                      | A: `SupplierCommitmentTest.php`                                                     |
| FOR-02 | P1         | Fornecedor não verificado não apresenta proposta                                                 | A: `SupplierCommitmentTest.php`, `ProposalConsistencyTest.php`                      |
| ORG-01 | P1         | Aprovação/rejeição de candidatura exige administração e preserva a decisão                       | A: `AdministrationTest.php`, `AdminSupervisionTest.php`                             |
| CAM-01 | P1         | Criar/publicar campanha exige perfil autorizado e dados válidos                                  | A: `CampaignManagementRegressionTest.php`                                           |
| CAM-02 | P1         | Condições ficam protegidas depois de participações/propostas                                     | A: `CampaignManagementRegressionTest.php`                                           |
| CAM-03 | P1         | Participação exige inteiro positivo e fase/prazo abertos                                         | A: `SelectedValidationIntegrationTest.php`, `PurchaseConsistencyTest.php`           |
| CAM-04 | P1         | Cancelamento atualiza participações/propostas sem apagar o histórico                             | A: `OrganizerConsoleTest.php`                                                       |
| PRO-01 | P1         | Proposta exige fornecedor aprovado, preço e condições comerciais válidos                         | A: `ProposalConsistencyTest.php`, `SupplierCommitmentTest.php`                      |
| PRO-02 | P1         | Não há duas propostas ativas do mesmo fornecedor na mesma campanha                               | A: `ProposalConsistencyTest.php`                                                    |
| PRO-03 | P1         | Proposta expirada não é selecionada; apenas uma fica aceite                                      | A: `ProposalConsistencyTest.php`                                                    |
| PAG-01 | P1         | Pedido fixa condições; não aceita preços/totais manipulados pelo cliente                         | A: `PurchaseConsistencyTest.php`, `PurchaseDemonstrationTest.php`                   |
| PAG-02 | P1         | Repetir pagamento/reconciliação não duplica cobrança nem movimentos                              | A: `PaymentRecoveryTest.php`, `PaymentOperationsTest.php`                           |
| PAG-03 | P1         | Duas compras concorrentes não excedem a capacidade reservada                                     | A: `Unit/PurchaseConcurrencyTest.php`; repetir separadamente em MySQL isolado       |
| PAG-04 | P1         | Pagamento pendente, falhado ou tardio segue o estado correto                                     | A: `PaymentRecoveryTest.php`, `PaymentOperationsTest.php`                           |
| PAG-05 | P1         | Mínimo não atingido/cancelamento origina reembolso integral e rastreável                         | A: `PurchaseDemonstrationTest.php`, `PurchaseConsistencyTest.php`                   |
| ENT-01 | P1         | Receção, separação e entrega respeitam perfil, estado e código de recolha                        | A: `PurchaseDemonstrationTest.php`                                                  |
| FIN-01 | P1         | Liquidação e encerramento exigem administração e valores coerentes                               | A: `PaymentOperationsTest.php`, `AdminSupervisionTest.php`                          |
| REL-01 | P2         | Relatórios distinguem estimativas de receita e usam calendário de Maputo                         | A: `Unit/AdminReporting.test.mjs`                                                   |
| REL-02 | P2         | Ordenação usa números/datas reais e mantém inválidos no fim                                      | A: `Unit/AdminRecordSorting.test.mjs`                                               |
| DEM-01 | P1         | Seeder exige modo demo e pagamentos de teste; recusa conflitos                                   | A: `HostedDemoSeederTest.php`                                                       |
| DEM-02 | P1         | Repetir seeder não altera passwords, permissões, decisões ou progresso                           | A: `HostedDemoSeederTest.php`                                                       |
| INF-01 | P1         | Proxy HTTPS gera URLs/cookies corretos sem confiar em host forjado                               | A: `ProductionProxyTest.php`                                                        |
| INF-02 | P1         | Pagamentos externos/IA não são usados quando desativados                                         | A: `ProductionDemonstrationTest.php`, `OpenAiIntegrationTest.php` com HTTP simulado |
| INF-03 | P1         | Build gera manifesto Vite e assets; aplicação responde em `/up`                                  | A: build; M: smoke test do deployment                                               |
| INF-04 | P1         | Reiniciar/republicar preserva MySQL, armazenamento e sessões válidas                             | M: demonstração, depois de backup e com autorização operacional                     |
| UX-01  | P2         | Login, formulários e erros funcionam no telemóvel e desktop                                      | M: 360, 768 e 1440 px                                                               |
| UX-02  | P2         | Navegação por teclado, foco, labels e erros são acessíveis                                       | M: teclado e leitor de ecrã                                                         |
| UX-03  | P2         | Pedidos apresentam uma ação útil por estado; loading/empty/error são compreensíveis              | A: testes Node de apresentação; M: navegador real                                   |

Os ficheiros PHP sem prefixo estão em `tests/Feature/`; os de Node estão em
`tests/Unit/`. A matriz agrupa cobertura por domínio; consultar cada teste
para conhecer as combinações efetivamente verificadas.

## 5. Roteiros de aceitação manual

### Consumidor

1. Entrar com perfil de consumidor e consultar campanhas e os próprios pedidos.
2. Abrir campanha em interesse; participar com quantidade válida.
3. Tentar zero, valor negativo e fracionário; confirmar erro junto ao campo.
4. Usar uma campanha preparada para pagamentos, confirmar condições e pagar
   exclusivamente no simulador; verificar o resultado e o acompanhamento.
5. Conferir pedido pronto para recolha, concluído e reembolsado.
6. Tentar entrar diretamente em áreas administrativas; confirmar recusa.

### Organizador e fornecedor

1. Organizador cria rascunho, publica, fecha interesse e compara propostas.
2. Seleciona proposta válida e abre pagamentos sem alterar condições fixadas.
3. Fornecedor aprovado consulta oportunidades e apresenta uma proposta.
4. Fornecedor pendente recebe explicação e não consegue autoaprovar-se.
5. Organizador acompanha mínimo, fornecimento, receção, separação e entrega.
6. Usar campanhas de teste separadas para não invalidar os outros roteiros.

### Administrador

1. Consultar campanhas, fornecedores, candidaturas, compras e auditoria.
2. Aprovar/rejeitar candidaturas fictícias e verificar a mudança de acesso.
3. Verificar fornecedor pendente e confirmar a permissão de apresentar proposta.
4. Conferir liquidação, reembolso e totalizações em centavos.
5. Exportar CSV e conferir filtros, datas e neutralização de fórmulas.

### Infraestrutura e UX

1. Confirmar HTTPS, `/up`, assets, registo e captura de email privada.
2. Abrir links internos por navegação Inertia e por refresh direto.
3. Exercitar os três tamanhos de ecrã e o percurso só com teclado.
4. Simular conexão lenta/offline no navegador e confirmar possibilidade de retentar.
5. Num ambiente autorizado, comparar contagens antes/depois de um redeploy.

Registar para cada execução: ID do cenário, data, commit, ambiente, perfil,
passos, resultado esperado, resultado observado e evidência sem dados secretos.
Não marcar estes roteiros como executados apenas porque um teste HTTP passou.

## 6. Processo TDD

Para cada bug confirmado:

1. **Red:** adicionar um teste que reproduz o comportamento esperado e falha
   no código atual. Guardar o motivo da falha no relatório.
2. **Green:** corrigir a causa com a menor alteração; executar o mesmo teste.
3. **Refactor:** rever nomes, transações, isolamento e duplicação; executar
   novamente os testes afetados e os controlos de qualidade.
4. **Regressão:** executar a suíte completa antes de aprovar a alteração.

Não designar como TDD um teste escrito apenas depois da correção. Os testes
preexistentes constituem regressão; a auditoria de CI/formatação é uma
verificação de processo, não um ciclo TDD de lógica de negócio.

## 7. Comandos e critérios de saída

```sh
php artisan test --compact
npm run test:unit
npm run types:check
npm run check
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan analyse --debug --memory-limit=512M --no-progress --error-format=raw
npm run build
```

Executar o Pest apenas com a configuração isolada de `phpunit.xml`, sem cache
de configuração de produção. O comando agregado do projeto é
`composer ci:check`; este inclui formatação, análise estática e regressão PHP,
além das verificações frontend. Requer PHP 8.5 e a versão Node compatível com
as dependências instaladas. `--debug` na análise estática evita o worker
paralelo que apresentou bloqueio neste Windows.

Critérios de saída:

- Nenhum teste executado a falhar; testes ignorados identificados com motivo.
- Regressões de cada correção aprovadas, incluindo preservação dos dados.
- Tipos, análise estática, formatação e build aprovados.
- Nenhum segredo nem dump incluído no diff ou nos artefactos públicos.
- Sem P1 aberto para o fluxo a publicar; limitações aceites explicitamente.
- Aceitação visual e compatibilidade MySQL registadas antes de afirmar
  homologação integral do ambiente publicado.

## 8. Gestão de defeitos e melhorias

Cada bug deve ter ID, prioridade, condição inicial, passos reproduzíveis,
esperado/observado, causa, ficheiros afetados, teste associado e estado.
Estados: identificado, reproduzido, corrigido localmente, validado,
publicado, ou pendente. "Validado" localmente não significa "publicado".

Melhorias sem reprodução de defeito ficam no backlog com valor esperado e
critério de aceitação; não contam como bugs corrigidos. Priorizar MySQL
isolado em CI, smoke tests após deploy, cobertura de navegador e acessibilidade
sem introduzir serviços pagos nem dependências sem aprovação.
