# Auditoria de produção do Junta

Data: 19 de novembro de 2026. Âmbito: preparação local, sem publicação nem alterações na conta Altare.

## Conclusão

A arquitetura Docker portátil está preparada para um host Linux com Docker Engine e Compose. **Não pode ser executada dentro da Sandbox Altare atual**, que não suporta Docker. A alternativa mínima nessa Sandbox é executar Nginx, PHP-FPM e Scheduler como processos separados, supervisionados, com MySQL gerido gratuito. O procedimento está em `DEPLOY-PRODUCAO.md`.

A revisão posterior acrescentou um modo de demonstração explícito em produção: o simulador funciona apenas com `APP_DEMO_MODE=true`, prestador `test` e `PURCHASE_TEST_ENABLED=true`. O Compose normal mantém os três valores desativados. Os emails de teste são capturados num Mailpit privado, conforme a escolha do utilizador. Não foi criado qualquer prestador financeiro real. A publicação e a confirmação de porta/HTTPS na conta Altare continuam pendentes. Ver a secção 10 de `DEPLOY-PRODUCAO.md`.

## Versões e dependências

| Componente | Encontrado | Preparação |
| --- | --- | --- |
| PHP | CLI Windows 8.5.11 | Imagem `php:8.5-fpm-bookworm` |
| Laravel | 13.38.0; manifesto `^13.17` | Preservado pelo `composer.lock` |
| PHP mínimo | Manifesto `^8.3`, mas Symfony 8.1 no lock exige >=8.4.1 | Usar 8.5; não instalar 8.3 apenas com base no manifesto |
| Composer | 2.10.3 local | Composer 2 apenas no estágio de build |
| Node | 24.20.0 local | Node 24 apenas no build |
| Node mínimo do build | Vite Plus: `^20.19.0 || ^22.18.0 || >=24.11.0` | Node 24 compatível; não usar qualquer versão antiga de Node 22 |
| npm | 11.19.0 local | `npm ci`, preservando `package-lock.json` |
| MySQL | Binário local `mysqld` 8.4.11 | `mysql:8.4`; versão efetiva do servidor local não consultada |
| Docker/Compose | CLI 29.6.1 / Compose 5.3.0 | Docker Desktop local com engine Linux para validação |
| Sistema do destino | Sandbox Ubuntu 26.04 informada pelo utilizador | Não houve sessão SSH nem inspeção da conta |

Dependências PHP diretas de execução: Inertia Laravel 3.5.1, Chisel 0.1.1, Fortify 1.41.0, Laravel Framework 13.38.0, Tinker 3.0.2 e Wayfinder 0.1.23. O runtime mantém as dependências do manifesto; não inclui as de desenvolvimento.

Dependências PHP de desenvolvimento: Faker 1.24.1, Larastan 3.13.0, Boost 2.10.3, Pail 1.2.7, Pao 1.1.5, Pint 1.32.1, Sail 1.69.0, Mockery 1.6.15, Collision 8.9.5, Pest 5.3.3 e plugin Laravel 5.0.1.

Frontend instalado no lock: React/React DOM 19.3.1, Inertia React/Vite 3.9.1, Vite 8.3.4, Vite Plus 0.3.0, Laravel Vite Plugin 3.2.0, plugin Wayfinder 0.1.11 e Tailwind 4.3.3. As restantes dependências diretas são componentes Radix, Lucide, Sonner, TypeScript, utilitários de classes, ferramentas React/Babel, tipos e binários opcionais de plataforma; a lista completa e os intervalos estão em `package.json`, e as versões exatas em `package-lock.json`. Não se executou atualização de dependências.

Extensões: ctype, curl, date, dom, fileinfo, filter, hash, iconv, intl, json, libxml, mbstring, openssl, pcre, PDO/pdo_mysql, session, tokenizer, XML, zip, bcmath e OPcache. O runtime Linux acrescenta `pcntl` para sinais e timeouts de workers. Parte destas extensões já pertence à imagem PHP oficial; as adicionais são compiladas no Dockerfile. Não são necessários Redis, PostgreSQL, GD, Imagick ou servidor Node em execução.

## Vite, Inertia e portabilidade

