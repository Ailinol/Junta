# Plano e Estratégia de Testes de Software — Junta

**Universidade Eduardo Mondlane — Faculdade de Ciências**  
**Departamento de Matemática e Informática — Licenciatura em Informática**  
**Disciplina:** Engenharia de Software  
**Trabalho:** Estratégia de testes do sistema de compra colectiva Junta  
**Grupo:** [preencher] · **Turma/Ano lectivo:** [preencher]  
**Autores e números de estudante:** [preencher]  
**Docente:** [preencher]  
**Data:** 20 de novembro de 2026 · **Fuso de referência:** Africa/Maputo  
**Versão documental:** 1.0 · **Base Git:** `c7d4dadb3aa990f6d8d4c0003297e0a67a5726be`, acrescida das alterações locais identificadas no relatório.

## 1. Introdução e base normativa

Este trabalho responde às três perguntas da estratégia de testes: o que testar, como testar e como determinar se os testes são suficientes. O objecto é a implementação efectivamente existente do Junta, e não uma plataforma comercial idealizada. Testar procura demonstrar a satisfação de requisitos e descobrir defeitos; a aprovação de uma suíte não demonstra a ausência de erros.

A referência primária é **UEM-ES-Teste de Software.pdf**, páginas físicas e numeradas **37–38**, de um total de 39 páginas. A página 37 define âmbito/prioridades, níveis/técnicas e critérios/responsabilidades; a página 38 exige os entregáveis E1–E6. Foi lida a cópia disponibilizada pelo utilizador, SHA-256 `4a36cff41dddefbaa3285fd997502492623da93baba2efec2ced828f96decf25`. Não se atribui ao material uma percentagem de cobertura que ele não estabelece.

### 1.1. Matriz de conformidade com a UEM

| Critério obrigatório                                                                     | Localização e evidência                      | Situação                                                                                                          |
| ---------------------------------------------------------------------------------------- | -------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| P. 37: funcionalidades classificadas por risco e frequência; identificação dos níveis    | Secções 3 e 5; inventário de funcionalidades | Documentado com base no código                                                                                    |
| P. 37: três funcionalidades críticas, requisitos e partições                             | Secção 6 e `Matriz_Casos_Teste.md`           | Documentado; execução individual discriminada                                                                     |
| P. 37: técnicas, serviços externos, carga e justificação da pirâmide                     | Secções 5.3–5.6                              | Automatização existente distinguida de ensaios planeados                                                          |
| P. 37: cobertura mínima, revisão cruzada, momentos de execução e registo de defeitos     | Secções 5.7–5.10                             | Política proposta; limiar E3 implementado no CI                                                                   |
| E1 — objectivos, níveis, cobertura, responsáveis, ferramentas e riscos                   | Secções 4–5                                  | Entregue                                                                                                          |
| E2 — três requisitos; partições, fronteiras, casos positivos/negativos e rastreabilidade | Secção 6 e matriz detalhada                  | Entregue; não se apresentam casos planeados como executados                                                       |
| E3 — pelo menos dez testes de regras de negócio, TDD e cobertura                         | Secção 7; testes e relatórios XML/HTML       | Quantidade e cobertura cumpridas; a origem em TDD de todos os testes não é certificável pelo histórico disponível |
| E4 — dois cenários concretos e cinco critérios Dado/Quando/Então                         | Secção 8                                     | Especificados; homologação do cliente pendente                                                                    |
| E5 — CI a cada push, execução do E3 e captura de resultados                              | Secção 9; `.github/workflows/tests.yml`      | Configuração preparada e comandos verificados localmente; execução no GitHub pendente                             |
| E6 — resultados reais, defeitos, lições e apresentação de oito minutos                   | Secções 10–11; relatório e roteiro           | Entregue com limitações explícitas                                                                                |

**Reserva de conformidade:** não se declara cumprimento integral da expressão «escritos com TDD» do E3 para código preexistente apenas porque os testes passam. Os testes novos deste trabalho são de caracterização/regressão. A validação académica dessa reserva cabe ao docente. Não foi criada funcionalidade artificial para produzir uma contagem de testes TDD.

## 2. Método de análise

Antes de redigir foram inspeccionados `composer.json`, dependências instaladas, `package.json`, `phpunit.xml`, rotas, configuração Fortify, providers, modelos, migrations, Form Requests, controladores, serviços em `app/Support`, seeders e testes. Foram consultadas as versões instaladas e a documentação oficial das ferramentas. A referência de execução é a **árvore de trabalho local**, que também contém alterações anteriores ainda não publicadas; o relatório de cobertura não certifica automaticamente o código que está no Railway.

As evidências correntes encontram-se em `evidencias/`. Os documentos antigos na raiz são antecedentes históricos, com contagens de outras versões, e não substituem este relatório. Não foram executados testes destrutivos no MySQL publicado, nem chamadas pagas à IA.

## 3. Caracterização do sistema

### 3.1. Arquitectura e tecnologias verificadas

O Junta é uma aplicação monolítica Laravel, com interface React apresentada por Inertia. Rotas web invocam controladores e Form Requests; os serviços coordenam transacções e regras comerciais; o Eloquent persiste as entidades. Wayfinder gera funções TypeScript de rotas e o Vite compila os assets. Não existe um microserviço independente de pagamentos reais.

