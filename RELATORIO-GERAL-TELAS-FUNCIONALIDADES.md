# Junta — Relatório geral das telas e funcionalidades

> **Levantamento actual — 11 de Novembro de 2026:** consultar [Fluxo actual do projecto](FLUXO-ACTUAL-DO-PROJECTO.md), com regras de negócio, ecrãs, permissões, rotas, navegação e limitações verificadas no código. As secções abaixo conservam o histórico das fases anteriores.

## Actualização — integração real de 29 de Outubro de 2026

Esta secção substitui as indicações anteriores de demonstração comercial e acesso livre. O inventário original abaixo fica como registo histórico da interface anterior.

A aplicação passou a ter **42 páginas React**. Foram acrescentadas **Ser organizador** (`/consumidor/candidatura-organizador`), **Candidaturas** (`/admin/candidaturas`) e **Histórico de operações** (`/admin/historico`).

### Implementado e ligado à base de dados

- Autenticação obrigatória nas áreas privadas e autorização no servidor por responsabilidade. O botão **Perfil** mostra apenas as áreas permitidas à conta; deixou de contornar o login.
- Candidatura em **Consumidor → Ser organizador**: nome, telefone, localidade e motivo. O envio fica pendente, sem conceder privilégios. Só um administrador decide em **Administração → Candidaturas**; a rejeição exige motivo. A aprovação acrescenta o acesso de organizador à mesma conta e preserva o de consumidor.
- Verificação de fornecedores separada da candidatura a organizador. O administrador associa opcionalmente uma conta existente ao fornecedor e controla a verificação. Apenas fornecedores verificados podem submeter propostas.
- Gestão administrativa de fornecedores, campanhas e propostas com criação, edição, remoção, validação e confirmações. Registos com histórico associado e propostas seleccionadas têm restrições de alteração/remoção. Utilizadores são consultados com os seus perfis; não foi implementada administração arbitrária das palavras-passe.
- Campanhas e propostas gravadas no servidor; participações únicas por conta/campanha, com quantidade agregada calculada a partir dos registos. O organizador só gere as suas campanhas, salvo autorização administrativa.
- Selecção humana de proposta, validada no servidor quanto à campanha, prazo, fornecedor e permissões. A selecção não conclui a compra nem escolhe automaticamente o menor preço.
- **Consultar** abre uma janela de detalhes. Os formulários e confirmações administrativos também usam janelas.
- Gráficos administrativos com distribuição das campanhas pelos estados existentes e contagem mensal de campanhas e propostas registadas. Sem números fictícios de vendas. Os relatórios de valor continuam a identificar estimativas baseadas no preço de referência; campanhas sem preço não são tratadas como valor zero.
- Histórico de operações administrativas e decisões, com actor e alterações. A decisão da candidatura gera uma notificação interna persistida; não envia email.
- Dados de contacto, preferências e perfil comercial persistidos. Notificações limitadas à conta proprietária. Os dados de demonstração anteriores não foram importados para a base real.
- Mantida a identidade verde e lima, os cartões, a adaptação móvel e o logótipo.

### Acesso inicial

A conta existente **lilianolicumba42@gmail.com** recebeu acesso de administrador. A palavra-passe não foi alterada. Após iniciar sessão com a sua palavra-passe habitual, pode abrir `/admin/dashboard` ou escolher **Administrador** no botão **Perfil**.

As oito migrações foram aplicadas à base local existente. Os testes de navegador usaram uma base SQLite separada, com contas e campanhas identificadas como testes; esses exemplos não foram inseridos na base real.

### Limites que permanecem

Não estão integrados pagamentos, reembolsos, execução logística, alertas automáticos de prazos/recolha, admissão pública de fornecedores, nem regras comerciais ainda não confirmadas no documento mestre. As preferências de alertas são guardadas, mas não activam esses envios. Não há edição/cancelamento de participação nem um fluxo completo de execução/encerramento. Não foi efectuado deployment.

A auditoria apresentada mostra os 100 eventos mais recentes; as listas actuais não têm paginação de servidor. A candidatura usa os dados necessários para avaliação manual, sem pontuação automática ou critérios documentais inventados.