`npm run build` executa `vp build`. O plugin Wayfinder invoca Artisan: o estágio frontend precisa de PHP e das dependências Composer, além de Node. O Dockerfile atende a essa dependência. Os plugins opcionais já distinguem Linux e Windows; `npm ci` dentro da imagem instala os binários Linux. Não copiar `node_modules` do Windows.

As fontes Instrument Sans são obtidas pelo plugin Bunny durante o build e incluídas em `public/build`, juntamente com os manifestos de assets e fontes. O build precisa de acesso à Internet para os registos de pacotes e fontes. O runtime serve os artefactos compilados sem Vite de desenvolvimento.

Inertia usa `resources/js/app.tsx` e as páginas existentes. `INERTIA_SSR_ENABLED=false` na produção evita um servidor SSR adicional; o valor predefinido da configuração mantém o comportamento anterior no desenvolvimento. Não há alteração visual nem dos componentes. O `public/hot` é excluído das imagens.

A referência `http://localhost` em `use-current-url.ts` é apenas a base para resolver caminhos quando não existe `window`; no navegador usa `window.location.origin`. URLs predefinidas locais em configuração são substituídas por `APP_URL`. `demonstracao-junta.mjs` usa portas locais e uma base SQLite de demonstração: não é comando de produção. Não foi encontrada dependência Windows no caminho de execução da aplicação. Os scripts locais, caminhos do ambiente e binários de desenvolvimento não devem ser transportados como runtime Linux.

## Autenticação, autorização e isolamento

Fortify usa o guard `web`, registo, recuperação de palavra-passe e verificação de email. Login tem limite de cinco tentativas por minuto por email/IP. O modelo implementa `MustVerifyEmail`; 2FA/passkeys não estão ativados. O `/dashboard` exige email verificado; os restantes grupos seguem os middleware já definidos, sem endurecimento que altere o fluxo existente.

Os grupos administrativos exigem autenticação e a gate `administer-platform`. Existem gates de organização, fornecimento e gestão de campanha. Controladores e dados partilhados aplicam os filtros por utilizador/campanha; os testes existentes de fronteiras e administração são usados como regressão. Isso não constitui certificação de segurança integral da aplicação.

O reconhecimento de HTTPS passa a aceitar apenas os endereços indicados em `TRUSTED_PROXIES`. Headers de host/porta encaminhados não são usados para construir URLs. A lista fica vazia até identificar o proxy real. Os cookies de produção são seguros, HttpOnly e SameSite=Lax; as sessões são cifradas. O Nginx serve apenas `public`, bloqueia ficheiros ocultos e só executa `index.php`. MySQL e FPM não publicam portas no host.

Em produção existem proibição de comandos destrutivos e regras fortes de palavra-passe, incluindo verificação de comprometimento pelo serviço Have I Been Pwned. A revisão mantém essa verificação na produção normal; apenas no modo explícito de demonstração evita a chamada externa, preservando comprimento e complexidade. Esta exceção serve exclusivamente contas fictícias da instalação isolada.

## Persistência, sessões, cache e processos

O ambiente local usa MySQL e os drivers `database` de sessão, cache e fila. A configuração de produção mantém MySQL, incluindo as tabelas `sessions`, `cache`, `cache_locks`, `jobs` e `failed_jobs` das migrations existentes.

O disco predefinido é `local`, com área privada em `storage/app/private`; o disco público aponta para `storage/app/public` através de `public/storage`. Não foram encontrados handlers atuais de upload de ficheiros; imagens de campanhas podem ser URLs. A infraestrutura já preserva ambas as áreas para os ficheiros presentes/futuros. Imagens remotas devem usar HTTPS para evitar conteúdo misto.

Volumes: `mysql_data` guarda `/var/lib/mysql` e `app_data` guarda `storage/app`. O Nginx monta apenas esse volume em leitura. Não se monta o código do Windows. Cache de configuração/rotas/views pertence a cada container, sem reutilizar caches do desenvolvimento. Sessões e locks ficam no MySQL. A manutenção usa cache partilhada. Logs vão para stderr e têm rotação pelo Docker.

As três notificações da aplicação usam o canal `database` e não implementam `ShouldQueue`; não foram encontrados jobs próprios nem dispatch assíncrono. O worker é opcional, no perfil `queues`, enquanto o driver permanece `database`.