| Camada       | Implementação observada                                                   | Implicação para os testes                                                                         |
| ------------ | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Servidor     | PHP 8.5.11; Laravel 13.38.0                                               | Pest 5.3.3 e plugin Laravel 5.0.1; validar HTTP, autorização, transacções e persistência          |
| Interface    | React 19.3.1; Inertia React 3.9.1; TypeScript 5.9.3; Vite 8.3.4           | Node Test Runner, verificações de tipos e build; asserções Inertia não equivalem a navegador real |
| Autenticação | Fortify 1.41.0                                                            | Registo, login, recuperação, verificação de email e encaminhamento por perfil                     |
| Dados        | MySQL no ambiente publicado; SQLite isolado na suíte                      | SQLite não prova semântica de concorrência/locking do MySQL                                       |
| Operação     | Docker, Nginx/PHP-FPM, fila e scheduler; health check `/up`               | Ensaios de release devem confirmar build, arranque, persistência e tarefas periódicas             |
| Pagamentos   | Interface `PaymentGateway` ligada a `TestPaymentGateway`                  | M-Pesa/e-Mola/mKesh/cartão são opções da simulação; não integrações comerciais certificadas       |
| IA           | `OpenAiClient`, `AiAdvisor`, análise de fornecedores/propostas e previsão | HTTP simulado nos testes; não avaliar qualidade factual da IA apenas por JSON válido              |
| Qualidade    | Pint 1.32.1; Larastan 3.13.0; Xdebug 3.5.3 temporário                     | Análise estática complementa execução; cobertura de linhas tem âmbito explicitado                 |

### 3.2. Entidades e relacionamentos

| Entidade                            | Relações e responsabilidade efectivas                                                                                    |
| ----------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `User`                              | Pode acumular os indicadores de administrador/organizador; associa-se a fornecedor, candidatura, participações e pedidos |
| `OrganizerApplication`              | Uma candidatura por utilizador; decisão e responsável pela revisão                                                       |
| `Supplier`                          | Associado a utilizador; propostas e estado de verificação administrativa                                                 |
| `Campaign`                          | Pertence a organizador; tem participações e propostas; guarda fase e condições de compra                                 |
| `Participation`                     | Liga utilizador e campanha, com quantidade e estado; a participação não comprova pagamento                               |
| `Proposal`                          | Liga fornecedor e campanha; preço, capacidade, mínimo, transporte, validade e reserva                                    |
| `PurchaseOrder`                     | Liga campanha, participação e comprador; fixa montantes, condições e dados de recolha                                    |
| `PaymentAttempt` / `PurchaseRefund` | Referências e estados de tentativas/reembolsos; reconciliação e idempotência                                             |
| `SupplyOrder` / `PurchaseIssue`     | Fornecimento, recepção, entrega e ocorrências                                                                            |
| `FinancialEntry` / `AuditEntry`     | Movimentos financeiros e rastreabilidade das acções; não substituir por simples contagem de pedidos                      |
| `PlatformPricingSetting`            | Taxa global administrada; condições já fixadas não são recalculadas retroactivamente                                     |

As migrations contêm unicidades e chaves estrangeiras, incluindo protecção do histórico de pedidos/ocorrências contra eliminação de utilizadores. A autorização usa gates e verificações de propriedade; não há um único enum que represente todos os perfis possíveis. O encaminhamento de `/dashboard` dá prioridade a administrador, organizador, fornecedor e, finalmente, consumidor.

### 3.3. Funcionalidades e estado de implementação

| Funcionalidade                                                                    | Estado actual                                                               | Evidência principal                                                      |
| --------------------------------------------------------------------------------- | --------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Autenticação, recuperação de password e verificação de email                      | Implementado                                                                | Fortify, testes `Auth/*` e `RoleDashboardRedirectTest`                   |
| Aprovação de organizadores e verificação de fornecedores                          | Implementado                                                                | Controladores administrativos, testes de administração e acesso          |
| Publicar/manter campanhas, aderir, alterar/cancelar participação                  | Implementado, condicionado por fase/propriedade                             | `CampaignController`, `ParticipationController`, testes de consistência  |
| Submeter/comparar/seleccionar propostas                                           | Implementado com defeito de limite monetário identificado                   | `ProposalRequest`, `ProposalAvailability`, `ProposalSelection`, EXP01    |
| Comissão e transporte partilhado até ponto único                                  | Implementado                                                                | `PlatformPricing`, `DeliveryFeeAllocation`, `PlatformPricingTest`        |
| Cobrança, reconciliação e reembolso                                               | Implementado como simulação                                                 | `TestPaymentGateway`, testes de pagamentos e recuperação                 |
| Receber, separar, entregar por código e tratar ocorrências                        | Implementado                                                                | `CollectivePurchase`, testes de compra e supervisão                      |
| Registos de liquidação/encerramento                                               | Implementado; registo administrativo, sem transferência bancária automática | Métodos `finance`/`complete` e testes financeiros                        |
| IA de apoio e previsão                                                            | Integração implementada; utilidade depende dos dados e do serviço           | `AiAdvisorTest`, `OpenAiIntegrationTest`, `CampaignForecastTest`         |
| Fotografias/ilustrações e apresentação                                            | Implementado; imagens locais leves e carregamento diferido                  | `CampaignVisual`, `CampaignPresentationTest`                             |
| Entrega na morada                                                                 | Compatibilidade histórica; novas condições usam ponto único                 | `CollectivePurchase::quote`, testes de compatibilidade de preços antigos |
| Pagamentos comerciais reais, cálculo por mapas/peso, testes de carga certificados | Não implementado/não executado neste trabalho                               | Não confundir nomes das opções de pagamento com conectores reais         |
| Autenticação de dois factores                                                     | Suporte do starter kit, desactivado na configuração actual                  | `config/fortify.php`; testes condicionais ignorados                      |