### Validação desta integração

- Suite PHP: **53 testes aprovados, 3 ignorados, 266 asserções**.
- PHPStan/Larastan: **zero erros**, sem baseline ou supressões acrescentadas.
- TypeScript e compilação aprovados; formatação PHP e frontend aplicadas.
- Fluxo real no navegador confirmado: envio pendente → aprovação administrativa → acesso de consumidor e organizador na mesma conta.
- **31 verificações interactivas aprovadas** de CRUD, recusas de operações, modais, permissões e apresentação móvel, registadas em `storage/app/platform-browser-validation.json`.

---

## Registo histórico anterior à integração

> **Actualização visual — 29 de Outubro de 2026:** o layout aprovado foi aplicado às 39 páginas na variante verde, sem as superfícies pretas experimentais. Cabeçalhos, navegação, formulários, listagens e áreas de conta partilham agora verde-escuro, verde suave e lima. As funcionalidades e limitações comerciais descritas neste relatório mantêm-se; a alteração visual não acrescenta integrações de backend. Ver `REFATORACAO.md` para o registo da expansão e da validação.

> **Revisão de cartões e imagens:** foram reforçados os cartões de conteúdo, registos e condições, e adicionadas seis imagens ilustrativas locais às campanhas conhecidas da demonstração. Imagens reais fornecidas pelos dados continuam a ter prioridade. As capas geradas estão identificadas como ilustrativas e não representam fornecedores ou mercadoria confirmada.

**Data:** 29 de Outubro de 2026  
**Base da análise:** páginas React, rotas, componentes partilhados, armazenamento da demonstração e documento de refatoração existentes no projecto.  
**Âmbito:** inventário da implementação actual. Este relatório não altera funcionalidades nem transforma requisitos previstos em funcionalidades concluídas.

## 1. Visão geral

O Junta apresenta uma interface de compras colectivas organizada em área pública, conta e quatro áreas funcionais: consumidor, organizador, fornecedor e administrador.

Existem **39 ficheiros de página React**. Os detalhes de diferentes campanhas reutilizam a mesma página; por isso, o número de campanhas e de endereços possíveis não corresponde ao número de telas implementadas. Revisões de formulários, confirmações e detalhes expansíveis também podem existir dentro da mesma tela.

| Área                 | Número de telas | Finalidade principal                                          |
| -------------------- | --------------: | ------------------------------------------------------------- |
| Pública              |               5 | Explicar o produto e apresentar campanhas                     |
| Autenticação e conta |               9 | Criar e gerir a conta e permitir acesso autenticado           |
| Consumidor           |               7 | Descobrir oportunidades e acompanhar participações            |
| Organizador          |               6 | Estruturar campanhas, acompanhar procura e comparar propostas |
| Fornecedor           |               6 | Consultar oportunidades e preparar propostas                  |
| Administração        |               6 | Consultar registos e informação de acompanhamento             |
| **Total**            |          **39** |                                                               |

### Como interpretar o estado das funcionalidades

- **Integrada:** a interface utiliza operações existentes do servidor. Isto não significa que todos os serviços externos, como envio de email, estejam validados em produção.
- **Demonstração:** utiliza dados de exemplo. Quando há gravação, esta ocorre na sessão do navegador, sem persistência comercial no servidor.
- **Informativa:** apresenta explicações, condições ou informação de interface, sem executar uma operação de negócio.
- **Pendente:** o requisito não dispõe actualmente de tela, operação ou integração suficiente.

As áreas comerciais são de **demonstração**. A autenticação e as operações pessoais de conta dispõem de integração com o servidor. A separação visual dos perfis ainda não corresponde a um sistema de permissões por perfil no backend.

Os caminhos indicados abaixo são relativos ao endereço onde a aplicação está a correr. `{id}` identifica a campanha; `{token}` representa o token de recuperação de palavra-passe.

## 2. Área pública — 5 telas

