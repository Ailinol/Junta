# Refatoração da interface Junta

## Actualização — integração real de 29 de Outubro de 2026

Esta secção substitui as indicações anteriores de demonstração comercial e acesso livre. O inventário original abaixo fica como registo histórico da interface anterior.

A aplicação passou a ter **42 páginas React**, incluindo as novas telas de candidatura, avaliação administrativa de candidaturas e histórico de operações.

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

## Âmbito

As 39 páginas React existentes foram revistas e alteradas. Foram preservados o Laravel 13, Inertia 3, React 19, Tailwind 4, Fortify, Wayfinder, as rotas e os contratos do servidor. Não foram alterados PHP, base de dados ou dependências.

| Área          | Telas abrangidas                                                                                                 |
| ------------- | ---------------------------------------------------------------------------------------------------------------- |
| Pública       | Inicial, catálogo, detalhe da campanha, como funciona, fornecedores                                              |
| Conta         | Login, registo, recuperação, redefinição, verificação de email, início autenticado, perfil, segurança, aparência |
| Consumidor    | Início, participações, participação com revisão, acompanhamento, histórico, notificações, contactos e alertas    |
| Organizador   | Início, campanhas, criação com revisão, resumo, participantes e exportação, comparação de propostas              |
| Fornecedor    | Início, oportunidades, detalhe, preparação e revisão da proposta, propostas e detalhes, perfil comercial         |
| Administração | Início, utilizadores, campanhas, fornecedores, propostas, relatórios e exportação                                |

## Organização implementada

- Acesso temporário aos perfis de demonstração pelo botão flutuante **Perfil**, disponível também na versão compilada. A participação local pode ser confirmada sem login enquanto `demoMode` estiver activo em `resources/js/lib/demo-mode.ts`; as operações reais da conta continuam protegidas.
- Navegação própria por perfil, menu móvel com gestão de foco e acesso à conta.
- Página pública mais curta; catálogo com pesquisa, categorias e ordenação.
- Listas operacionais com pesquisa, filtros, estados textuais e detalhes consultáveis.
- Participações apresentadas como interesse e quantidade; preço de referência identificado como estimativa.
- Formulários de campanha e proposta agrupados por finalidade, com revisão antes da confirmação.
- Comparação lado a lado de preço unitário, total declarado, quantidade, cobertura da procura, prazo, modalidade, validade e observações. Sem classificação automática por preço.
- Identificadores da rota respeitados nos detalhes, acompanhamento, participantes e comparação; registos inexistentes têm estado próprio.
- Indicadores e textos repetidos reduzidos; fotografias genéricas de categorias retiradas das listagens operacionais; logótipo existente preservado.
- Campos de registo alinhados com os dados efectivamente aceites pelo Fortify, sem escolha de perfil ou ligações para termos inexistentes.

## Limitações verificadas no projecto

Campanhas, participações, propostas, notificações e perfis comerciais são uma demonstração com dados de exemplo e armazenamento de sessão no navegador. A interface identifica essa condição. Apenas autenticação e operações de conta existentes usam persistência do servidor.

As rotas das quatro áreas não têm middleware de autenticação/autorização por perfil. O modelo de utilizador não estabelece os quatro perfis funcionais. A navegação separada não resolve esta ausência de controlo no servidor. A integração e a segurança destas áreas exigem uma tarefa de backend explicitamente autorizada.

Não existem operações de servidor para candidaturas a organizador, aprovação, verificação de fornecedores, publicação de campanhas, selecção de propostas, pagamentos ou auditoria. Não foram inventados endpoints nem apresentados esses fluxos como operações reais.

A lista de participantes existente contém exemplos parciais para a campanha 1, sem relação completa com a procura agregada. A página assinala a limitação e não apresenta esses exemplos para outras campanhas. É necessária uma relação de participantes fornecida pelo servidor.

O modelo de proposta não tem campos próprios para transporte, outros custos ou condições de pagamento. As observações existentes permanecem disponíveis; a comparação não presume que esses custos são zero. O total declarado não é tratado como custo final de satisfação da procura.

## Decisões funcionais pendentes

- Quantidade mínima obrigatória, consequências de não atingir o objectivo e tratamento de campanhas sem sucesso.
- Alteração/cancelamento de participações e respectivas fases permitidas.
- Aprovação de campanhas, elegibilidade de fornecedores e critérios de candidatura a organizador.
- Prazo próprio para propostas, possibilidade de alteração e permissões de selecção.
- Compromisso financeiro, pagamentos, reembolsos, entrega e levantamento.
- Operações sujeitas a auditoria e limites da intervenção administrativa.
- Termos aplicáveis e respectivo mecanismo de aceitação. Não existe uma página de termos válida no projecto.
- Atribuição do perfil inicial de consumidor e acumulação de permissões após aprovação de organizador.