O Scheduler é obrigatório: `junta:reconcile-purchases` e `junta:process-platform` executam a cada minuto, com `withoutOverlapping(10)`. A reconciliação ignora normalmente o prestador intencionalmente desativado e continua a falhar perante um prestador configurado mas indisponível. No modo de demonstração explícito, o simulador permite executar a reconciliação e os reembolsos. O segundo comando mantém prazos, propostas e notificações internas.

O arranque valida ambiente, chave e MySQL e executa `artisan optimize`. Não gera chaves, não migra, não semeia nem apaga dados. As migrations são uma operação explícita anterior à abertura do tráfego. Foram mantidas todas as migrations existentes, incluindo a alteração de nulabilidade de `accepted_at`, que exige revisão antes de aplicar numa base já utilizada.

## Serviços externos e variáveis

Obrigatórios: `APP_KEY` estável, `APP_URL`, dados MySQL e, para tráfego público, configuração HTTPS/proxy. `MYSQL_ROOT_PASSWORD` pertence apenas ao serviço MySQL; não é injetada no PHP.

Recomendados conforme funcionalidades: SMTP para verificação/recuperação; CA de MySQL quando gerido. `MAIL_MAILER=log` permite validar o backend mas não entrega mensagens. Não foram ativados SMTP pago, OpenAI, armazenamento S3 ou pagamentos reais. OpenAI é opcional e fica desligada; as funcionalidades que a exigem continuam dependentes de credenciais e serviço externo autorizados.

Os drivers, cookies, locale, logs e demais variáveis estão documentados em `.env.production.example`. O Compose fixa os valores de segurança e o MySQL local: esse ficheiro não transforma por si só o Compose num cliente do MySQL gerido. A variante nativa lê a sua própria `.env` com os parâmetros efetivos do destino.

## Altare: capacidades verificadas e limites

Segundo a [documentação de Sandboxes](https://docs.altare.gg/sandboxes), ficheiros persistem e processos continuam após logout, mas não há Docker ou systemd. Depois de parar/reiniciar a Sandbox, os processos devem ser iniciados novamente. A pausa por inatividade é configurável. `df`, `free` e `nproc` mostram totais do nó, não as quotas atribuídas. Consequentemente, os 788 GB reportados não são capacidade garantida desta conta.

Na [rede da Altare](https://docs.altare.gg/platform/connecting), a porta inicial da Sandbox serve SSH. HTTP requer outra porta e um nome HTTPS associado. No Always Free, o slot adicional custa 11,25 bolts; não foi verificado se a equipa tem slot/saldo disponível. Não há promessa de exposição web gratuita imediatamente disponível.

[Docker Apps](https://docs.altare.gg/apps/docker) executa uma imagem por app, em Linux amd64, com uma pasta persistente por app. Não há execução de Compose documentada nesse produto. Separar este stack em várias Docker Apps exigiria verificar rede privada, partilha dos ficheiros públicos e limites; esta preparação não apresenta essa migração como pronta.

Os [recursos gratuitos](https://docs.altare.gg/platform/resources) são partilhados pela equipa: 6 GB RAM, 150% CPU, 10 GB disco e quatro servidores. Confirmar a atribuição à Sandbox na consola. Os limites do Compose são cerca de 1,7 GiB RAM e 1,4 CPU, ou 1,94 GiB/1,5 CPU com worker. São tetos, não reservas; o build deve ocorrer fora da pequena Sandbox.

O [MySQL gerido](https://docs.altare.gg/databases/mysql) usa 8.4 e TLS. O Always Free inclui uma base de 256 MB RAM/1 GB disco, com 25 ligações no tamanho mínimo e backups conservados três dias. É a adaptação sugerida para a execução nativa, sujeita à disponibilidade da conta. Exportações próprias são necessárias para conservar a semana inteira.

[Workstations nos planos](https://docs.altare.gg/platform/plans) têm capacidades diferentes e dependem do plano; não foram propostas como solução gratuita. A documentação não equivale à confirmação do estado da sua conta nem garante uma semana sem interrupções.

## Verificação

Os resultados executados e as limitações de validação estão registados em `DEPLOY-PRODUCAO.md`. Nenhuma ligação, porta, base, plano ou recurso Altare foi criado ou alterado nesta tarefa. O `.env` local deve conservar o hash registado na auditoria da sessão; não se deve copiar esse ficheiro para as imagens ou para o servidor.