| Nº  | Tela e caminho                              | Funcionalidades presentes                                                                                                                                                                                                                                                                                        |
| --- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | **Página inicial** — `/`                    | Apresentação das compras colectivas; acesso ao catálogo e à explicação do processo; destaque de até três campanhas activas de demonstração; acesso ao detalhe de cada campanha.                                                                                                                                  |
| 2   | **Catálogo de campanhas** — `/campanhas`    | Pesquisa por produto, descrição ou localização; filtro por categoria; ordenação por prazo ou proporção de procura reunida; contagem de resultados; cartões com estado, descrição, procura, participantes, prazo e localização; limpeza de filtros e estado sem resultados.                                       |
| 3   | **Detalhe da campanha** — `/campanhas/{id}` | Descrição, organizador, prazo, localização, modalidade, estado, procura reunida, objectivo, participantes e preço de referência por unidade; imagem quando fornecida pelos dados; encaminhamento para indicar quantidade ou iniciar sessão; aviso de campanha fechada e tratamento de identificador inexistente. |
| 4   | **Como funciona** — `/como-funciona`        | Explicação das etapas de escolha, indicação de quantidade, avaliação de propostas e acompanhamento; esclarecimento de que participar regista interesse e de que o menor preço não determina automaticamente a melhor proposta; acesso ao catálogo.                                                               |
| 5   | **Para fornecedores** — `/fornecedores`     | Explicação da avaliação de oportunidades e das condições comerciais a apresentar; indicação de que a adesão de novos fornecedores ainda não está disponível; ligação para iniciar sessão.                                                                                                                        |

**Estado:** páginas institucionais informativas e catálogo/detalhes alimentados pela demonstração.

No comportamento actual da interface, o catálogo inclui campanhas nos estados `ativa` e `em_avaliacao`. A acção de participação no detalhe exige estado `ativa` e prazo ainda aberto. Uma campanha visível no catálogo não está necessariamente aberta a novas participações.

## 3. Autenticação e conta — 9 telas

| Nº  | Tela e caminho                                          | Funcionalidades presentes                                                                                                                                                                                | Estado                                                         |
| --- | ------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------- |
| 6   | **Iniciar sessão** — `/login`                           | Email, palavra-passe, opção de manter sessão, recuperação de palavra-passe quando disponível, acesso ao registo, erros devolvidos pelo servidor e bloqueio do botão durante envio.                       | Integrada                                                      |
| 7   | **Criar conta** — `/register`                           | Nome, email, palavra-passe e confirmação; validação devolvida pelo servidor; limpeza das palavras-passe após sucesso; ligação para login. Não oferece atribuição de perfil de organizador ou fornecedor. | Integrada                                                      |
| 8   | **Recuperar palavra-passe** — `/forgot-password`        | Pedido de ligação de recuperação por email; mensagens de erro e de resultado; ligação para regressar ao login.                                                                                           | Integrada; entrega do email depende da configuração do serviço |
| 9   | **Redefinir palavra-passe** — `/reset-password/{token}` | Email associado ao pedido, nova palavra-passe e confirmação; envio do token ao servidor; validação e estado de processamento.                                                                            | Integrada                                                      |
| 10  | **Verificar email** — `/email/verify`                   | Instruções de verificação, reenvio da mensagem, indicação de reenvio e opção de terminar sessão.                                                                                                         | Integrada; entrega do email depende da configuração do serviço |
| 11  | **Início da conta autenticada** — `/dashboard`          | Saudação com o nome da conta; acesso a campanhas e à edição do perfil. É uma entrada geral da conta, sem atribuição automática de área por perfil.                                                       | Integrada à sessão; navegação para o catálogo de demonstração  |
| 12  | **Dados pessoais** — `/settings/profile`                | Consulta e alteração de nome e email; estado de verificação; reenvio de verificação; acesso à eliminação da conta com confirmação e palavra-passe; erros e processamento das operações.                  | Integrada                                                      |
| 13  | **Segurança** — `/settings/security`                    | Palavra-passe actual, nova palavra-passe e confirmação; validação no servidor; limpeza dos campos e reposicionamento do foco em caso de erro.                                                            | Integrada                                                      |
| 14  | **Aparência** — `/settings/appearance`                  | Informação de que o tema disponível nesta versão é claro. Não apresenta um selector de temas que não estejam efectivamente disponíveis.                                                                  | Informativa                                                    |