Os estados existentes no TypeScript foram preservados para a demonstração; não constituem uma máquina de estados validada pelo servidor. O tema efectivamente implementado é claro; a página de aparência comunica essa disponibilidade.

## Validação

- TypeScript, formatação/lint e compilação de produção verificados durante a execução.
- Testes afectados de autenticação, definições e início autenticado: 28 passaram, 3 ignorados pelas condições existentes da suite (75 asserções).
- Verificação das 60 rotas registadas.
- Navegador: 84 verificações de apresentação (42 destinos a 1440 e 390 px), sem erros de JavaScript, texto corrompido ou transbordo horizontal da página. Incluem destinos protegidos que redireccionam para login e estados de registo inexistente; não comprovam o conteúdo interno das páginas que exigem sessão.
- Interacções: 11 verificações passaram, cobrindo abertura/foco/fecho do menu móvel, quantidade inteira na revisão, rascunho local, revisão e gravação de proposta com campanha e total correctos, propostas do próprio fornecedor, detalhes expansíveis, pesquisa vazia e recuperação do perfil da sessão.
- A verificação interactiva detectou e permitiu corrigir uma restrição HTML que rejeitava quantidades inteiras válidas no formulário de campanha.

O envio de emails, a experiência autenticada com uma conta real e os fluxos operacionais de produção dependentes do backend não estão validados ponta a ponta. Executar também a suite completa com `php artisan test --compact`.

## Expansão da direcção visual aprovada — 29 de Outubro de 2026

O utilizador aprovou o layout das referências e pediu a aplicação a todas as telas, regressando à variante verde e retirando o preto. As 39 páginas utilizam agora o mesmo sistema: cabeçalhos verde-escuro, superfícies verdes suaves, acções verdes e destaques em lima. O token experimental `junta-ink` e a superfície `#101815` foram removidos do código da interface.

- `Page` e `PageHeader` estendem o cabeçalho aprovado às listagens, detalhes, formulários, notificações, perfil comercial e relatórios. `OperationalPanel` mantém a composição própria das tarefas e passa a distinguir secções com superfícies suaves e separadores.
- A navegação dos quatro perfis e da conta usa verde-escuro com destino activo em lima; o menu móvel mantém gestão de foco e fecho por teclado. O logótipo original é preservado com nome legível sobre verde.
- A página inicial usa uma campanha existente em destaque, em vez do painel de ícones repetidos. As restantes campanhas aparecem sem repetir o destaque; pesquisa e detalhes continuam nas rotas anteriores.
- `RecordTable` mantém filtros, pesquisa, contagens e detalhes. Em ecrãs largos apresenta tabela; nos restantes apresenta registos com os mesmos campos e detalhes expansíveis, sem comprimir colunas. A alteração cobre histórico, campanhas, oportunidades, propostas, utilizadores e fornecedores.
- Login, registo, recuperação, redefinição e verificação de email usam o novo layout de autenticação. Dados pessoais, segurança, aparência e contactos partilham a navegação verde das definições. Não foram alteradas operações Fortify, controladores, rotas ou permissões.
- Os contactos deixam de repetir as margens da página de definições. As barras de guardar dos perfis ficam no fluxo normal, evitando sobreposição móvel. Relatórios mantêm os cálculos e CSV existentes, com paleta verde e indicação explícita de valores demonstrativos.

As operações e destinos anteriores foram preservados: participação com revisão e acompanhamento, criação de campanha e proposta, selecção humana, exportações, notificações, perfis e operações reais de conta. Nenhuma integração foi substituída por simulação nesta expansão; os módulos comerciais que já eram locais continuam identificados como demonstração.

Validação da expansão:

