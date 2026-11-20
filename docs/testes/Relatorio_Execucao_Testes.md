# E6 — Relatório de execução de testes do Junta

**Data:** 20 de novembro de 2026 · **Fuso documental:** Africa/Maputo  
**Código de referência:** base `c7d4dadb3aa990f6d8d4c0003297e0a67a5726be` e árvore de trabalho local.  
**Âmbito normativo:** `UEM-ES-Teste de Software.pdf`, pp. 37–38.  
**Plano associado:** [Plano e Estratégia](Plano_Estrategia_Testes.md).  
**Responsável pela execução/revisão académica:** [preencher nomes e números].

## 1. Objectivos e conclusão técnica

A execução verificou regras e contratos implementados, procurou defeitos e produziu evidência rastreável para E1–E6. O foco é a integridade de quantidades, propostas e montantes, complementado pela regressão de acessos, fluxos, apresentação e integrações simuladas.

**Resultado corrente:** 415 casos PHP descobertos, **412 aprovados, 3 ignorados, zero falhados/erros**, com **3206 asserções**. A suíte JavaScript aprovou **54 casos**, sem falhas ou ignorados. O subconjunto E3 tem **36 casos aprovados** e **95% de cobertura de linhas** em dois módulos financeiros. A cobertura global de `app/` foi **88,62%**, ou **2648/2988 linhas executáveis**.

Esses resultados não significam que todos os requisitos estejam aprovados: EXP01 confirmou um defeito de consistência monetária, apesar de o teste diagnóstico passar. A execução em MySQL isolado, a aceitação do cliente e a comprovação de proveniência TDD de todo o E3 continuam pendentes. Não foi feita certificação de produção nem alterada a base publicada.

## 2. Ambiente e reprodutibilidade

| Item                | Configuração verificada                                                                                                  |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Sistema de execução | Windows/PowerShell, processos locais                                                                                     |
| PHP/Laravel         | PHP 8.5.11 TS x64; Laravel 13.38.0                                                                                       |
| Testes PHP          | Pest 5.3.3; plugin Laravel 5.0.1                                                                                         |
| Interface           | Node 24.20.0; React 19.3.1; Inertia React 3.9.1; TypeScript 5.9.3; Vite 8.3.4                                            |
| Instrumentação      | Xdebug 3.5.3 oficial, DLL temporária carregada por `-d zend_extension=...`, modo `coverage`                              |
| BD                  | SQLite `:memory:`; concorrência usa outro ficheiro SQLite temporário com dois processos                                  |
| Integrações         | IA desactivada no processo; chave vazia; HTTP simulado nos testes específicos; correio/sessão em memória e fila síncrona |
| Estado de produção  | Não usado para testes de escrita, carga, migrations ou reset neste trabalho                                              |

A DLL temporária tem SHA-256 `c75623ba050ddef734ba28a957e92df9ce5b8dfdb1a0569e5071bd6b68a12292`. Não foi adicionada ao repositório, ao `php.ini`, ao Dockerfile ou às dependências da aplicação. O `.env` e o ficheiro privado de acessos foram preservados; não fazem parte das evidências.

A árvore de trabalho já tinha, antes desta tarefa, alterações de documentação, scripts e uma correcção local do controlador de perfil. Elas foram preservadas. Assim, este relatório descreve o código local efectivamente testado; não se pode atribuir o mesmo resultado ao commit isolado ou à aplicação publicada sem nova verificação da revisão exacta.

## 3. Metodologia aplicada

1. Leitura do PDF de referência e identificação literal dos seis entregáveis; inspecção de arquitectura, modelos, migrations, gates, Form Requests, serviços e testes.
2. Selecção de RF01/RF02/RF03 por impacto financeiro, integridade e frequência estimada. Definição de partições, fronteiras, entradas anómalas e critérios verificáveis.
3. Reutilização dos 16 casos de rateio; adição de 20 casos unitários de `Money` e 14 casos de componente, incluindo a caracterização EXP01. O código funcional de cálculo não foi modificado para facilitar testes.
4. Execução unitária isolada com cobertura limitada aos dois módulos E3; depois regressão PHP completa, com cobertura global de `app/`, e execução JavaScript.
5. Preparação de CI com captura de resultados, limiar E3 e verificação de contagem. Separação entre validação local de comandos/configuração e execução remota, ainda pendente.
6. Registo de defeitos, casos não executados, restrições da cobertura e reserva sobre TDD. Não se alterou o oráculo para classificar como correcto um comportamento defeituoso.

## 4. Resultados de execução