As definições partilham navegação para dados pessoais, contactos e alertas, segurança e aparência. **Contactos e alertas pertence à demonstração**, embora esteja acessível nessa navegação.

**Limitações:** não existe página válida de termos nem aceitação integrada; o registo não implementa o sistema funcional dos quatro perfis; não existe encaminhamento pós-login determinado por permissões de consumidor, organizador, fornecedor ou administrador. Não foram identificadas telas de autenticação de dois factores ou passkeys entre as 39 páginas.

## 4. Área do consumidor — 7 telas

| Nº  | Tela e caminho                                                              | Funcionalidades presentes                                                                                                                                                                                                                             |
| --- | --------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 15  | **Início do consumidor** — `/consumidor/dashboard`                          | Participações em curso com campanha, quantidade, localização e estado; acesso ao acompanhamento; até três notificações por ler; acesso ao catálogo e às listagens completas; estados sem participações ou actualizações.                              |
| 16  | **As minhas participações** — `/consumidor/campanhas`                       | Pesquisa por título da campanha; filtro entre todas, em curso e concluídas/canceladas; quantidade solicitada, localização, data de participação e estado; acesso ao acompanhamento e a novas campanhas.                                               |
| 17  | **Indicar quantidade** — `/consumidor/campanhas/participar?campaignId={id}` | Escolha de quantidade inteira positiva e observações opcionais; apresentação do preço de referência; revisão da quantidade e do valor de referência; possibilidade de voltar à edição; confirmação local e acesso às participações.                   |
| 18  | **Acompanhamento** — `/consumidor/acompanhamento/{id}`                      | Estado actual da campanha, procura reunida, objectivo, prazo, localização e orientação contextual; quantidade e estado da participação, data e observações; acesso às condições da campanha; tratamento de participação inexistente.                  |
| 19  | **Histórico de participações** — `/consumidor/historico`                    | Listagem e pesquisa de participações canceladas ou de campanhas concluídas/canceladas; quantidade, estado e data; acesso ao acompanhamento.                                                                                                           |
| 20  | **Notificações** — `/consumidor/notificacoes`                               | Filtros por todas, não lidas, sucesso e alertas; agrupamento em hoje, esta semana e anteriores; marcar todas como lidas, alternar leitura individual, abrir destino associado e eliminar; estado sem notificações.                                    |
| 21  | **Contactos e alertas** — `/consumidor/perfil`                              | Telefone, cidade/província e morada/bairro; preferências para novas propostas, fecho próximo e disponibilidade para recolha; gravação local com feedback; ligação para os dados reais da conta; informação de candidatura a organizador indisponível. |

**Estado:** demonstração. Com o modo temporário de pré-visualização activo, é possível confirmar uma participação sem login. O registo comercial continua a ser apenas local.

### Comportamento da participação

O formulário segue **preenchimento → revisão → confirmação local**. A campanha tem de estar activa e dentro do prazo. Ao confirmar, a demonstração adiciona uma participação pendente e incrementa a quantidade e o contador apresentados para a campanha.

Isto não executa pagamento, não reserva mercadoria no servidor e não confirma entrega. A contagem de pessoas únicas e a integridade da procura agregada necessitam de regras e persistência no backend.

A página de acompanhamento também possui o caminho `/consumidor/acompanhamento`, que reutiliza a mesma tela e apresenta a primeira participação disponível. O identificador no caminho detalhado é tratado pelo código como **identificador da campanha**.

**Não implementado nesta área:** candidatura a organizador, edição/cancelamento operacional de participações, pagamentos, comprovativos, reembolsos e histórico cronológico alimentado por eventos reais. As preferências de alertas não constituem um serviço de envio ou agendamento de notificações.

## 5. Área do organizador — 6 telas