### 3.4. Fluxos principais e invariantes

1. O utilizador autentica-se e acede à área autorizada. Uma candidatura não atribui automaticamente privilégios de organizador.
2. O organizador publica a campanha; consumidores aderem com quantidade inteira válida. Uma segunda adesão activa do mesmo utilizador é recusada; uma participação cancelada pode ser reactivada enquanto a campanha admite adesões.
3. Fornecedor verificado submete proposta com condições comerciais; o organizador encerra interesse e selecciona uma proposta válida, única e da campanha certa.
4. A administração define a comissão e, se necessário, confirma a cotação global do transporte. O organizador abre pagamentos com prazo e ponto de recolha. Os montantes são fixados por participante.
5. O comprador aceita as condições e inicia pagamento. A confirmação depende da reconciliação com o prestador de teste; repetir a operação não deve originar nova cobrança indevida.
6. No apuramento, cobranças incertas exigem revisão. Atingido o mínimo pago, inicia-se fornecimento; em cancelamento ou mínimo insuficiente, há reembolso integral dos pedidos cobrados.
7. O organizador recebe/separa produtos e entrega por código. O administrador regista custos/liquidações e encerra após cumprir os impedimentos financeiros e operacionais.

Invariantes de maior risco: somas exactas em centavos; soma das quotas igual à cotação total; nenhuma duplicação de cobrança/reembolso; capacidade reservada respeitada; isolamento entre compradores; preservação de condições fixadas e histórico; transições comerciais autorizadas. A taxa inicial de 2,5% é uma configuração actual, não uma constante irreversível do domínio. O transporte arrecadado não é, por si só, lucro líquido.

## 4. Objectivos dos testes

**Validação:** avaliar se os fluxos satisfazem as necessidades de consumidores, fornecedores, organizadores e administração, através de cenários concretos e aceitação pelo cliente do grupo. **Verificação:** avaliar se o código, cálculos, validações, persistência e controlos implementam correctamente as especificações e restrições. Inspecção de código e análise estática são formas de verificação complementares aos testes dinâmicos.

Objectivos operacionais: descobrir defeitos reproduzíveis; proteger regras financeiras e permissões; estabelecer rastreabilidade RF→CT→teste→evidência; distinguir aprovado, reprovado, ignorado e não executado; permitir regressão automática a cada alteração. Um teste de caracterização que confirma um defeito pode passar sem que o requisito de negócio esteja aprovado.

## 5. E1 — Plano e estratégia

### 5.1. Âmbito e exclusões

Abrange regras monetárias/rateio, participações, propostas, acessos, compras, reconciliação/reembolsos simulados, fornecimento, códigos, supervisão, IA simulada, apresentação e configuração de CI. Os três requisitos detalhados são RF01, RF02 e RF03, definidos na secção 6.

Exclui certificação de segurança, gateways de dinheiro real, inferência de qualidade da IA a partir de mocks, avaliação de transportadores reais e execução de carga no Railway experimental. A persistência/concorrência MySQL, acessibilidade integral e homologação por utilizadores finais permanecem ensaios específicos pendentes, não resultados presumidos.

### 5.2. Classificação por risco e frequência

Risco combina impacto e probabilidade qualitativos: **crítico** implica exposição de dados, perda/duplicação de valores ou corrupção do processo; **alto**, bloqueio de fluxo essencial; **médio**, perda de eficiência/informação auxiliar. A frequência é uma estimativa de utilização do domínio, não telemetria medida.

| Área                            | Risco e consequência                             | Frequência estimada   | Nível/evidência automatizada                              | Complemento manual/pendente                                            |
| ------------------------------- | ------------------------------------------------ | --------------------- | --------------------------------------------------------- | ---------------------------------------------------------------------- |
| Login, perfis e isolamento      | Crítico: acesso indevido                         | Alta                  | HTTP/integração: acesso, autenticação e dashboards        | Sessões em navegador e revisão de segurança                            |
| Participações RF01              | Alto: procura/quantidades incorrectas            | Alta                  | Validação de componente e HTTP                            | Usabilidade de adesão                                                  |
| Propostas/seleccção RF02        | Crítico: condições inválidas/fornecedor errado   | Média/alta            | Validação, consistência e compromisso do fornecedor       | Confirmação comercial com fornecedor                                   |
| Comissão/rateio RF03            | Crítico: cobrança incorrecta                     | Alta por compra       | Unitário puro e integração de tarifas                     | Compreensão do preço no checkout                                       |
| Pagamento/reconciliação RF03    | Crítico: cobrança duplicada/estado falso         | Alta por compra       | Integração com gateway de teste e HTTP                    | Contrato de gateway real, ainda inexistente                            |
| Reembolso RF03                  | Crítico: retenção indevida de valores            | Ocasional             | Integração financeira e recuperação                       | Reembolso real fora do âmbito                                          |
| Reserva concorrente             | Crítico: excesso de capacidade                   | Episódica, simultânea | Ensaio de dois processos com SQLite                       | Repetição em MySQL isolado obrigatória antes de dinheiro real          |
| Recepção/código/ocorrências     | Alto: entrega errada ou dupla                    | Média                 | Integração de compra/supervisão                           | Ensaio do ponto de recolha e dispositivos                              |
| Custos/liquidações/encerramento | Crítico: saldo ou encerramento indevido          | Média                 | Integração financeira/administração                       | Conferência documental por responsável independente                    |
| Gestão de conta com histórico   | Alto: erro ou perda de rastreabilidade           | Baixa                 | Testes de perfil, incluindo correcção local anterior      | Confirmar publicação da correcção antes de declarar produção corrigida |
| IA e previsão                   | Médio: recomendação inadequada/indisponibilidade | Ocasional             | HTTP simulado, validação de respostas e ausência de dados | Avaliação humana e limites de consumo                                  |
| Relatórios/ordenação/datas      | Médio: interpretação incorrecta                  | Média                 | Testes JS de lógica/apresentação                          | Conferência visual e calendário de Maputo                              |
| Imagens e apresentação          | Médio/baixo: informação visual indisponível      | Alta                  | Testes de apresentação e seeders; build                   | Navegadores, leitores de ecrã e redes lentas                           |
| Operação e deploy               | Alto: indisponibilidade/perda de dados           | A cada release        | Testes de proxy/configuração; health check                | Backup/restauro e volume MySQL em ambiente isolado                     |