| Execução                                                    | Resultado real                                                            | Evidência e observação                                                                                                  |
| ----------------------------------------------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| Primeira tentativa de configuração E3 com entradas `<file>` | **Sem testes descobertos**, processo terminou com código 0 e cobertura 0% | Ocorrência de preparação detectada; não contada como execução aprovada; configuração corrigida antes da medição válida  |
| E3: `pest --configuration phpunit.unit.xml` com cobertura   | **36 aprovados, 0 ignorados/falhados; 43 asserções**                      | `unitarios.xml`; duração exibida 1,85 s; núcleo financeiro apenas                                                       |
| Limiar E3 `--coverage --min=90`                             | **Aprovado**, cobertura 95%                                               | Comando igual ao bloqueio principal do CI; duração exibida 2,29 s                                                       |
| Componentes monetários/EXP01                                | **14 aprovados; 19 asserções**                                            | `limites-monetarios.xml`; duração exibida 3,82 s; EXP01 aprovado significa defeito reproduzido                          |
| Regressão PHP completa instrumentada                        | **415 casos: 412 aprovados, 3 ignorados, 0 falhados; 3206 asserções**     | `regressao-php.xml`; duração exibida 400,19 s; soma dos tempos JUnit 375,02 s, que exclui parte do overhead da execução |
| Testes JavaScript                                           | **54 aprovados; 0 falhados/ignorados**                                    | `unitarios-js.xml`; execução real do Node Test Runner                                                                   |
| TypeScript `npm run types:check`                            | **Aprovado**                                                              | Processo terminado com código 0, sem erros de tipos                                                                     |
| Pint, Larastan, formatação e build nesta entrega            | **A actualizar após a verificação final**                                 | Não confundir resultados anteriores do projecto com nova execução                                                       |
| Workflow GitHub Actions remoto                              | **Não executado nesta tarefa**                                            | Configuração preparada localmente; carece de publicação e consulta da execução                                          |
| SC01/SC02 com cliente do grupo                              | **Não homologados**                                                       | Cenários e critérios especificados; componentes automatizados não equivalem a assinatura de aceitação                   |
| MySQL isolado, carga, navegador/acessibilidade integral     | **Não executados**                                                        | Pendências explícitas de release; não foram simulados resultados                                                        |

Os subconjuntos E3 e componentes monetários estão incluídos na regressão PHP; **não somar 36 + 14 + 415 como se fossem testes únicos**. Analogamente, execuções repetidas para cobertura/gate não aumentam a quantidade de comportamentos cobertos.

As durações são observações desta máquina e incluem instrumentação Xdebug; não são benchmarks do serviço, tempos de resposta de utilizador ou prova de capacidade do Railway.

### 4.1. Casos ignorados

| Ficheiro/teste                                                                                        | Motivo e interpretação                                                                                                          |
| ----------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `Auth/AuthenticationTest.php`: `users with two factor enabled are redirected to two factor challenge` | 2FA não está activado; cenário condicionado. Ignorado, não aprovado                                                             |
| `Settings/SecurityTest.php`: `security page is displayed`                                             | Mesmo condicionamento de 2FA; não mede o cenário activo nesta configuração                                                      |
| `Settings/SecurityTest.php`: `security page renders without two factor when feature is disabled`      | O teste de ausência de 2FA também chama `skipUnlessFortifyHas`; condição inadequada para o seu próprio objectivo. Lacuna DEF-02 |

### 4.2. Distribuição e pirâmide

O E3 seleccionado contém 36 casos puros e não exige aplicação/BD. Os outros testes PHP misturam componentes de validação, HTTP, Eloquent, serviços transaccionais e um ensaio concorrente. O nome do directório `Unit` não prova isolamento: o teste concorrente efectua migrations e usa processos externos. Os 54 testes JS incluem lógica e apresentação de componentes, não um navegador real.

A suíte fornece forte regressão de componentes, mas não demonstra a proporção desejada da pirâmide. A recomendação é acrescentar testes pequenos de regras novas e limitar percursos completos aos de maior risco, preservando as regressões existentes. Não se eliminaram testes para produzir uma proporção artificialmente favorável.

## 5. Relatório de cobertura