| Nº  | Tela e caminho                                                   | Funcionalidades presentes                                                                                                                                                                                            |
| --- | ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 22  | **Início do organizador** — `/organizador/dashboard`             | Campanhas activas ordenadas por prazo; procura reunida e participantes; campanhas em avaliação com número de propostas e ligação para comparação; acesso à criação e à lista de campanhas.                           |
| 23  | **As minhas campanhas** — `/organizador/campanhas`               | Pesquisa por título, localização ou organizador; filtro pelos estados existentes; tabela com estado, procura/objectivo, participantes e prazo; acesso ao resumo e à criação.                                         |
| 24  | **Criar campanha** — `/organizador/campanhas/criar`              | Preenchimento do produto e condições; revisão; regresso à edição antes de guardar; gravação como rascunho local ou publicação na demonstração; confirmação e ligação para a campanha criada.                         |
| 25  | **Resumo da campanha** — `/organizador/campanhas/{id}`           | Descrição, estado, procura agregada, objectivo, participantes, prazo, localização, modalidade, número de propostas e data de actualização; navegação para participantes e propostas.                                 |
| 26  | **Participantes** — `/organizador/campanhas/{id}/participantes`  | Pesquisa por nome; filtros por todos, confirmados e pendentes; nome, contacto, quantidade, adesão, observações e estado; exportação CSV dos resultados filtrados; navegação entre resumo, participantes e propostas. |
| 27  | **Comparar propostas** — `/organizador/campanhas/{id}/propostas` | Comparação comercial lado a lado; estado de cada proposta; revisão e confirmação da selecção na demonstração quando a interface a disponibiliza; tratamento de campanha inexistente e ausência de propostas.         |

**Estado:** demonstração. A lista de campanhas ainda não é limitada pelo servidor às campanhas pertencentes ao utilizador autenticado.

### Campos da criação de campanha

- Nome da campanha, unidade de medida e objectivo de procura.
- Preço unitário de referência opcional.
- Prazo de participação e localização.
- Categoria.
- Descrição e condições de participação.
- Modalidade: recolha, entrega ou ambas.
- Contexto da procura opcional.

A interface valida campos essenciais, quantidade inteira positiva e prazo futuro. Não define uma quantidade mínima comercial independente do objectivo.

### Critérios da comparação

São apresentados **preço unitário, total declarado, quantidade disponível, cobertura da procura actual, prazo de fornecimento, modalidade, validade e observações**.

Não existe classificação automática nem selecção automática do preço mais baixo. O botão de revisão da selecção aparece para propostas submetidas/em avaliação, ainda válidas, quando a campanha está em avaliação. A confirmação local marca a proposta escolhida como aceite, as restantes da campanha como recusadas e a campanha como proposta seleccionada. Não conclui a compra.

Estas são condições da demonstração frontend, sem autorização por perfil nem máquina de estados comercial validada no servidor.

**Limitações:** a lista de participantes é parcial e contém exemplos apenas para a campanha 1; não é uma relação completa da procura agregada. Não existem edição de campanhas já guardadas, publicação real, comunicação operacional, acompanhamento real da execução ou encerramento administrativo integrados.

## 6. Área do fornecedor — 6 telas

| Nº  | Tela e caminho                                                        | Funcionalidades presentes                                                                                                                                                                                                                   |
| --- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 28  | **Início do fornecedor** — `/fornecedor/dashboard`                    | Até cinco oportunidades da demonstração com procura, localização e prazo; até quatro propostas associadas ao fornecedor de demonstração, com preço e estado; acesso às listagens.                                                           |
| 29  | **Oportunidades** — `/fornecedor/oportunidades`                       | Pesquisa por título ou localização; filtro por categoria; campanha, procura agregada, objectivo e prazo; acesso às condições.                                                                                                               |
| 30  | **Detalhe da oportunidade** — `/fornecedor/oportunidades/{id}`        | Produto/descrição, estado, procura actual, objectivo, localização, modalidade, organizador e prazo; acesso à preparação da proposta quando a interface permite; aviso de oportunidade encerrada ou inexistente.                             |
| 31  | **Preparar proposta** — `/fornecedor/propostas/criar?campaignId={id}` | Introdução de condições comerciais, revisão, correcção antes de confirmar, cálculo do total e gravação local; associação à campanha escolhida e ao fornecedor da demonstração.                                                              |
| 32  | **As minhas propostas** — `/fornecedor/propostas`                     | Filtragem pelo fornecedor de demonstração; pesquisa por campanha/fornecedor e filtro por estado; campanha, preço, quantidade e validade; detalhes expansíveis com total, prazo, modalidade, submissão e observações; acesso à oportunidade. |
| 33  | **Perfil comercial** — `/fornecedor/perfil`                           | Consulta e edição dos dados comerciais e de contacto; apresentação do estado de verificação e das categorias indicadas no perfil; gravação local e recuperação dos dados da sessão.                                                         |