### 5.3. Níveis, técnicas e ferramentas

| Nível/contexto         | Técnica                                                                  | Ferramenta e justificação                                                                                                |
| ---------------------- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| Unitário puro          | Partições, fronteiras e oráculos monetários independentes                | Pest, sem Laravel/BD nos módulos E3 seleccionados; rápido e determinístico                                               |
| Componentes/integração | Validação de contratos, HTTP, relações, transacções e falhas de serviços | Pest/Laravel, factories, SQLite, fakes HTTP/mail/tempo; base real de teste, não mocks de Eloquent                        |
| Sistema                | Fluxo completo entre intervenientes, configuração e persistência         | Aplicação integrada em ambiente isolado; verificações HTTP e roteiro manual; ainda sem suíte instalada de navegador real |
| Release                | Casos derivados de requisitos, cenários, compatibilidade MySQL e carga   | Equipa distinta do autor; build Docker, `/up`, fila/scheduler e ensaios isolados; parte ainda planeada                   |
| Utilizador/aceitação   | Dois cenários concretos e cinco critérios verificáveis                   | Cliente do grupo e representantes dos perfis; registo de aprovação/reprovação                                            |

Os testes de desenvolvimento incluem unitários, componentes e sistema executados pela equipa. Os testes de release avaliam uma versão candidata por responsáveis com independência face ao autor. Testes alfa/beta/aceitação com utilizadores não são substituídos por Pest nem pelo acesso do programador ao ambiente publicado.

### 5.4. Pirâmide de testes

A distribuição desejada privilegia muitos testes pequenos de regras, menos integrações e poucos percursos completos. Para novas regras propõe-se, como orientação e não quota estatística, 60–70% de casos unitários, 20–30% de componente/integração e 5–10% de sistema. A aceitação manual é complementar.

A suíte actual é fortemente concentrada em testes Laravel de componente/HTTP. Não se afirma que já cumpre essa proporção: `PurchaseConcurrencyTest`, apesar de estar em `tests/Unit`, executa processos, migrations e uma BD e é um teste de integração; `SelectedValidationRulesTest` usa o componente Validator; um exemplo que verifica `true` não conta para o mínimo de regras de negócio. A configuração `phpunit.unit.xml` isola os dois módulos puros do E3. Não se vai refactorizar artificialmente código funcional apenas para melhorar a proporção.

### 5.5. Interfaces externas e anomalias

O prestador é simulado, com testes de timeout após aceitação, referência divergente, repetição de consulta, falha e reembolso incerto. A IA usa fakes/respostas controladas e não recebe credenciais reais durante a suíte. O correio usa transporte em memória nos testes. Tempo é congelado nos limites de validade, reserva e pagamento. Oráculos verificam montantes persistidos, estados, movimentos e ausência de duplicações, não apenas HTTP 200.

### 5.6. Desempenho, carga e compatibilidade

**Planeado, não executado:** num ambiente descartável semelhante ao de release, usar MySQL isolado e, após preparação/aprovação, uma ferramenta de carga HTTP como k6. Iniciar com 1, 5 e 20 utilizadores virtuais, aquecimento de 30 s e observação de 60 s por patamar, separando leituras de adesão/pagamento simulado. Os valores são uma proposta de ensaio, não capacidade comprovada ou SLA.

Proposta de critérios a negociar: menos de 1% de erros inesperados, p95 até 1 s nas leituras e 2 s nas escritas, nenhuma duplicação de referência e nenhuma reserva acima da capacidade. Monitorizar CPU, memória, locks e ligações. Não aplicar este ensaio ao Railway com crédito limitado. Testar restauro de backup, concorrência MySQL, reinício de serviço e persistência em volume num ambiente próprio antes de release comercial.

### 5.7. Política de cobertura e saída

| Indicador                                       | Política                                                                                    | Aplicação neste trabalho                                                                               |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| E3: linhas de `Money` + `DeliveryFeeAllocation` | Mínimo agregado de 90%                                                                      | CI com `--coverage --min=90`; medição local 95%, 38/40 linhas                                          |
| Caminhos monetários excepcionais                | Cobrir validação, quantidade inválida, overflow e arredondamento                            | Testes de componente complementam o núcleo puro                                                        |
| Cobertura global de `app/`                      | Medir e publicar; meta proposta de 80% de linhas                                            | A meta global é de melhoria; não se acrescenta um bloqueio arbitrário sem comparar com a linha de base |
| Requisitos críticos                             | Pelo menos um caso normal, anómalo e de fronteira por requisito; sem defeito crítico aberto | Matriz explicita casos exactos pendentes e defeito RF02                                                |
| Aceitação                                       | Cinco critérios acordados e dois cenários homologados pelo cliente                          | Homologação ainda pendente                                                                             |