| Âmbito                                | Linhas cobertas/total | Percentagem | Outros indicadores                                                                                  |
| ------------------------------------- | --------------------- | ----------- | --------------------------------------------------------------------------------------------------- |
| E3: `Money` + `DeliveryFeeAllocation` | 38/40                 | **95,00%**  | 3/5 métodos integralmente cobertos; 1/2 classes integralmente cobertas no relatório unitário        |
| E3: `DeliveryFeeAllocation`           | 28/28                 | **100,00%** | Validade das entradas, conservação da soma e distribuição de restos verificadas                     |
| E3: `Money`                           | 10/12                 | **83,33%**  | Linhas dos erros de conversão/multiplicação dependentes do Laravel não cobertas no subconjunto puro |
| Regressão global: `app/`              | 2648/2988             | **88,62%**  | 98 ficheiros, 95 classes; 240/328 métodos cobertos, cerca de 73,17%                                 |

Os dois caminhos monetários de validação excluídos da medição pura são exercitados pelos testes de componente. O denominador global inclui código de `app/` definido em `phpunit.xml`; não inclui o frontend React, dependências em `vendor` nem toda a infraestrutura/seeders. Não se apresenta esta cobertura PHP como cobertura do produto inteiro.

**Fórmula:** cobertura de linhas = linhas executáveis visitadas ÷ linhas executáveis instrumentadas × 100. Exemplo global: 2648 ÷ 2988 × 100 = 88,62%, arredondado a duas casas.

Os campos de condições do Clover aparecem a zero porque não foi pedida medição de branches/caminhos; isso **não significa 100% de branches**. Uma linha executada pode conter condições não exercitadas. Percentagem elevada não demonstra qualidade de oráculos, ausência de defeitos, resistência à carga ou correção de locks no MySQL.

Artefactos locais:

- [JUnit E3](evidencias/unitarios.xml), [Clover E3](evidencias/cobertura-unitaria.xml), [texto E3](evidencias/cobertura-unitaria.txt), [HTML E3](evidencias/cobertura-unitaria-html/index.html).
- [JUnit global PHP](evidencias/regressao-php.xml), [Clover global](evidencias/cobertura-global.xml), [texto global](evidencias/cobertura-global.txt), [HTML global](evidencias/cobertura-global-html/index.html).
- [JUnit JavaScript](evidencias/unitarios-js.xml), [diagnóstico monetário](evidencias/limites-monetarios.xml), [resumo de máquina](evidencias/resumo-execucao.json).

Os HTML de cobertura são gerados localmente e ignorados no Git; o workflow gera-os novamente e guarda-os em artefactos próprios. Os documentos e os XML/textos pequenos de evidência permanecem separáveis desses ficheiros gerados.

## 6. Defeitos identificados e estado

### DEF-01 — Intervalos monetários incompatíveis entre submissão e selecção

| Campo                | Registo                                                                                                                                                     |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Requisito/caso       | RF02, CT23; teste diagnóstico EXP01                                                                                                                         |
| Gravidade/prioridade | Média / P2: bloqueio de proposta numa partição extrema; não foi demonstrada perda de dinheiro                                                               |
| Ambiente/versão      | SQLite isolado; árvore de trabalho baseada em `c7d4dad`                                                                                                     |
| Pré-condições        | Fornecedor verificado, campanha em avaliação, disponibilidade/mínimo 1, transporte incluído, validade/reserva futuras                                       |
| Passos               | Submeter proposta com preço `10000000000.00`; obter proposta persistida; o organizador tenta seleccioná-la                                                  |
| Esperado             | Intervalo representável coerente durante todo o percurso; se não suportado, rejeitar previamente com erro de preço claro                                    |
| Observado            | Submissão sem erros; preço persistido; selecção recusada com erro `amount`; campanha permanece em avaliação e proposta submetida                            |
| Causa observada      | `ProposalValidation::rules` permite até `999999999999.99`; `Money::cents` aceita no máximo dez dígitos inteiros, até `9999999999.99`                        |
| Evidência            | `tests/Feature/MoneyValidationTest.php`, `EXP01 characterizes...`; JUnit monetário e global                                                                 |
| Estado               | **Reproduzido, pendente de correcção funcional**                                                                                                            |
| Recomendação         | Definir um intervalo monetário comum de domínio e alinhar validação, representação e BD; retestar limite, adjacentes e HTTP, sem alterar valores históricos |
| Responsável/reteste  | [membro responsável]; repetir CT23/CT24 e regressão após decisão/correcção                                                                                  |

**Importante:** EXP01 é um teste de caracterização do defeito actual: passa quando confirma a incompatibilidade. Não é um teste de aceitação que aprove a regra. Ao corrigir DEF-01, deve ser substituído/actualizado para exigir o comportamento de domínio acordado, mantendo o histórico do defeito.

### DEF-02 — Teste do cenário sem 2FA é ignorado quando 2FA está desactivado

