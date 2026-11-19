# Junta — demonstração reproduzível da compra actual

Preparação de 12 de Novembro de 2026, referência de Maputo. Base: [fluxo actual](FLUXO-ACTUAL-DO-PROJECTO.md), [PurchaseJourneyTest.php](tests/Feature/PurchaseJourneyTest.php) e [CollectivePurchasePilotSeeder.php](database/seeders/CollectivePurchasePilotSeeder.php).

Esta demonstração mantém **interesse → selecção → confirmação do fornecedor → pagamentos → apuramento → fornecimento/reembolso**. Usa exclusivamente `TestPaymentGateway`. Não implementa o piloto proposto de divulgar uma oferta já confirmada, nem pagamentos reais.

## Preparar e iniciar

Pré-requisitos: PHP 8.5 com SQLite, Node instalado, dependências PHP/JS existentes e `.env` local com `APP_KEY`. O lançador não modifica `.env`, dependências ou a base configurada da aplicação. Usa as migrações actuais. Execute na raiz do repositório:

```powershell
# Uma vez, ou depois de alterar o frontend. Termine antes o Vite, se estiver activo.
npm.cmd run build

# Recria apenas storage/app/purchase-demonstration.sqlite.
node demonstracao-junta.mjs preparar

# Manter este terminal aberto. Ctrl+C termina os dois servidores.
node demonstracao-junta.mjs servir
```