Cobertura de linhas não é cobertura de requisitos, branches, caminhos, condições de corrida ou qualidade do oráculo. A cobertura E3 de 95% não é cobertura global nem prova TDD. Métodos/classes e os intervalos não cobertos são também publicados no relatório. Não se declara aprovação comercial enquanto faltarem gateway real, ensaios MySQL e aceitação.

**Entrada:** dependências instaladas, BD isolada, chaves externas desactivadas, código identificável e dados sintéticos. **Suspensão:** ligação acidental a BD partilhada, execução de serviços pagos, indisponibilidade do ambiente ou resultados não reprodutíveis. **Retoma:** isolamento comprovado e nova execução do conjunto afectado. **Saída de desenvolvimento:** testes afectados e regressão aprovados, relatórios guardados, defeitos classificados e revisão cruzada concluída. **Saída de release/aceitação:** acrescentar os ensaios e assinaturas independentes previstos acima.

### 5.8. Responsabilidades e revisão cruzada

| Função                           | Responsável editável              | Revisor/contraparte                 | Evidência produzida                      |
| -------------------------------- | --------------------------------- | ----------------------------------- | ---------------------------------------- |
| Coordenação e rastreabilidade    | [Membro A — nome/n.º]             | [Membro D]                          | Plano, matriz e decisão de release       |
| Regras de participação/propostas | [Membro B — nome/n.º]             | [Membro C, que não seja autor]      | Testes RF01/RF02 e registo de defeitos   |
| Montantes, pagamento e reembolso | [Membro C — nome/n.º]             | [Membro B]                          | E3, RF03 e conferência financeira        |
| CI, ambientes e evidências       | [Membro D — nome/n.º]             | [Membro A]                          | Workflow, JUnit e cobertura              |
| Interface e cenários de sistema  | [Membro A/B — preencher]          | [outro membro não autor]            | Registo de navegador/dispositivo         |
| Cliente/aceitação                | [Representante do cliente — nome] | [Organizador/consumidor designados] | Critérios acordados e termo de aceitação |

Se o grupo tiver menos membros, pode acumular funções, mantendo outro responsável para testar/rever o código do autor. A revisão de release deve ter independência suficiente; o cliente decide a aceitação. Não há nomes de membros ou aprovação do cliente fornecidos neste trabalho.

### 5.9. Regressão, periodicidade e defeitos

A cada alteração, executar primeiro o teste afectado; a cada push/PR, executar E3, regressão PHP, testes JS, análise estática e build no CI. Antes de entrega, rever a matriz, os casos ignorados, os defeitos e os cenários de release. Não somar execuções sobrepostas como se fossem testes únicos.

Um defeito recebe ID, requisito/caso, versão/ambiente, passos, resultado esperado/observado, gravidade/prioridade, evidência, responsável, estado e reteste. Fluxo: identificado → reproduzido → triado → corrigido → retestado → encerrado, ou pendente com justificação. A correcção não apaga o registo anterior. Alterações a regras comerciais exigem decisão explícita, não ajuste do oráculo para fazer o teste passar.

### 5.10. Riscos técnicos e mitigação

| Risco                                             | Mitigação e limite residual                                                                                              |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| SQLite aprovar comportamento diferente do MySQL   | Executar compatibilidade/locks em MySQL descartável; permanece pendente nesta execução                                   |
| Teste tocar na produção ou consumir crédito de IA | Variáveis apenas no processo; SQLite em memória, API de IA vazia, HTTP controlado; nunca usar `.env` de produção no Pest |
| Regressão verde apesar de defeito conhecido       | Separar teste de caracterização EXP01 do veredicto de conformidade RF02                                                  |
| Testes ignorados por configuração                 | Registar motivo; distinguir indisponibilidade de 2FA da condição indevida no teste sem 2FA                               |
| Passagem sem descoberta de testes                 | E3 tem configuração própria, cobertura mínima e verificação da contagem JUnit no CI                                      |
| Dados/segredos em evidências                      | Não copiar `.env`, credenciais, dumps ou cookies; resultados só de dados sintéticos                                      |
| Esgotamento de recursos em carga                  | Usar ambiente isolado e orçamento aprovado; não ensaiar carga no Trial Railway                                           |
| Documentação desactualizada                       | Identificar commit/árvore de trabalho, data, relatórios de máquina e pendências; rever a cada release                    |

## 6. E2 — Requisitos e casos de teste

Os identificadores seguintes são rótulos de requisitos **extraídos da implementação**, não requisitos aprovados formalmente por um cliente inexistente. A [matriz detalhada](Matriz_Casos_Teste.md) contém dados, partições, fronteiras, passos, resultados esperados, critério de aprovação e teste correspondente.