- **Área:** `tests/Feature/Settings/SecurityTest.php`; gravidade média de cobertura, prioridade P2.
- **Observado:** o caso `security page renders without two factor when feature is disabled` chama o helper que o ignora se a funcionalidade estiver desactivada. Essa é precisamente a situação que pretende verificar.
- **Evidência:** código inspeccionado e registo de skip na regressão corrente; não foi inferida falha da página real.
- **Estado:** **pendente**. Não se activou 2FA nem se alterou autenticação para satisfazer o teste.
- **Acção proposta:** corrigir apenas a condição de execução do caso, executar a página sem 2FA e avaliar o resultado; preservar os outros testes condicionais.

### INF-01 — Workflow sem captura de evidências e sem execução em todos os pushes

- **Área:** `.github/workflows/tests.yml`; prioridade P2 para reprodutibilidade/entrega.
- **Antes:** workflow com filtro de branches, cobertura desactivada e sem upload de JUnit/Clover/HTML. Existia uma alteração local anterior para acrescentar `railway-deploy`, ainda insuficiente para «a cada push».
- **Correcção nesta tarefa:** eventos sem filtro de branch, E3 isolado e limiar 90%, regressão/global, testes JS explícitos, extensões/ambiente isolado e upload mesmo em falha, com acção fixada por SHA.
- **Estado:** **corrigido na configuração local**; execução remota ainda não comprovada. Não declarar pipeline remoto verde sem publicação e URL/artefacto de execução.

### INF-02 — Descoberta vazia dos testes E3 durante preparação

- **Observado:** uma primeira configuração com entradas `<file>` não descobriu testes no Pest instalado e devolveu código 0. A cobertura resultante era 0/40, não uma medição válida de testes aprovados.
- **Correcção:** usar descoberta de directório e exclusões explícitas para exemplo trivial/componentes/concorrência; confirmar 36 casos reais; acrescentar validação de mínimo dez no JUnit do CI.
- **Estado:** **corrigido e retestado localmente**. A evidência final E3 é 36/36 e 95%; a tentativa vazia é registada como problema de preparação, não escondida nem somada às aprovações.

### HIST-01 — Correcção anterior de eliminação de conta com histórico

A árvore de trabalho contém uma correcção anterior em `Settings/ProfileController.php`: verifica pedidos/ocorrências numa transacção e só termina a sessão após eliminação bem-sucedida. O relatório antigo da raiz documenta a reprodução anterior do erro de chave estrangeira. A presente regressão inclui os testes de perfil e passou com essa correcção local.

**Estado:** correcção local anterior preservada, não criada por esta tarefa; publicação/estado do Railway não verificados aqui. Não apresentar a presença de código corrigido no workspace como prova de que a produção foi actualizada.

## 7. Resultados por requisito e melhorias

| Requisito/tema       | Avaliação e acção                                                                                                                                                                       |
| -------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RF01                 | Casos normais, quantidades anómalas e prazo exercitados; extremos superiores, duplicação/reactivação exactas e combinações de autorização assinaladas na matriz como pendentes/parciais |
| RF02                 | Regressão de submissão, compromisso, duplicação e selecção aprovada; CT23 reprovado no domínio por DEF-01; fronteiras exactas adicionais pendentes                                      |
| RF03                 | Núcleo monetário e rateio aprovados; tarifas, snapshots, recuperação, reembolsos e privacidade exercitados; MySQL concorrente e cenário completo de aceitação pendentes                 |
| TDD                  | Não há certificação histórica completa de teste antes do código; guardar Red/Green/Refactor em futuras alterações legítimas                                                             |
| Pirâmide             | Aumentar isolamento das novas regras quando natural; conservar regressões de integração; não contar pasta `Unit` como prova automática de nível                                         |
| Cobertura            | Limiar E3 atingido; meta global proposta de 80% também atingida nesta medição; rever branches e oráculos, não apenas linhas                                                             |
| Testes de utilizador | Executar SC01/SC02, recolher evidências e obter decisão do cliente com nomes/datas; não substituir por aprovação do programador                                                         |

## 8. Limitações e ameaças à validade