Entrada: [catálogo da demonstração](http://127.0.0.1:8786/campanhas). A porta 8786 serve os assets e encaminha as páginas para o Laravel na porta 8787; ambas estão limitadas a `127.0.0.1`. Não iniciar outra aplicação nestas portas. O lançador verifica o manifest e recusa um `public/hot` activo. Os logs do servidor ficam em `storage/logs/purchase-demonstration-server.log`.

Use um perfil de navegador reservado para a sessão. Para comprador e organizador em simultâneo, use **dois perfis separados**, ou uma janela normal e outra privada. Duas janelas privadas do mesmo perfil podem partilhar a sessão. Também pode terminar sessão e entrar com a outra conta; recarregue sempre a página da respectiva área.

O lançador fixa o ambiente `local`, o prestador `test`, `PURCHASE_TEST_ENABLED=true`, correio em memória, fila síncrona e IA desactivada. Sessões e cache usam a base exclusiva; o cookie de sessão tem nome próprio. Usa um caminho de configuração em cache exclusivo, que deve estar ausente. Não exige limpar o cache da aplicação principal. O comando Artisan recusa produção e prestador indisponível antes de repor dados e escolhe internamente a sua própria ligação SQLite, sem aceitar um caminho fornecido pelo operador.

## Contas e estado inicial

Palavra-passe **apenas destas contas fictícias nesta base**: `Junta-Piloto!2026`.

| Conta                          | Utilização                                                                               |
| ------------------------------ | ---------------------------------------------------------------------------------------- |
| `consumidor@pilot.junta.test`  | Participante: 3 sacos de arroz e 2 de feijão, ainda sem pedidos                          |
| `organizador@pilot.junta.test` | Facilitador: apurar, receber, separar e registar recolha                                 |
| `fornecedor@pilot.junta.test`  | Fornecedor verificado; a preparação abre as condições em seu nome pelos serviços actuais |
| `admin@pilot.junta.test`       | Consulta administrativa opcional; não necessária para as tarefas propostas               |
| `consumidor2@pilot.junta.test` | Conta sem participação nestas duas campanhas; não a usar para acrescentar pagamentos     |

O comando reutiliza o seeder do piloto numa base vazia e dá nomes de produto às campanhas, para não antecipar o resultado no título. Não altera o seeder original nem os seus registos noutras bases.

| Cenário técnico | Campanha e referência                          | Depois de preparar                               | Depois de abrir                                                 |
| --------------- | ---------------------------------------------- | ------------------------------------------------ | --------------------------------------------------------------- |
| `success`       | `[DEMO] Arroz em sacos de 5 kg`, `JNT-000001`  | Activa, interesse, 3 sacos, uma oferta submetida | Pagamentos abertos, mínimo 3; pedido e cobrança ainda por criar |
| `refund`        | `[DEMO] Feijão em sacos de 5 kg`, `JNT-000002` | Activa, interesse, 2 sacos, uma oferta submetida | Pagamentos abertos, mínimo 3; pedido e cobrança ainda por criar |

Estes IDs são garantidos **só após `preparar` nesta base dedicada**. O resultado depende de manter as quantidades e de apenas a conta indicada pagar. O objectivo visual de interesse é 10; não é o mínimo comercial de 3 unidades pagas.

## Condução pelo facilitador

Entregue somente [GUIAO-PARTICIPANTE-JUNTA.md](GUIAO-PARTICIPANTE-JUNTA.md) e as credenciais do consumidor. Não entregue a tabela de respostas abaixo. Peça que pense em voz alta. Não indique menus, botões ou rotas durante a tarefa; se ajudar, registe exactamente o que disse. A pessoa já tem participações para que possa chegar ao pagamento no fluxo actual, sem criar contas ou aprovações durante o exercício.

**Antes de abrir qualquer cenário:** deixe a pessoa procurar as duas campanhas no catálogo público. Pode explorar a participação, mas deve manter 3 sacos de arroz e 2 de feijão. A referência pública é estimativa, não o valor contratual. Não peça que conclua o preço final antes da confirmação comercial.

**Abrir o arroz**, num segundo terminal, quando a pessoa estiver pronta para consultar as condições:

```powershell
node demonstracao-junta.mjs abrir --scenario=success --minutes=15
node demonstracao-junta.mjs estado
```

O comando fecha o interesse, selecciona a oferta existente, confirma-a pela conta do fornecedor e abre pagamentos pelo organizador, usando `CollectivePurchase` e `ProposalSelection`. Não emite pedidos, não paga, não apura e não marca entregas. Esta preparação substitui apenas as tarefas dos dois operadores antes da tarefa do participante; os testes de percurso cobrem os endpoints correspondentes.

Anuncie: «As condições da sua compra estão disponíveis. Pode continuar.» A pessoa pode precisar de actualizar a página: não há sincronização automática geral. Após a abertura, a campanha sai do catálogo público segundo a regra actual. A retoma faz-se na participação existente, nas notificações ou no acompanhamento da conta. Um detalhe público antigo pode devolver 404; isso não significa que a participação desapareceu.

Para conferência técnica do facilitador, não como instrução ao participante:

| Etapa                                   | Arroz                             | Feijão                            |
| --------------------------------------- | --------------------------------- | --------------------------------- |
| Detalhe público antes da abertura       | `/campanhas/1`                    | `/campanhas/2`                    |
| Detalhe interno da própria participação | `/consumidor/campanhas/1`         | `/consumidor/campanhas/2`         |
| Destino estável do comprador            | `/consumidor/acompanhamento/1`    | `/consumidor/acompanhamento/2`    |
| Operação do organizador                 | `/organizador/campanhas/1/compra` | `/organizador/campanhas/2/compra` |

A conta do consumidor aceita as condições, emite o pedido, inicia o pagamento e usa o simulador disponível no acompanhamento para **confirmar sucesso**. Iniciar pagamento sozinho deixa uma tentativa pendente: confirme no simulador antes do prazo. Não forneça estes passos à pessoa, salvo como ajuda registada. No prestador de teste não se introduzem telefone, PIN ou credenciais financeiras reais.

**Apurar o arroz:** aguarde o prazo real impresso por `estado` (hora de Maputo). Entre como organizador, abra a operação da campanha, actualize e use o apuramento após o prazo. A interface bloqueia o apuramento antecipado. Com os 3 sacos pagos, emite ordem de fornecimento de 3 sacos. Registe recepção **acumulada de 3**, referência `DEMO-ARROZ-01` e motivo `Mercadoria fictícia conferida para a sessão`. Separe o pedido pago. Não foi recebida mercadoria real.

Anuncie apenas: «A mercadoria desta simulação chegou. Pode continuar a tarefa de recolha.» O comprador deve encontrar o ponto e o seu código privado. O organizador recebe o código apresentado pelo comprador e usa-o para confirmar a recolha. **Não existe um botão de confirmação autónoma do comprador**; a participação do comprador é apresentar o código e verificar o estado recebido. Depois do uso, o código deixa de estar disponível. A campanha pode continuar em fornecimento: a conclusão administrativa/financeira global está fora deste exercício.

**Abrir o feijão** só quando começar o exercício B, para não consumir o prazo enquanto decorre A:

```powershell
node demonstracao-junta.mjs abrir --scenario=refund --minutes=15
node demonstracao-junta.mjs estado
```

Depois do pagamento simulado de 2 sacos e do prazo real, o organizador actualiza a operação e apura. Não pague com mais contas. Anuncie apenas: «O apuramento desta compra terminou. Pode consultar o resultado.» Como 2 é inferior ao mínimo de 3, a compra é cancelada e o prestador de teste confirma a devolução integral. O comprador consulta o mesmo acompanhamento, com valor e referência de reembolso. Não há saída para recolha nem segunda cobrança.

O prestador actual confirma o reembolso normalmente na própria operação de apuramento; pode não se conseguir observar uma espera em `refund_pending`. Essa espera tem cobertura em `PurchaseJourneyTest.php`, com resposta controlada do prestador. Não a apresentar como uma espera real observada nesta demonstração.

**Tempo de sessão:** cada janela começa no respectivo comando `abrir`. O valor por omissão é 15 minutos; pode escolher de 1 a 120. Para sessões mais lentas, escolha 30 minutos antes de abrir. Na verificação técnica pode usar uma janela curta, mantendo o pagamento e a simulação antes do prazo. Entre o pagamento e o apuramento, use o tempo para recolher a explicação da pessoa. Não altere relógios, prazos guardados ou estados para acelerar. Se o prazo terminar antes da cobrança, registe a interrupção e reponha a sessão.

## Respostas esperadas — só para o facilitador

Condições fictícias fixadas na abertura: 100,10 MZN por saco, capacidade 10, mínimo 3, comissão 2,50% sobre mercadoria, transporte ao ponto 10,00 MZN por saco, entrega não disponibilizada, fornecimento previsto em 3 dias após confirmação. Recolha em **Ponto Junta de demonstração, Maputo (local fictício)**.

| Parcela                          | Arroz: 3 sacos                      | Feijão: 2 sacos                                                     |
| -------------------------------- | ----------------------------------- | ------------------------------------------------------------------- |
| Mercadoria                       | 300,30 MZN                          | 200,20 MZN                                                          |
| Comissão, arredondada ao cêntimo | 7,51 MZN                            | 5,01 MZN                                                            |
| Transporte ao ponto              | 30,00 MZN                           | 20,00 MZN                                                           |
| Entrega                          | 0,00 MZN                            | 0,00 MZN                                                            |
| **Total pago no simulador**      | **337,81 MZN**                      | **225,21 MZN**                                                      |
| Unidades pagas no apuramento     | 3; avança para fornecimento         | 2; mínimo falha                                                     |
| Resultado do exercício           | Pedido recebido; código inutilizado | Pedido reembolsado: **225,21 MZN**, incluindo comissão e transporte |

Pagar não significa que a mercadoria já chegou. O fornecimento depende do mínimo de unidades pagas no fim do prazo, sem tentativas incertas. A recolha depende ainda da recepção física registada e separação pelo organizador.

## Repor depois de cada sessão

1. Guarde a ficha da sessão fora da base descartável, sem guardar códigos privados ou palavras-passe de outras contas.
2. Termine o servidor com Ctrl+C e feche os navegadores da sessão, para não haver pedidos durante a reposição.
3. Execute `node demonstracao-junta.mjs preparar`. Elimina e recria as tabelas **só** de `storage/app/purchase-demonstration.sqlite`, incluindo sessões, pagamentos e códigos. Recria contas, palavras-passe, quantidades e prazos de interesse futuros. Repor apaga os dois exercícios; não é uma continuação de sessão.
4. Confirme `node demonstracao-junta.mjs estado`: duas campanhas em `interest`, sem prazo de pagamento. Inicie `servir`, volte a entrar e confirme 3 sacos de arroz/2 de feijão, sem pedidos. Abra cada cenário apenas quando necessário.

Reexecutar apenas o seeder antigo **não repõe** progresso. Não usar `migrate:fresh` directamente contra a configuração normal, nem copiar por cima de `database/pilot.sqlite`. A base normal e a anterior pré-visualização não são o destino deste lançador.

Se houver tentativa pendente depois do prazo, não forçar o mínimo: conferir a tentativa; uma confirmação tardia pode provocar reembolso. Para voltar ao exercício planeado, reponha. A demonstração não executa scheduler em segundo plano: o facilitador faz o apuramento e as actualizações explicitamente.

<a id="ficha-de-observacao"></a>

## Ficha de observação

**Por preencher em sessões com participantes.** Uma linha por hesitação, interpretação ou ajuda; copie a ficha por pessoa. Não converter a verificação técnica abaixo em resultados de usabilidade.

Código do participante: __________ Data/hora: __________ Facilitador: __________ Dispositivo: __________

Familiaridade com compras colectivas: __________ Janela de pagamento usada: __________

| Exercício / tarefa                     | Conseguiu concluir? | Ponto exacto em que hesitou | Interpretação / palavras da pessoa | Precisou de ajuda? Qual? | Tempo / observações |
| -------------------------------------- | ------------------- | --------------------------- | ---------------------------------- | ------------------------ | ------------------- |
| A1 — encontrar e interpretar interesse |                     |                             |                                    |                          |                     |
| A2 — explicar total e avanço da compra |                     |                             |                                    |                          |                     |
| A3 — pagamento simulado e significado  |                     |                             |                                    |                          |                     |
| A4 — ponto, código e recolha           |                     |                             |                                    |                          |                     |
| B1 — encontrar e explicar condições    |                     |                             |                                    |                          |                     |
| B2 — pagamento simulado                |                     |                             |                                    |                          |                     |
| B3 — apuramento e devolução            |                     |                             |                                    |                          |                     |

Informação que faltou: __________

Expectativa diferente do resultado: __________

Problema técnico / interrupção / decisão comercial levantada: __________

## Decisões de demonstração e limites comerciais

Preço, comissão, mínimo, prazo de pagamento, três dias de fornecimento e ponto fictício são **dados do exercício**, não decisões comerciais para lançamento. A sessão pode ser executada com estes dados. Antes de uma operação real faltaria decidir os valores comerciais, um ponto real com horários e responsável, regras operacionais de transporte e a janela efectiva de pagamento. Isso não bloqueia a preparação fictícia.

A confirmação pelo código é a implementação actual, sem prova automática de transporte. Uma mercadoria marcada recebida neste exercício é apenas uma declaração de teste. Não se simula a liquidação ao fornecedor nem a conclusão global; não confundir «pedido recebido» com «campanha financeiramente concluída».

## Verificação técnica desta entrega

Executada em **Microsoft Edge headless**, por interacção com os formulários e ligações da aplicação, em 1440 × 1050, tema escuro. Os operadores trocaram de conta com terminar sessão/login. A abertura inicial das condições foi feita pelos comandos documentados; as cobranças, simulações, apuramentos, recepção, separação e recolha foram feitos pelos formulários no navegador. Usaram-se janelas reais de **3 minutos por cenário**, sem alterar o relógio nem editar prazos na base. Não se tratou de uma sessão com participantes.

| Percurso / conferência                              | Resultado observado em 12/11/2026                                                                                                                                                                                                                      |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Catálogo → arroz → entrada autenticada              | Campanha pública activa, 3 sacos de interesse; login regressou a `/consumidor/campanhas/1`; não havia pedido nem cobrança                                                                                                                              |
| Condições → pedido → simulador, arroz               | Parcelas 300,30 + 7,51 + 30,00 = **337,81 MZN**; emissão do pedido, tentativa e simulação aprovada; a conta viu «Pagamento confirmado: aguarda apuramento»                                                                                             |
| Apuramento do arroz, depois das 13:40:34 de Maputo  | 3 unidades pagas, mínimo 3; ordem de fornecimento de 3; separação bloqueada enquanto a quantidade recebida era zero                                                                                                                                    |
| Recepção e separação                                | Organizador registou 3 unidades acumuladas com a guia `DEMO-ARROZ-01`; separou o pedido; ficou disponível o formulário para registar o código apresentado pelo consumidor                                                                              |
| Recolha                                             | Comprador encontrou ponto fictício e código de 12 caracteres; organizador introduziu esse código; comprador viu **«Pedido recebido»**, sem código; `receipt_status=received`, código limpo na base                                                     |
| Encontrar feijão na conta                           | Ligação da lista de participações abriu `/consumidor/acompanhamento/2`, com 2 sacos e sem cobrança; após abertura, o acompanhamento mostrou as condições e o total                                                                                     |
| Condições → pedido → simulador, feijão              | Parcelas 200,20 + 5,01 + 20,00 = **225,21 MZN**; cobrança simulada confirmada antes do prazo; detalhe interno mostrou preço confirmado e ligação de retoma                                                                                             |
| Apuramento do feijão, depois das 13:47:03 de Maputo | 2 unidades pagas, mínimo 3; fase `cancelled`, pedido `refunded`, nenhuma ordem de fornecimento; devolução de **22521 cêntimos** confirmada pelo prestador de teste                                                                                     |
| Notificação → acompanhamento do feijão              | Ligação abriu a campanha 2; comprador viu **«Reembolso confirmado»**, «Devolução confirmada», referência e **225,21 MZN**; sem botão para pagar nem código de recolha                                                                                  |
| Reposição e reinício pelos mesmos comandos          | Duas campanhas de novo em `interest`, 5 contas, quantidades 3 e 2, zero pedidos/tentativas/reembolsos. A sessão antiga deixou de dar acesso ao acompanhamento; as duas campanhas reapareceram no catálogo e o detalhe público do feijão voltou a abrir |

O acompanhamento é estável por **ID de campanha**, tanto antes como depois do pagamento. Foram também conferidas as ligações da lista de participações, detalhe interno e notificações. A reposição final deixou a base no estado inicial e o servidor de demonstração disponível na porta 8786; a próxima abertura volta a calcular um prazo futuro. Se o processo já tiver terminado, execute `servir` novamente.

Evidências locais: [registo técnico das etapas](storage/app/purchase-demonstration-browser.json), [pedido recebido](storage/app/purchase-demonstration-captures/09-success-received-buyer.png), [devolução confirmada](storage/app/purchase-demonstration-captures/13-refund-buyer.png) e [catálogo depois de repor](storage/app/purchase-demonstration-captures/14-reset-catalogue.png). As capturas intermédias estão na mesma pasta. São artefactos locais em `storage`, não uma suite de navegador incorporada no Pest; podem não acompanhar uma cópia limpa do repositório. O guião, lançador, comando e testes são os ficheiros reproduzíveis da entrega.

Não se encontrou um defeito da aplicação que bloqueasse estes dois percursos. Não foi necessário alterar regras de negócio, endpoints, frontend ou dependências. O servidor de apoio serve assets estáticos separadamente para manter a execução local em Windows consistente; a aplicação continua a processar todas as mutações no Laravel. Não foram registadas excepções da aplicação nas capturas CDP dos percursos concluídos; os logs antigos de outras portas não foram usados como resultado desta sessão.

Validação automática executada após as alterações:

- `php artisan test --compact tests/Feature/PurchaseDemonstrationTest.php tests/Feature/PurchaseJourneyTest.php`: **16 testes aprovados, 438 asserções**.
- Nova verificação dos 6 casos da demonstração após formatação: **6 aprovados, 43 asserções**. Cobre reposição, isolamento da ligação normal, credenciais/sessões, dois resultados comerciais, recusa em produção/prestador desactivado e opções inválidas.
- `php artisan test --compact`: **206 aprovados, 3 ignorados, 1891 asserções**.
- PHPStan: **zero erros**; Pint aplicado; `vp check --fix demonstracao-junta.mjs`: formatação e lint aprovados; `node --check demonstracao-junta.mjs`: aprovado.

Os testes usam bases temporárias próprias, não a demonstração aberta no navegador. O ficheiro original `database/pilot.sqlite` conservou tamanho de 593920 bytes e data de alteração de 11/11/2026 às 08:43:58; não foi alvo da preparação ou da reposição. A conferência final foi feita por consulta de leitura à base dedicada.

**Resultados de participantes: por preencher.** Estes resultados técnicos não demonstram que uma pessoa nova compreendeu os estados, preços ou transições sem ajuda. Essa observação é o objectivo da ficha e do guião.