| Requisito                                    | Enunciado verificável                                                                                                                                                                       | Partições/fronteiras principais                                                                                                              |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| RF01 — Participar numa campanha              | Um utilizador autenticado adere com quantidade inteira positiva dentro do limite, apenas durante interesse aberto, sem duplicar adesão activa                                               | Ausente/não inteiro/≤0/1…2147483647/>limite; aberta/fechada; adesão nova/activa/cancelada                                                    |
| RF02 — Proposta comercial utilizável         | Fornecedor autorizado submete condições válidas; uma proposta utilizável pode ser seleccionada pelo responsável sem conflitos ou expiração                                                  | Preço ausente/≤0/válido/decimais inválidos/excesso; fornecedor verificado/não; duplicada/histórica; prazo antes/no/depois do instante limite |
| RF03 — Cobrar/reembolsar montantes correctos | Condições são fixadas pela plataforma; comissão e transporte são exactos, autorização e capacidade são respeitadas, repetições não duplicam dinheiro e cancelamento reembolsa integralmente | Taxa 0…100%; transporte incluído/cotado/em falta; centavos e restos; tentativas novas/repetidas/incertas; mínimo atingido/não atingido       |

O limite do formulário RF02 (`999999999999.99`) é inconsistente com o formato aceite por `Money::cents` (`9999999999.99`). O caso diagnóstico EXP01 confirma uma proposta submetida a `10000000000.00` e recusada apenas na selecção. O requisito de consistência é reprovado nessa partição; não se redefine o esperado para esconder o defeito.

## 7. E3 — Testes unitários, TDD e cobertura

### 7.1. Conjunto entregue

| Ficheiro                                   | Casos concretos                    | Papel                                                                                                           |
| ------------------------------------------ | ---------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `tests/Unit/DeliveryFeeAllocationTest.php` | 16 existentes                      | Rateio proporcional, restos determinísticos, limite, grupo vazio, custo/IDs/quantidades inválidos e duplicações |
| `tests/Unit/MoneyTest.php`                 | 20 novos                           | Conversão exacta, multiplicação no limite, comissão/arredondamento e apresentação monetária                     |
| `tests/Feature/MoneyValidationTest.php`    | 14 novos, fora da contagem E3 pura | Excepções de validação que dependem do Laravel e caracterização EXP01                                           |

O mínimo de dez é satisfeito por **36 casos unitários concretos**, distribuídos por datasets com entradas e oráculos distintos; não se confundem casos instanciados com funções de teste. Não se conta `ExampleTest` nem o ensaio concorrente como unidade de negócio pura. Os 14 casos de componente não são somados aos 36 para inflacionar o E3.

### 7.2. Organização e oráculos

Os testes seguem preparação, chamada e asserções. Por exemplo, 20 centavos de subtotal e 250 pontos base produzem exactamente um centavo de comissão: o resultado esperado é fixado independentemente do método testado. No rateio, 100 MZN para quantidades 3 e 2 produz 60 e 40 MZN; os restos de centavo são atribuídos por maior resto, com desempate determinístico por ID. Testam-se também custo negativo, participante duplicado, quantidade zero e ausência de participantes.

```php
$subtotal = 20;
$basisPoints = 250;

$result = Money::commission($subtotal, $basisPoints);

expect($result)->toBe(1);
```

As entradas monetárias inválidas lançam uma excepção criada com o Validator do Laravel. Esses testes são de componente com aplicação inicializada; não se substitui o framework por mocks só para os chamar unitários puros.

### 7.3. Evidência e limite da alegação de TDD

O código de `Money` existia antes dos novos testes. Por isso, escrever e executar esses testes agora não constitui TDD retroactivo. O repositório de referência também não fornece uma sequência granular autónoma que certifique a ordem teste-antes-da-implementação para todos os testes existentes de rateio. O conjunto está preparado para suportar TDD em alterações futuras, mas **a exigência de proveniência TDD permanece uma reserva explícita do E3**.

Procedimento a adoptar: formular o próximo comportamento legítimo; escrever teste que falhe por esse comportamento em falta; guardar saída Red e commit; implementar a menor mudança autorizada; guardar Green; refactorizar mantendo os testes verdes; submeter a revisão. Não substituir Red por erro de sintaxe, ficheiro ausente no runner ou limiar de cobertura artificial. O ensaio de limiar `--min=100`, quando demonstrado, é uma falha de política de cobertura, não prova TDD.

### 7.4. Medição

O Xdebug 3.5.3 oficial foi carregado apenas por opção de linha de comandos, sem editar `php.ini`, dependências Composer ou produção. A configuração `phpunit.unit.xml` inclui no denominador E3 apenas `Money` e `DeliveryFeeAllocation`. Resultado: **38/40 linhas, 95%; rateio 28/28, 100%; Money 10/12, 83,33%**. Os caminhos de excepção monetária são exercitados adicionalmente por testes de componente e constam da medição global separada.

Evidências: `evidencias/unitarios.xml`, `cobertura-unitaria.xml`, `cobertura-unitaria.txt` e [relatório HTML unitário](evidencias/cobertura-unitaria-html/index.html). O [relatório de execução](Relatorio_Execucao_Testes.md) apresenta a cobertura global e os motivos de testes ignorados. Linhas não cobertas, branches não medidos e âmbito limitado são limitações expressas, não ocultadas pela percentagem agregada.

## 8. E4 — Cenários e critérios de aceitação

Os nomes seguintes correspondem aos perfis sintéticos existentes. Não são identidades de clientes reais nem credenciais. Os cenários especificam percursos completos a homologar; a cobertura automatizada de partes desses percursos não representa aceitação formal pelo cliente.

### 8.1. Cenário SC01 — Compra com transporte partilhado e recolha