**Estado:** demonstração. A filtragem das propostas do próprio fornecedor é feita no frontend; não substitui autorização no servidor. As oportunidades não dispõem de um mecanismo real de elegibilidade por fornecedor.

### Campos da proposta

- Preço unitário em meticais.
- Quantidade disponível na unidade da campanha.
- Prazo de fornecimento em dias.
- Data de validade.
- Modalidade: entrega, recolha ou ambas.
- Outras condições comerciais em texto livre.

O formulário valida preço positivo, quantidade e dias inteiros positivos e validade futura. Na criação, o total é calculado por **preço unitário × quantidade proposta**. A confirmação não envia a proposta a um organizador real.

Custos de transporte, taxas e condições de pagamento não têm campos estruturados próprios. Podem ser descritos nas observações, mas não entram automaticamente num cálculo completo do custo da compra.

### Campos do perfil comercial

Razão social, NUIT, pessoa de contacto, localização/armazém, email comercial, telefone e descrição da capacidade de fornecimento. As categorias e o estado de verificação são apresentados a partir dos dados existentes; não há aprovação comercial executável nesta tela.

**Não implementado nesta área:** adesão comercial real, verificação documental, edição/retirada/substituição de propostas, rascunho de proposta guardado entre sessões, pedidos de esclarecimento e histórico real de decisões. Os detalhes da proposta são expansíveis na lista; não existe uma página independente para esse detalhe.

## 7. Área administrativa — 6 telas

| Nº  | Tela e caminho                                   | Funcionalidades presentes                                                                                                                                                                                                                     |
| --- | ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 34  | **Início administrativo** — `/admin/dashboard`   | Campanhas em avaliação com organizador e estado; acesso a campanhas, utilizadores, fornecedores e propostas; indicação das operações ainda indisponíveis.                                                                                     |
| 35  | **Utilizadores** — `/admin/utilizadores`         | Pesquisa por nome/email; filtro pelos perfis indicados nos exemplos; nome, email, perfil e data de registo; contagem e estado sem resultados.                                                                                                 |
| 36  | **Campanhas da plataforma** — `/admin/campanhas` | Pesquisa por título, localização ou organizador; filtro por estado; campanha, procura/objectivo, participantes e prazo; detalhes expansíveis de organizador, descrição, referência de preço e modalidade.                                     |
| 37  | **Fornecedores** — `/admin/fornecedores`         | Pesquisa por empresa, NUIT ou localização; filtros verificado/por verificar; empresa, localização, NUIT, contacto e verificação; detalhes de telefone, categorias e descrição.                                                                |
| 38  | **Propostas comerciais** — `/admin/propostas`    | Consulta de propostas de todos os fornecedores dos exemplos; pesquisa e filtro por estado; campanha, fornecedor, preço, quantidade e validade; detalhes de total, fornecimento, submissão, observações e oportunidade.                        |
| 39  | **Relatórios** — `/admin/relatorios`             | Distribuição de volume estimado por categoria e região; campanhas com maior volume estimado; distribuição de alguns estados; série dos últimos seis meses; exportação CSV de resumos por categoria e região; tratamento de ausência de dados. |

**Estado:** consulta de demonstração. As listas de utilizadores e fornecedores usam exemplos definidos no frontend, não consultas administrativas à base de dados.

Nas campanhas, o nome abre o resumo existente na área do organizador; não existe um detalhe administrativo independente nem um conjunto separado de intervenções administrativas.