- Formatação/lint, TypeScript e compilação de produção aprovados.
- Suite PHP completa: 30 testes aprovados, 3 ignorados, 77 asserções. Os 5 testes Node de apresentação e participação também passaram.
- 117 renderizações: as 39 páginas em 1440, 1024 e 390 px, sem transbordo horizontal da página, excepções JavaScript ou superfícies pretas nos cabeçalhos/painéis verificados. Capturas em `storage/app/junta-green-{pagina}-{largura}.png`; registo em `storage/app/junta-green-rollout-check.json`.
- Após a inspecção, contactos, perfil comercial, relatórios e início do fornecedor receberam 12 verificações adicionais nos mesmos tamanhos, também aprovadas. Registo em `storage/app/junta-green-adjustments-check.json`.
- 20 verificações interactivas passaram: menu móvel verde/lima com foco e Escape; botão Perfil e quatro destinos; pesquisa, estado vazio e limpeza de filtros; detalhes de propostas e fornecedores; notificações; gravação local de contactos; revisão/edição/rascunho de campanha; revisão/edição/gravação local de proposta; participação contextual com actualização da procura. Registo em `storage/app/junta-green-interactions-check.json`. Os dados criados foram removidos da sessão temporária ao terminar.
- As páginas protegidas de conta e verificação de email foram renderizadas com propriedades isoladas injectadas apenas no navegador de teste. Não foram criadas contas, enviadas mensagens nem removida autenticação. Esta verificação visual não prova uma sessão autenticada real; a protecção das rotas e as operações de conta continuam cobertas pela suite PHP.

As dependências de backend e regras de negócio pendentes descritas acima continuam em aberto. A aplicação visual às 39 páginas não equivale à conclusão das integrações comerciais.

## Cartões e imagens — revisão de apresentação

A pedido do utilizador, a composição foi revista para reduzir texto solto e dar mais presença aos cartões, preservando a versão verde aprovada.

- Foram criadas seis imagens ilustrativas de produto: construção, coco, painéis solares, sementes, escritório e saúde. Estão guardadas no projecto em `public/images/campaigns/*-illustration.jpg`, optimizadas em JPEG (aproximadamente 875 KiB no conjunto). Foram geradas para este projecto; não são fotografias de fornecedores ou de mercadoria reservada.
- `CampaignVisual` preserva imagens reais fornecidas em `imageUrl`. As ilustrações são usadas apenas nas seis campanhas conhecidas da demonstração, mediante correspondência de identificador e produto. Campanhas novas não recebem fotografias arbitrárias. As capas apresentam o rótulo **Imagem ilustrativa**; falhas de carregamento ou ausência de imagem recebem uma alternativa por categoria.
- Catálogo e página inicial usam cartões com capa, categoria, estado, referência comercial e acção. Detalhes públicos, gestão e oportunidade comercial apresentam o produto e as condições em secções com superfície, borda e espaçamento próprios.
- Participações, campanhas, oportunidades, histórico e propostas passam a incluir miniaturas quando existe uma imagem adequada. As tabelas de gestão continuam disponíveis em ecrãs largos, com os mesmos campos, filtros e detalhes.
- Os painéis operacionais separam cabeçalho, conteúdo e registos individuais. Os dados de detalhe usam cartões de factos. Processo público, notificações, relatórios, formulários, definições e segurança também utilizam agrupamentos visuais coerentes, sem acrescentar métricas ou operações fictícias.
- A inspecção móvel identificou títulos demasiado comprimidos entre miniaturas e estados. Os cartões afectados foram corrigidos com grelha e acções em linha própria, sem truncar condições essenciais.

Não foram alterados backend, contratos, autenticação ou persistência comercial. O âmbito desta revisão é apresentação e recursos visuais. As verificações estão em `storage/app/junta-cards-*-check.json` e as capturas em `storage/app/junta-cards-{pagina}-{largura}.png`. As páginas protegidas continuam a exigir sessão real; a sua inspecção visual usa apenas propriedades isoladas no navegador de teste.

Validação desta revisão: compilação, TypeScript e formatação/lint aprovados; 30 testes PHP e 5 testes Node passaram, com 3 testes PHP ignorados pelas condições da suite. Foram verificadas as 39 telas em 1440, 1024 e 390 px (117 renderizações), seguidas de 15 verificações das composições ajustadas. Não foram detectados transbordos horizontais, imagens visíveis quebradas ou excepções JavaScript. As capturas foram inspeccionadas visualmente, incluindo a correcção dos títulos móveis comprimidos.

Passaram ainda 25 verificações interactivas: os 20 fluxos anteriores de menus, filtros, detalhes, perfis e formulários; identificação da capa ilustrativa; descodificação das seis imagens locais; ausência de associação arbitrária a campanhas novas; prioridade de `imageUrl`; e alternativa após falha de carregamento. Os exemplos e mutações de teste ficaram apenas no navegador temporário e foram removidos ao terminar.