**Intervenientes:** Ana Mucavele e Helena Chongo, consumidoras; Carlos Nhantumbo, organizador; Paulo Cossa, fornecedor verificado; [responsável da administração]. **Pré-condições:** contas verificadas, campanha aberta, pagamentos de teste, taxa global 2,5% e uma reserva comercial válida.

1. Carlos publica uma campanha de arroz, com ponto de recolha em Maputo. Ana adere com três unidades e Helena com duas; ambas observam interesse, não confirmação de pagamento.
2. Paulo submete proposta a 100 MZN/unidade, disponibilidade de pelo menos cinco, mínimo cinco, reserva e validade futuras, sem transporte incluído. Carlos encerra interesse e selecciona a proposta.
3. A administração confirma cotação global de 100 MZN até ao ponto. Carlos define ponto/prazo e abre pagamentos, sem poder dispensar a comissão.
4. O sistema fixa para Ana: produtos 300, comissão 7,50, transporte 60, total **367,50 MZN**; para Helena: 200 + 5 + 40 = **245 MZN**. Total colectivo **612,50 MZN**. Não soma novamente o transporte por unidade do fornecedor.
5. Cada consumidora aceita as condições, escolhe método disponível e, se necessário, indica contacto de nove dígitos nacionais. O prestador simulado confirma cada referência; a reconciliação regista duas cobranças, sem duplicar operações repetidas.
6. No apuramento são confirmadas cinco unidades pagas; inicia-se o fornecimento e o valor de produtos devido ao fornecedor é 500 MZN.
7. Carlos regista a recepção de cinco unidades, separa os pedidos e entrega mediante os respectivos códigos, que não podem ser reutilizados. Cada consumidora consulta apenas os seus dados privados.
8. A administração regista custo comprovado do transporte, obrigação e liquidação correspondentes, liquidação do fornecedor e encerra após entregas, ocorrências e saldos devidos resolvidos. Para custo de transporte de 100 MZN, os 12,50 MZN de comissão são receita antes de outros custos; não há transferência bancária real na simulação.

**Resultado:** compra concluída com rastreabilidade e preços preservados. **Alternativas:** cotação ausente bloqueia abertura; cobrança incerta conduz a revisão; falta de recepção/código impede entrega; saldo devido impede encerramento. **Homologação:** pendente, mediante execução manual do percurso completo e assinatura do cliente.

### 8.2. Cenário SC02 — Mínimo insuficiente e reembolso sem duplicação

**Intervenientes:** os mesmos, para permitir comparação. **Pré-condições:** proposta com mínimo cinco, quantidades 3 e 2, montantes fixados como SC01 e prazo conhecido.

1. Ana aceita e paga 367,50 MZN pelo prestador de teste; Helena tem pagamento falhado ou não paga. A reconciliação confirma apenas a cobrança de Ana.
2. O prazo termina. Carlos ou o processamento agendado promove o apuramento. Se houver uma cobrança ainda incerta, o sistema exige revisão antes da decisão final.
3. Resolvida a incerteza, existem apenas três unidades pagas, abaixo do mínimo de cinco. A compra é cancelada; não é emitido fornecimento válido para simular mínimo atingido.
4. O sistema solicita e reconcilia o reembolso **integral de 367,50 MZN**, incluindo produtos, comissão e a quota de transporte de Ana. O pedido não pago de Helena não gera reembolso fictício.
5. A consulta ou recuperação do reembolso utiliza a referência original. Repetir o processamento não produz segundo reembolso; capacidade reservada é libertada segundo as regras do fluxo e os movimentos continuam auditáveis.
6. As consumidoras consultam o resultado no acompanhamento; o administrador confere os movimentos. Não se apagam pedidos nem histórico para obter saldo aparente zero.

**Resultado:** cancelamento e reembolso rastreável. **Homologação:** pendente; os testes de compra, tarifas e recuperação cobrem os componentes do percurso, sem substituírem o ensaio de aceitação.

### 8.3. Cinco critérios Dado/Quando/Então

| ID   | Critério verificável                                                                                                                                                                                                                                                                       | Rastreabilidade       |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------- |
| CA01 | **Dado que** Ana está autenticada numa campanha aberta sem participação activa, **quando** adere com quantidade inteira 1, **então** existe exactamente uma participação sua com quantidade 1; quantidade zero é recusada sem persistência adicional.                                      | RF01; CT01–CT03; SC01 |
| CA02 | **Dado que** Paulo tem proposta válida e Carlos gere a campanha, **quando** Carlos a selecciona, **então** apenas essa proposta fica aceite; proposta expirada no instante limite ou conflitante não substitui a selecção.                                                                 | RF02; CT18–CT21; SC01 |
| CA03 | **Dado que** há quantidades 3 e 2, preço 100 MZN, comissão 2,5% e cotação 100 MZN, **quando** se abrem pagamentos, **então** as quotas de transporte são 60/40 e os totais 367,50/245 MZN; um campo de comissão zero enviado pelo organizador não elimina a taxa.                          | RF03; CT25–CT28; SC01 |
| CA04 | **Dado que** o mínimo pago foi atingido e os produtos foram recebidos, **quando** Carlos entrega pelo código do pedido, **então** a entrega fica registada uma vez; pedido de outro comprador ou código inválido não autoriza a operação, e encerramento exige os impedimentos resolvidos. | RF03; CT35–CT37; SC01 |
| CA05 | **Dado que** só Ana foi cobrada e o mínimo não foi atingido, **quando** a compra é cancelada e o prestador confirma o reembolso, **então** Ana recebe integralmente o total original e repetir a reconciliação não cria outro reembolso.                                                   | RF03; CT33–CT34; SC02 |