### Interpretação dos relatórios

O volume monetário apresentado resulta de **preço de referência × quantidade actual** dos dados de exemplo. Não representa vendas confirmadas, pagamentos recebidos, facturação ou liquidação financeira.

A série mensal agrupa campanhas pela data de criação e usa a procura actual dessas campanhas. Não constitui um histórico de transacções ou da evolução diária da procura. O CSV exporta resumos de categoria e região; não exporta todos os painéis da tela.

**Não implementado nesta área:** gestão de candidaturas a organizador, aprovação/rejeição de pedidos, atribuição de permissões reais, suspensão de utilizadores, verificação executável de fornecedores, intervenções administrativas sobre campanhas e histórico auditável de operações.

## 8. Funcionalidades partilhadas

| Funcionalidade        | O que existe actualmente                                                                                                 |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Identidade visual     | Logótipo existente, verde principal e escuro, lima em realces, superfícies claras e componentes partilhados              |
| Navegação por área    | Menus próprios para consumidor, organizador, fornecedor e administração; acessos à conta e à área pública                |
| Navegação móvel       | Menu lateral com abertura, gestão de foco e fecho por teclado nas áreas de trabalho; menu público adaptável              |
| Formulários           | Rótulos, agrupamento, revisão nos fluxos comerciais, validação frontend e mensagens do servidor nas operações integradas |
| Pesquisa e filtros    | Pesquisa local nos dados carregados, filtros e contagem; não constituem pesquisa/paginação de servidor                   |
| Tabelas               | Cabeçalhos, detalhes expansíveis em determinadas listas e deslocação horizontal em ecrãs pequenos                        |
| Estados visuais       | Rótulos textuais de estado, ausência de resultados e registos inexistentes em telas de detalhe                           |
| Carregamento          | Indicador de navegação; processamento nos formulários integrados; skeleton para carregamento dos perfis da demonstração  |
| Exportações           | CSV dos participantes filtrados e dos resumos administrativos de categoria/região                                        |
| Conta                 | Definições e terminação de sessão pelos mecanismos existentes                                                            |
| Aviso de demonstração | Identificação dos dados de exemplo e do armazenamento local nas áreas correspondentes                                    |

Não existe um tratamento operacional completo de erros de comunicação, autorização e gravação para os módulos comerciais, porque estes ainda não executam pedidos comerciais ao servidor.

## 9. Estados representados nos dados

| Entidade     | Estados definidos no frontend                                                                            |
| ------------ | -------------------------------------------------------------------------------------------------------- |
| Campanha     | Rascunho, activa, em avaliação, proposta seleccionada, confirmada, em fornecimento, concluída, cancelada |
| Participação | Pendente, confirmada, cancelada                                                                          |
| Proposta     | Rascunho, submetida, em avaliação, aceite, recusada, expirada                                            |

Ter um estado definido no TypeScript ou disponível num filtro não significa que exista uma operação para o atingir. Por exemplo, a interface não implementa actualmente todos os passos de confirmação, fornecimento e conclusão de uma campanha.

## 10. Persistência, acesso e limitações transversais

### Dados reais de conta

Registo, login, recuperação/verificação de email, actualização de nome/email, alteração de palavra-passe e eliminação da conta utilizam os mecanismos de servidor existentes. A entrega de emails e a experiência autenticada completa dependem da configuração e validação do ambiente.

### Dados comerciais de demonstração

Campanhas, participações, propostas, notificações e perfis de demonstração utilizam exemplos e `sessionStorage`. As alterações não são partilhadas como operações reais entre consumidores, organizadores, fornecedores ou dispositivos. A sessão de demonstração também não é separada por identidade de conta no servidor.

Nem todas as listas administrativas usam o mesmo armazenamento: utilizadores e fornecedores administrativos são exemplos estáticos. Alterar um perfil de fornecedor na demonstração não garante actualização correspondente nessa lista administrativa.

### Permissões