1. SQLite não reproduz integralmente o motor, tipos, collations e locks do MySQL. O teste concorrente usa SQLite e só prova o ensaio realizado nesse motor.
2. Mocks/fakes validam contratos de integração e recuperação, não a disponibilidade real de um gateway ou a qualidade factual da IA.
3. Não há suíte de navegador real instalada/executada neste trabalho, nem ensaio sistemático de acessibilidade, navegadores/dispositivos ou rede lenta.
4. Não houve ensaio de carga/desempenho, restauro de backup ou compatibilidade MySQL nesta entrega. Os alvos no plano são propostas, não resultados.
5. A cobertura é PHP e de linhas, com denominadores diferentes no E3 e global. Não mede JavaScript, branches, caminhos ou todos os componentes de infraestrutura.
6. Três casos foram ignorados. Ignorado não equivale a aprovado; um deles tem condição inadequada.
7. O commit base não representa sozinho toda a árvore de trabalho testada. Existem alterações locais anteriores e novas; nenhuma publicação é implicitamente declarada.
8. O material UEM exige TDD no E3; a entrega demonstra quantidade/cobertura e distingue caracterização, mas não comprova proveniência TDD de todos os casos.
9. Critérios e cenários ainda carecem de validação e assinatura do cliente do grupo; os nomes dos membros permanecem campos editáveis.

## 9. Lições aprendidas e conclusão

Um processo pode terminar com código zero sem descobrir testes; por isso a contagem, cobertura e artefactos devem ser verificados. Reutilizar o mesmo limite monetário entre formulário e cálculo evita propostas formalmente aceites que não podem ser utilizadas. Testes verdes de caracterização não aprovam automaticamente requisitos de negócio. A classificação dos testes deve atender às dependências reais, não ao nome da pasta.

As evidências confirmam uma base de regressão ampla e um núcleo financeiro pequeno com cobertura elevada. A entrega suporta E1–E6 com uma reserva honesta sobre TDD e pendências de release/aceitação. O próximo passo funcional é resolver DEF-01 com requisito monetário coerente; depois corrigir a lacuna DEF-02, completar os casos pendentes, validar MySQL e executar a aceitação independente.

## 10. Lista de fecho e reprodução

- [x] Referência UEM lida e critérios E1–E6 mapeados.
- [x] Arquitectura e funcionalidades reais identificadas, incluindo limitações.
- [x] Três requisitos e 40 CT com partições/fronteiras/rastreabilidade documentados.
- [x] Mais de dez casos unitários de negócio executados; cobertura medida e separada da global.
- [x] Dois cenários e cinco critérios Dado/Quando/Então especificados.
- [x] Configuração CI com captura de resultados preparada.
- [ ] Confirmar execução remota do workflow após publicação.
- [ ] Comprovar TDD histórico integral ou obter aceitação académica da reserva.
- [ ] Corrigir DEF-01/DEF-02 e repetir os testes afectados.
- [ ] Executar casos exactos pendentes, MySQL, carga e aceitação com cliente.

Os comandos de execução estão no anexo do plano. O ficheiro `evidencias/resumo-execucao.json` permite conferir contagens e denominadores; os XML guardam nomes e resultados. A exportação formatada consolida o plano, a matriz, este relatório e o roteiro, preservando as reservas e os campos editáveis.

## 11. Exporta??o e notas das verifica??es finais

Foram preparados HTML e Word consolidados dos quatro documentos, e uma vers?o PDF a partir do HTML. Para regenerar HTML/Word, executar `python docs/testes/exportar_documentos.py`; depois abrir o HTML no navegador e imprimir para PDF respeitando os tamanhos de p?gina definidos por CSS (A4 e matriz em A4 horizontal). Regenerar as vers?es exportadas ap?s qualquer altera??o aos Markdown. A exporta??o utiliza apenas a biblioteca padr?o do Python; n?o acrescenta depend?ncias ? aplica??o.

A primeira verifica??o de formata??o detectou apenas o resumo JSON gerado sem o estilo do projecto. O ficheiro foi formatado e a verifica??o repetida aprovou os 201 ficheiros suportados. O build foi conclu?do; os avisos de tempo gasto em plugins s?o diagn?sticos de compila??o, n?o falhas dos testes nem medi??es de desempenho do servi?o.

Foi tamb?m executado um controlo negativo de cobertura: com 95% medidos e limiar tempor?rio `--min=100`, o processo devolveu c?digo 1, conforme esperado. Com `--min=90`, devolveu 0. Estes ensaios confirmam o comportamento do bloqueio de cobertura e n?o constituem Red/Green de TDD.

A inspec??o do hist?rico Git encontrou testes/regras de valida??o no import inicial `cdcdf63` e testes/implementa??o do rateio juntos em `5d969b3`. Esses commits n?o fornecem uma sequ?ncia aut?noma de teste primeiro e implementa??o depois. Por isso a reserva sobre proveni?ncia TDD mant?m-se.
