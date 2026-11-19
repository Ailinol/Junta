# Junta — primeira etapa das referências visuais

**Data:** 29 de Outubro de 2026. **Estado:** código integrado, pronto para revisão visual.

**Actualização:** a direcção visual foi aprovada e expandida às 39 páginas. O utilizador pediu a variante verde, sem as superfícies pretas experimentais: verde-escuro `#0C3427`, verde-floresta `#155B43`, superfícies verdes suaves e lima `#DDF27A`. O texto abaixo regista a primeira etapa; o resultado da expansão está em `REFATORACAO.md` e no relatório geral.

Esta etapa aplica o plano anexo a três experiências: explorar campanhas, consultar e participar numa campanha e gerir uma campanha com três abas. Não representa o redesenho das 39 páginas. A expansão para consumidor, organizador, fornecedor, administração e conta fica para depois da revisão desta direcção.

## Inspecção e fontes preservadas

O repositório confirma Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4 e Wayfinder. As páginas estão em `resources/js/pages`; os caminhos existentes são definidos em `routes/web.php` e `routes/settings.php`.

Campanhas, participações e propostas continuam a vir de `resources/js/data/demo.ts`, através de `usePrototypeStore` e da chave `junta.prototype.v1` no `sessionStorage`. Pesquisa e filtros são locais. Os cinco participantes da campanha 1 constituem uma amostra parcial, preservada na respectiva página; não comprovam a integridade da procura agregada.

Autenticação e definições pessoais continuam a usar as integrações existentes. Não foram alterados PHP, backend, base de dados, contratos, dependências ou atribuição de permissões. O botão flutuante Perfil e o modo temporário de demonstração sem login foram mantidos.

## Mapa de rotas, funções e destinos

| Função                                            | Destino preservado                                                    | Composição nesta etapa                                                                               |
| ------------------------------------------------- | --------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Descoberta, pesquisa, categorias e ordenação      | `/campanhas`                                                          | Pesquisa próxima dos resultados, filtros removíveis, grelha de 3/2/1 colunas e estado sem resultados |
| Condições da campanha                             | `/campanhas/{id}`                                                     | Produto e estado; conteúdo e painel de participação em duas colunas; painel primeiro no telemóvel    |
| Indicar quantidade, rever e confirmar             | No detalhe; também `/consumidor/campanhas/participar?campaignId={id}` | Mesmo componente de participação nos dois destinos; confirmação explicitamente local                 |
| Acompanhamento                                    | `/consumidor/acompanhamento/{id}`                                     | Acesso após confirmação; a página existente continua disponível                                      |
| Lista e criação de campanhas                      | `/organizador/campanhas` e `/organizador/campanhas/criar`             | Acessos mantidos; formulários e listagem fora do redesenho desta etapa                               |
| Visão geral da gestão                             | `/organizador/campanhas/{id}`                                         | Próxima tarefa conhecida, objectivo de procura e condições                                           |
| Participantes, pesquisa, estados e exportação CSV | `/organizador/campanhas/{id}/participantes`                           | Tabela em ecrã largo; lista com contacto e observações acessíveis em ecrãs menores                   |
| Comparação e revisão da selecção                  | `/organizador/campanhas/{id}/propostas`                               | Matriz em ecrã largo; cartões com os mesmos critérios na mesma ordem em ecrãs menores                |
| Login, recuperação e definições                   | Rotas existentes                                                      | Integrações preservadas; não substituídas por demonstração                                           |

As abas são ligações para as rotas anteriores, com estado activo associado ao URL. Abrir directamente, recarregar, voltar e avançar continuam possíveis. Nenhuma função foi eliminada por passar a integrar uma aba.

## Componentes e decisões de apresentação

- `CampaignCard`: produto, localização, referência por unidade, procura, prazo e participantes; rodapé alinhado. Imagem apenas quando existe nos dados; alternativa discreta sem bloco vazio.
- `CampaignFacts`: referência explicitamente estimada, singular da unidade e procura com objectivo e quantidade em falta quando calculável.
- `ParticipationFlow`: quantidade e observações → revisão → resultado local; permite corrigir antes de confirmar. Foco acompanha revisão, edição e confirmação. O caminho anterior de participação reutiliza este componente.
- `CampaignManagementLayout` e `CampaignManagementTabs`: cabeçalho persistente, contexto compacto e navegação entre visão geral, participantes e propostas.
- `campaign-presentation.ts`: rótulos, unidades e datas no fuso de Maputo. O prazo `2026-11-15T23:59:00Z` corresponde a 16 de Novembro em Maputo.
- `demo-participation.ts`: operação local extraída e validada, preservando os incrementos de quantidade e participações da demonstração.

A marca e o logótipo foram preservados. O texto secundário usa a variante `#5E7066` para leitura pequena; o token original da paleta permanece disponível. A largura pública é limitada a 1280 px, com margens responsivas. O componente de cartão também melhora a apresentação nos destinos existentes que já o utilizavam, sem reconstruir essas páginas.

O visitante anónimo não recebe a apresentação de uma participação pessoal dos exemplos no detalhe público. Com sessão autenticada, a interface pode mostrar a participação encontrada na demonstração; a separação dos dados por identidade ainda depende do backend.