**Decisão do cliente:** [aprovar/reprovar, observações, nome, assinatura e data — preencher após execução].

## 9. E5 — Integração contínua

Foi adaptado o workflow existente, sem criar um segundo pipeline concorrente. `.github/workflows/tests.yml` passa a responder a **qualquer push**, PR e execução manual. Usa PHP 8.5, Node 24, extensões necessárias e Xdebug no runner; instala dependências pelos lockfiles e prepara SQLite em memória, correio/fila/sessão isolados e IA desactivada.

Sequência: instalação → preparação → build React/Vite → E3 com limiar 90% → regressão PHP com cobertura global → testes JS → Pint/Larastan/tipos/formatação → upload de JUnit/Clover/HTML, mesmo se houver falha. As acções estão fixadas por SHA; os artefactos têm retenção de 14 dias e não incluem `.env` nem ficheiros privados. Os comandos não usam `|| true` para ocultar falhas de testes. A contagem mínima E3 é conferida no JUnit.

O workflow não faz deploy, não usa MySQL do Railway, não contacta a IA e não contrata serviços. A configuração e comandos foram preparados/verificados localmente; **não se declara execução remota do GitHub Actions neste trabalho**. Para concluir essa validação operacional é necessário publicar as alterações no repositório e consultar a execução/artefactos. O crédito Railway não é usado para executar a suíte.

## 10. E6 — Resultados e limites

Os resultados correntes, durações, cobertura global, defeitos e respectivos estados encontram-se no [Relatório de Execução](Relatorio_Execucao_Testes.md). Os números provêm de JUnit/Clover e não de estimativas. A matriz separa cobertura automatizada existente de execução exacta pendente. O roteiro de [apresentação de oito minutos](Plano_Apresentacao.md) demonstra E3 e cobertura em directo e apresenta a regressão longa através de evidência previamente gerada.

Resultados não equivalem a aceitação comercial: há uma inconsistência monetária reproduzida, uma lacuna de teste condicional de 2FA, validação MySQL/carga/acessibilidade pendente e reserva sobre proveniência TDD. Uma suíte verde com caracterização de defeito conhecido exige leitura do relatório, não apenas da cor do pipeline.

## 11. Conclusão

A estratégia prioriza valores, transições e permissões e mantém rastreabilidade entre requisitos, partições, casos e evidências. A combinação de testes pequenos, integração com dados isolados, análise estática e cenários de aceitação permite verificar a implementação e apoiar a validação com utilizadores. As limitações são parte do resultado técnico: a cobertura medida não prova ausência de defeitos, compatibilidade completa com MySQL, TDD histórico ou aceitação pelo cliente.

## 12. Anexos técnicos e referências

- [Matriz detalhada RF01–RF03](Matriz_Casos_Teste.md).
- [Relatório de execução e defeitos](Relatorio_Execucao_Testes.md).
- [Roteiro de apresentação](Plano_Apresentacao.md).
- Código: `app/Models`, `app/Http/Requests`, `app/Http/Controllers`, `app/Support`, `database/migrations` e `tests`.
- Configuração: [`phpunit.xml`](../../phpunit.xml), [`phpunit.unit.xml`](../../phpunit.unit.xml), [workflow CI](../../.github/workflows/tests.yml).
- UEM, **UEM-ES-Teste de Software.pdf**, material de Engenharia de Software fornecido pelo utilizador, pp. 37–38; autor/data não estabelecidos nesta entrega.
- Pest: [cobertura](https://pestphp.com/docs/test-coverage) e [integração contínua](https://pestphp.com/docs/continuous-integration), consultados em 20/11/2026.
- Xdebug: [binários oficiais](https://xdebug.org/download); driver 3.5.3 compatível com PHP 8.5 TS VS17 x64. O ficheiro temporário não é dependência do repositório.
- GitHub: [upload-artifact v7.0.2](https://github.com/actions/upload-artifact/releases/tag/v7.0.2), fixado no workflow pela revisão oficial.

### 12.1. Comandos de reprodução

```bash
# PHPUnit/Pest usa SQLite isolado na configuração principal.
php artisan test --compact
php vendor/bin/pest --configuration phpunit.unit.xml
node --test tests/Unit/*.test.mjs
npm run types:check

# Com Xdebug disponível apenas no processo de teste:
XDEBUG_MODE=coverage php vendor/bin/pest --configuration phpunit.unit.xml \
  --coverage --min=90 --log-junit=unitarios.xml --coverage-clover=cobertura.xml
```

No PowerShell local, para repetir a medição com o driver temporário já descarregado:

```powershell
$env:PAO_DISABLE = 'true'
$env:OPENAI_ENABLED = 'false'
$env:OPENAI_API_KEY = ''
$env:XDEBUG_MODE = 'coverage'
$driver = Join-Path $env:TEMP 'junta-uem-xdebug-3.5.3.dll'
php -d "zend_extension=$driver" -d xdebug.mode=coverage vendor/bin/pest `
  --configuration phpunit.unit.xml --coverage --min=90
```

Antes de uma regressão completa, usar também `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio e SSR desactivado no processo. Não alterar o `.env` da aplicação nem apontar Pest a uma base partilhada. Para exportar os documentos formatados, consultar as instruções no relatório; as versões exportadas devem ser regeneradas quando o Markdown mudar.