As rotas das quatro áreas ainda não têm protecção por perfil. Existe autenticação para a conta e definições, mas não um sistema real dos quatro perfis de negócio. O botão flutuante **Perfil** aparece também na versão compilada enquanto o modo temporário de demonstração está activo e permite abrir as quatro áreas sem login; não atribui permissões reais. A configuração está em `resources/js/lib/demo-mode.ts`.

### Pontos de revisão identificados

- Algumas mensagens ainda contêm `?` onde deveriam existir caracteres acentuados, por exemplo em textos de participação e de detalhes de campanha. Necessitam de revisão textual; não foi feita alteração de código nesta tarefa de relatório.
- Preferências de alerta, estados comerciais e verificação de fornecedores são demonstrativos; não devem ser interpretados como automatizações ou aprovações de produção.
- O total declarado nas propostas existentes deve ser lido com o seu contexto. Não há garantia de comparabilidade integral de custos ou de inclusão de transporte e outros encargos.

## 11. Requisitos previstos ainda pendentes

| Requisito                                           | Situação actual                                                                               |
| --------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Candidatura a organizador                           | Sem formulário próprio, persistência, avaliação ou comunicação de decisão                     |
| Aprovação na mesma conta e acumulação de permissões | Sem implementação de perfis/permissões de negócio no servidor                                 |
| Elegibilidade e verificação de fornecedores         | Sem critérios implementados e sem processo de aprovação real                                  |
| Gestão e edição operacional de campanhas            | Criação demonstrativa disponível; edição posterior e restrições por fase pendentes            |
| Prazo independente para propostas                   | Modelo actual utiliza o prazo da campanha; regra específica pendente                          |
| Participação com integridade comercial              | Sem persistência no servidor, prevenção de duplicados e contagem de pessoas únicas garantidas |
| Alterar ou cancelar participação                    | Sem fluxo operacional e sem regras de fases confirmadas                                       |
| Selecção comercial de proposta                      | Apenas local; sem autorização real, auditoria ou eventual aprovação administrativa            |
| Execução e encerramento da campanha                 | Sem operações completas de execução, conclusão, cancelamento e tratamento de insucesso        |
| Pagamentos e reembolsos                             | Sem fluxo e sem integração                                                                    |
| Entrega e levantamento                              | Modalidade e informações presentes; execução logística não integrada                          |
| Notificações reais e histórico                      | Sem eventos comerciais, envios e histórico operacional integrados                             |
| Administração e auditoria                           | Consulta de exemplos; decisões e histórico auditável pendentes                                |
| Termos e aceitação                                  | Sem página válida e mecanismo integrado de aceitação                                          |

As regras de quantidade mínima, aprovação de campanhas, selecção de propostas, compromisso financeiro, fornecedores elegíveis e intervenções administrativas continuam dependentes de decisões confirmadas. Não são consideradas resolvidas pela existência das respectivas telas.

## 12. Validação disponível e alcance deste relatório

O registo da refatoração anterior indica aprovação de compilação, TypeScript e lint; 28 testes passaram e 3 foram ignorados. Regista também 84 verificações de apresentação em computador/telemóvel e 11 verificações interactivas de demonstração.

Essas verificações não comprovam todos os requisitos de negócio. Alguns destinos protegidos foram verificados apenas pelo redireccionamento para login, e os fluxos comerciais continuam locais.

**Na elaboração original deste relatório foi efectuada uma leitura do código, sem alterações à aplicação. Posteriormente foi activado o acesso temporário de demonstração sem login e reposto o botão flutuante Perfil, a pedido do utilizador.**

### Fontes no projecto

- `resources/js/pages/`: inventário das 39 telas.
- `resources/js/layouts/`: navegação pública, de conta e por perfil.
- `resources/js/components/junta/`: componentes de apresentação e interacção partilhados.
- `resources/js/hooks/use-prototype-store.ts`: armazenamento e recuperação da demonstração.
- `resources/js/data/demo.ts`: exemplos comerciais.
- `resources/js/types/junta.ts`: entidades e estados do frontend.
- `routes/web.php` e `routes/settings.php`: caminhos e protecção actualmente definida.
- `config/fortify.php`: funcionalidades de autenticação habilitadas.
- `REFATORACAO.md`: âmbito e registo da validação anterior.