## Propostas e decisão humana

A comparação distingue:

- preço unitário e quantidade disponível;
- cobertura da procura actual;
- base comum de quantidade e subtotal calculado para essa procura, quando o fornecedor tem capacidade;
- total declarado na proposta, cuja base de quantidade não está informada;
- outros encargos e condições de pagamento não informados;
- custo final comparável indisponível, prazo, modalidade, validade e observações.

Encargos ausentes não são tratados como zero. O subtotal calculado não é apresentado como preço final garantido. Não existe ordenação de mérito nem atribuição automática de melhor proposta ao menor preço.

A revisão identifica o fornecedor e as condições antes de confirmar. A selecção mantém as condições existentes da demonstração: campanha em avaliação, proposta submetida/em avaliação e validade aberta. Confirmar actualiza apenas a sessão local e coloca a campanha em proposta seleccionada; não conclui a compra.

## Validação

- `npm.cmd run check`: formatação e lint aprovados, sem avisos ou erros.
- `npm.cmd run types:check`: aprovado.
- `npm.cmd run build`: aprovado. Mantém o aviso sobre a dependência opcional `fontaine`; nenhuma dependência foi acrescentada.
- `node --test tests/Unit/CampaignPresentation.test.mjs`: 5 testes aprovados, cobrindo unidades, data em Maputo, actualização imutável da participação e rejeição de quantidades/campanhas inválidas.
- Testes PHP afectados de conta, autenticação, definições e dashboard: 29 aprovados e 3 ignorados, com 76 asserções. Comando: `php artisan test --compact tests/Feature/ExampleTest.php tests/Feature/Auth tests/Feature/Settings tests/Feature/DashboardTest.php`.
- Navegador Chromium local: 15 renderizações das cinco vistas (catálogo, detalhe e três abas) em 1440, 1024 e 390 px. Sem deslocação horizontal acidental ou excepções JavaScript detectadas. Capturas efectivamente inspeccionadas; o cabeçalho e o resumo móvel foram ajustados após a primeira leitura visual.
- 24 verificações interactivas aprovadas: pesquisa/filtros, ausência de resultados, privacidade do detalhe anónimo, quantidade inválida, revisão/edição/confirmação/acompanhamento, compatibilidade da participação anterior, campanha fechada/inexistente, navegação das abas, CSV filtrado e confirmação/cancelamento de selecção. Inclui selecção manual do fornecedor com preço superior e gestão do foco nas etapas de participação.
- Mais 9 verificações com título/descrição longos, sem imagem e sem referência de preço, nas três larguras: sem deslocação horizontal. Uma verificação adicional confirmou imagem opcional carregada e preço ausente explícito. As variantes foram injectadas só na sessão temporária; nenhum exemplo do projecto foi substituído.
- Contraste calculado: texto secundário/fundo principal 4,89:1; texto secundário/branco 5,27:1; branco/verde principal 8,05:1; texto principal/lima 13,29:1. A revisão de teclado/foco foi dirigida aos componentes alterados; não constitui auditoria integral de acessibilidade das 39 páginas.

As verificações comerciais foram feitas apenas na sessão temporária do navegador. Não foram criadas contas nem efectuadas operações comerciais no servidor. Os registos estão em `storage/app/junta-reference-*-check.json`; as capturas em `storage/app/junta-reference-{vista}-{largura}.png` e `-full.png`.

Para validação mais ampla do projecto, executar também a suite completa: `php artisan test --compact`.

## Limitações e decisões pendentes

1. Os módulos comerciais continuam demonstrativos. Gravação, propriedade de campanhas, permissões por perfil, elegibilidade de fornecedores e integridade da procura precisam de backend.
2. A regra de duplicação, alteração e cancelamento de participações não foi inventada. Uma participação conhecida é mostrada em vez do formulário; os exemplos não substituem identificação e prevenção de duplicados no servidor.
3. Custos de transporte, pagamento e base do total declarado não têm campos estruturados nos contratos actuais. São explicitamente desconhecidos na comparação.
4. Não existem eventos comerciais reais para fabricar uma cronologia. Execução, conclusão, pagamentos, reembolsos, candidaturas, aprovação e auditoria permanecem requisitos pendentes.
5. Os futuros pontos de acesso são: candidatura no contexto de conta/consumidor; avaliação em Pessoas e acessos/Pendências; auditoria no detalhe administrativo autorizado; execução no espaço de gestão quando existirem operações e transições confirmadas. Estes pontos não são botões simulados nesta entrega.
6. As novas imagens não foram inventadas: os dados actuais não têm fotografias de produto. O suporte a imagem opcional permanece disponível.
7. Os menus globais, a página inicial e os restantes perfis não foram migrados nesta etapa. O botão Perfil é uma ferramenta temporária de pré-visualização, não uma atribuição de permissões.
8. A verificação de participação existente com uma conta real e a entrega real de emails não foram repetidas nesta etapa. Os testes de autenticação existentes passaram, mas não validam serviços externos em produção.

O ponto de revisão desta entrega são as três referências. A expansão do desenho e a simplificação dos menus seguem após a avaliação visual, preservando os destinos e funções descritos acima.
