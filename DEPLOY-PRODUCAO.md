# Preparação e operação em produção

Estado: preparação local; nenhuma publicação autorizada ou executada. Ler também `AUDITORIA-PRODUCAO.md`. Os comandos de instalação/publicação abaixo são para a fase posterior à autorização do utilizador.

## 1. Escolher o destino correto

O Compose deste projeto precisa de um host com Docker Engine e Compose v2 ou posterior. **Não executar esses comandos dentro da Sandbox Altare**: a [documentação oficial](https://docs.altare.gg/sandboxes) exclui Docker. Na Sandbox atual, seguir a alternativa nativa da secção 7. Não é necessário contratar um VPS ou mudar de plano para preparar o projeto.

Antes de publicar na conta existente, confirmar: quota atribuída de RAM/CPU/disco, disponibilidade de uma porta HTTP adicional e nome HTTPS, pausa por inatividade desligada durante a apresentação e forma de retomar os processos após restart. Se não houver porta/slot gratuito já disponível ou obtenível com bolts gratuitos, a publicação gratuita nessa Sandbox fica bloqueada. Não ativar faturação para contornar isso.

O Compose normal mantém pagamentos desativados. Para testar o ciclo financeiro sem dinheiro real, usar o modo de demonstração explícito da secção 10, com volumes próprios. Não trocar `APP_ENV` para `local` para contornar as proteções de produção.

## 2. Preparar segredos num host Docker

Comandos seguintes em Bash/Linux, na raiz do projeto. Não executar `composer setup`, que gera chave e executa migrations do ambiente local.

```bash
cp -n .env.production.example .env.production
chmod 600 .env.production
docker compose version
```

Editar `.env.production` em privado: endereço HTTPS real em `APP_URL`; palavras-passe diferentes e aleatórias em `DB_PASSWORD` e `MYSQL_ROOT_PASSWORD`; `TRUSTED_PROXIES` com os IPs/CIDRs reais da infraestrutura HTTPS. Um IP deve corresponder a `REMOTE_ADDR` visto pelo PHP, considerando NAT/proxy. Não usar `*`. Nunca publicar o conteúdo de `docker compose config`, que inclui segredos; usar `config --quiet`.

Gerar `APP_KEY` **uma única vez**, antes da primeira utilização, e guardar o resultado num gestor de segredos e numa cópia protegida. Este comando não modifica qualquer `.env`:

```bash
docker run --rm --entrypoint php php:8.5-cli-bookworm \
  -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Inserir o resultado em `.env.production`. Em atualizações e recuperação manter a mesma chave, necessária para sessões e dados cifrados. Nunca executar `key:generate` automaticamente no arranque.

Definir o atalho nesta sessão Bash:

```bash
dc() { docker compose --env-file .env.production "$@"; }
dc --profile queues config --quiet
```

## 3. Build, migrations e início em Docker

```bash
dc build app web
dc up -d --wait db
dc run --rm --no-deps app php artisan migrate:status --no-interaction
```

Numa base nova, `migrate:status` pode indicar que a tabela de migrations ainda não existe. Numa base existente, fazer backup antes de prosseguir. Para rever SQL pendente, depois de existir a tabela de migrations:

```bash
dc run --rm --no-deps app php artisan migrate --pretend --force --no-interaction
```

O `--pretend` não é substituto de revisão de migrations com efeitos PHP; inspecionar sempre o código pendente. As migrations deste projeto não foram alteradas. Aplicar apenas migrations progressivas:

```bash
dc run --rm --no-deps app php artisan migrate --force --no-interaction
dc up -d --wait app web scheduler
dc exec -T app php artisan migrate:status --no-interaction
dc exec -T app php artisan schedule:list --no-interaction
dc exec -T web nginx -t
dc ps
```

Não executar `migrate:fresh`, refresh/reset, rollback automático, `db:wipe` ou seeders de demonstração na base de produção. O arranque não executa migrations nem seeders. Criar contas pela interface com palavras-passe privadas; conceder administração explicitamente a uma conta existente, quando autorizado:

```bash
dc exec app php artisan junta:grant-admin 'EMAIL_DA_CONTA_EXISTENTE' --no-interaction
```

O worker não é necessário pelas notificações atuais. Se existir trabalho enfileirado que o exija:

```bash
dc --profile queues up -d worker
```

## 4. HTTPS e verificações antes de abrir acesso

Por predefinição só `127.0.0.1:8080` é publicado; MySQL e PHP-FPM não expõem portas no host. Ligar o proxy HTTPS da infraestrutura a essa porta. Se o proxy for externo ao host, definir `HTTP_BIND` com a interface necessária, restringir o acesso à origem por firewall e só então configurar `TRUSTED_PROXIES`. Este Compose não obtém certificados nem publica HTTPS sozinho.

O proxy deve preservar `Host`, substituir `X-Forwarded-Proto` pelo protocolo real e construir `X-Forwarded-For` de forma segura. O Nginx/PHP não aceitam host/porta fornecidos em headers encaminhados. Não é suficiente definir apenas `APP_URL=https://...`.

Em teste exclusivamente local HTTP, usar outro ficheiro de ambiente com `HTTP_BIND=127.0.0.1`, uma porta livre, `APP_URL` local e `SESSION_SECURE_COOKIE=false`. Não aplicar esse valor ao endereço público HTTPS.

Após iniciar: consultar `/up`, `/`, `/campanhas`, `/como-funciona`, `/fornecedores`, `/login` e `/register`; verificar CSS/JS/fontes/imagens; autenticação, registo, logout, CSRF e redirecionamentos HTTPS; uma conta comum não pode abrir `/admin/dashboard`; organizadores não podem ver/alterar campanhas alheias. Confirmar cookies Secure/HttpOnly/SameSite e ausência de URLs locais nos pedidos do navegador. O `/up` confirma boot Laravel; não certifica sozinho DB, SMTP ou Scheduler.

```bash
dc exec -T app sh -c 'test -w storage/app && test -w bootstrap/cache && test -L public/storage && test -s public/build/manifest.json && test ! -e public/hot'
dc exec -T app php artisan config:show app.env --no-interaction
dc exec -T app php artisan config:show app.debug --no-interaction
dc logs --tail=80 app web scheduler
```

Usar `MAIL_MAILER=smtp` e os dados de um serviço já autorizado/gratuito para entrega real de verificação/recuperação. Para a demonstração, a secção 10 fornece captura local de emails, sem credenciais pessoais. Com `log`, nenhum email chega ao utilizador. Não ativar OpenAI ou pagamentos reais nesta preparação. A reconciliação termina normalmente quando o prestador está intencionalmente desativado; configurações inválidas continuam a produzir erro.

## 5. Parar, reiniciar e atualizar

```bash
dc stop
dc start
dc restart
dc ps
```

`stop`/`start` e `down` sem opções de volume preservam dados. **Nunca usar `down --volumes`/`down -v` nem `docker volume prune` nos volumes da aplicação.** `restart` não aplica novas variáveis ou imagens.

Para atualizar, com backup concluído e tráfego controlado:

```bash
dc build app web
dc exec -T app php artisan down --no-interaction
dc stop scheduler
# Se o worker tiver sido ativado:
# dc --profile queues stop worker
dc run --rm --no-deps app php artisan migrate --force --no-interaction
dc up -d --force-recreate --wait app web scheduler
# Se aplicável: dc --profile queues up -d --force-recreate worker
dc exec -T app php artisan up --no-interaction
```

A recriação conjunta inclui Nginx para renovar a resolução do endereço FPM. A chave e os volumes permanecem. Não executar `optimize:clear` indiscriminadamente: também limpa o cache partilhado e pode interferir com locks. O entrypoint executa `optimize` com as variáveis atuais em cada container. Se uma migration falhar, manter manutenção e investigar; não executar reversões destrutivas.

Os health checks verificam uma consulta MySQL autenticada, o ping FPM e `/up` via Nginx. Scheduler e worker são processos principais supervisionados pelo Docker, com reinício após saída. Um container `unhealthy` não é reiniciado automaticamente apenas pelo health check; acompanhar `dc ps` e logs.

## 6. Backup e recuperação Docker

Em Bash/Linux, guardar dumps fora do Git, com acesso restrito. A exportação usa a conta da aplicação, sem expor a palavra-passe na linha de comando do cliente:

```bash
umask 077
mkdir -p backups
backup_stamp=$(date -u +%Y%m%dT%H%M%SZ)
dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysqldump --user="$MYSQL_USER" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"' > "backups/mysql-$backup_stamp.sql.partial"
# Só executar depois de o comando anterior terminar com código 0:
mv "backups/mysql-$backup_stamp.sql.partial" "backups/mysql-$backup_stamp.sql"
```

Para uma cópia consistente entre DB e uploads, ativar manutenção e parar Scheduler/worker durante a cópia. Fazer o dump nesse intervalo e copiar também:

```bash
dc exec -T app tar -C storage/app -czf - . > "backups/storage-$backup_stamp.tar.gz"
```

Guardar `.env.production` e a `APP_KEY` separadamente, de forma cifrada. Copiar backups para fora do host e conservar pelo menos sete cópias diárias. Não há eliminação automática de backups nestes comandos.

**Recuperar primeiro numa instância nova**, com outro `COMPOSE_PROJECT_NAME`, nomes/volumes próprios e porta livre, mantendo `APP_KEY` original. Criar a base vazia pelo serviço MySQL dessa instância; não importar sobre uma base existente com dados. Confirmar o destino com `docker compose ls`/`dc ps` e definir `dc` para o ficheiro de recuperação antes dos comandos:

```bash
dc up -d --wait db
dc exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql --user="$MYSQL_USER" "$MYSQL_DATABASE"' < backups/ARQUIVO.sql
dc run --rm --no-deps -T app sh -c 'tar -C storage/app -xzf -' < backups/ARQUIVO-storage.tar.gz
dc run --rm --no-deps app php artisan migrate:status --no-interaction
dc up -d --wait app web scheduler
```

A conta da aplicação deve conseguir ler os ficheiros restaurados. Rever owners/modos se o backup tiver vindo de outro sistema. Só mudar o tráfego após validar a cópia recuperada. Não trocar o volume MySQL entre versões principais: usar exportação/importação lógica para migrações.

## 7. Alternativa mínima para a Sandbox Altare atual

Esta alternativa executa **processos nativos separados**, não um container monolítico e não Compose. Usa Nginx + PHP-FPM + Scheduler, com Supervisor sem systemd. O MySQL gerido gratuito reduz os processos e o uso de memória da Sandbox. Não houve execução ou instalação remota durante a preparação.

### 7.1 Condições e configuração na consola, após autorização

1. Na consola da Sandbox, confirmar Ubuntu 26.04 e recursos atribuídos. Desligar Pause when idle durante a semana, se estiver ligado. Não interpretar `df/free/nproc` como quotas.
2. Na secção Network, confirmar/adicionar um slot de porta disponível; registar **a porta efetivamente atribuída**, que não é a de SSH. Criar um nome do tipo HTTPS ligado a essa porta. O [Always Free cobra o slot adicional em bolts](https://docs.altare.gg/platform/connecting); parar se não houver recursos gratuitos suficientes.
3. Em Storage → MySQL, criar uma base no escalão gratuito (uma base, 256 MB RAM, 1 GB disco), se a equipa ainda tiver essa disponibilidade. Registar host, porta, utilizador e base na consola privada e descarregar o certificado. Não ativar excedentes/faturação. [Documentação MySQL](https://docs.altare.gg/databases/mysql).
4. As variáveis `MYSQL_*` da Altare não são automaticamente as `DB_*` do Laravel. Preencher explicitamente `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` e `MYSQL_ATTR_SSL_CA`. Usar o certificado da base e TLS; não desativar verificação de certificado.

Não foi confirmada uma funcionalidade de autostart da Sandbox. Supervisor reinicia filhos enquanto estiver ativo, mas não se inicia sozinho após stop/restart da própria Sandbox. É preciso retomar manualmente ou obter da plataforma um mecanismo de arranque documentado. Essa limitação impede garantir recuperação autónoma de reinícios por uma semana.

### 7.2 Criar um pacote Linux fora da Sandbox

Num host com Docker, depois do build bem-sucedido. Estes comandos também funcionam em PowerShell; o arquivo é criado dentro do Linux e copiado como ficheiro, sem redirecionar bytes binários pela shell do Windows:

```bash
docker run --name junta-release-export --entrypoint sh junta-app:production -c 'tar -C /var/www/html -czf /tmp/junta-release.tar.gz .'
docker cp junta-release-export:/tmp/junta-release.tar.gz ./junta-release.tar.gz
docker rm junta-release-export
```

O pacote contém dependências PHP de produção e frontend compilado. Não inclui o `.env` local, `node_modules`, dados de desenvolvimento ou ferramentas Windows. Não precisa de Composer/Node no servidor. Transferir por SFTP/SCP com o host e a porta SSH apresentados pela Altare; guardar o pacote fora do Git. Exemplo PowerShell, apenas depois de autorizar a publicação:

```powershell
$altareSshHost = Read-Host 'Host SSH mostrado na consola Altare'
$altareSshPort = [int](Read-Host 'Porta SSH mostrada na consola Altare')
scp -P $altareSshPort .\junta-release.tar.gz "root@${altareSshHost}:/root/junta-release.tar.gz"
```

### 7.3 Instalar numa Sandbox nova/preparada

Os comandos abaixo pressupõem login root e ausência de uma instalação anterior em `/home/junta/app`. Numa instalação existente, fazer backup e rever os ficheiros antes de substituir configurações ou extrair um pacote. Ubuntu 26.04 disponibiliza [PHP 8.5 nos repositórios](https://packages.ubuntu.com/resolute/php/); confirmar candidatos antes de instalar:

```bash
cat /etc/os-release
apt-get update
apt-cache policy php8.5-fpm php8.5-cli php8.5-mysql
apt-get install -y --no-install-recommends nginx supervisor gettext-base \
  ca-certificates curl php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring \
  php8.5-xml php8.5-curl php8.5-intl php8.5-zip php8.5-bcmath
install -d -m 755 /home/junta/app /run/php
tar -xzf /root/junta-release.tar.gz -C /home/junta/app
cd /home/junta/app
cp -n .env.production.example .env
```

Editar apenas o `.env` **do servidor**, com valores privados, antes de prosseguir: produção/debug false; chave estável; URL HTTPS atribuída; dados do MySQL gerido; certificado em `/home/junta/mysql-ca.pem`; `MYSQL_ATTR_SSL_CA=/home/junta/mysql-ca.pem`; cookies seguros; SSR/OpenAI/pagamentos de teste desligados. Manter o código fora de `/root`, para permitir acesso ao utilizador web. Não copiar o `.env` do PC.

```bash
chown root:www-data .env
chmod 640 .env
chown -R www-data:www-data storage bootstrap/cache
chmod -R u=rwX,go=rX storage bootstrap/cache
# O pacote Docker contém um symlink absoluto próprio do container.
# Só remover se for mesmo symlink; não apagar um diretório com uploads.
if test -L public/storage; then unlink public/storage; fi
runuser -u www-data -- php artisan storage:link --no-interaction
runuser -u www-data -- php artisan migrate:status --no-interaction
# Rever e fazer backup se a base já existir, antes de aplicar:
runuser -u www-data -- php artisan migrate --force --no-interaction
runuser -u www-data -- php artisan optimize --no-interaction
```

Copiar o tuning PHP e definir o pool local. Os caminhos `/proc/self/fd/2` referem-se ao stderr dos processos supervisionados:

```bash
cp php.production.ini /etc/php/8.5/fpm/conf.d/99-junta.ini
cat > /etc/php/8.5/fpm/pool.d/www.conf <<'FPM'
[www]
user = www-data
group = www-data
listen = /run/php/php8.5-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 10s
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
request_terminate_timeout = 65s
ping.path = /ping
FPM
```

Gerar o Nginx a partir do mesmo template, substituindo apenas as três variáveis de instalação e preservando `$uri` e outros nomes do Nginx:

```bash
read -r -p 'Porta HTTP adicional atribuída pela Altare: ' NGINX_PORT
export NGINX_PORT
export PHP_FPM_UPSTREAM=unix:/run/php/php8.5-fpm.sock
export APP_PUBLIC_PATH=/home/junta/app/public
envsubst '${NGINX_PORT} ${PHP_FPM_UPSTREAM} ${APP_PUBLIC_PATH}' \
  < nginx.production.conf > /etc/nginx/sites-available/junta
# Só desativar o default da instalação nova, se for um symlink.
if test -L /etc/nginx/sites-enabled/default; then unlink /etc/nginx/sites-enabled/default; fi
ln -s /etc/nginx/sites-available/junta /etc/nginx/sites-enabled/junta
nginx -t
php-fpm8.5 -t
chmod 600 supervisord.sandbox.conf
nohup supervisord -n -c /home/junta/app/supervisord.sandbox.conf \
  >/home/junta/supervisor-console.log 2>&1 </dev/null &
supervisorctl -c /home/junta/app/supervisord.sandbox.conf status
```

Identificar o IP real do proxy por um pedido controlado e os logs Nginx; confirmar também como a Altare reescreve headers. Configurar esse IP/CIDR em `TRUSTED_PROXIES`, executar novamente `artisan optimize` como `www-data` e reiniciar PHP/Scheduler pelo Supervisor. Não abrir acesso a avaliadores até login/CSRF/HTTPS funcionarem. A execução nativa e a ligação TLS à base da conta continuam a exigir validação no destino.

### 7.4 Operação nativa e persistência

```bash
supervisorctl -c /home/junta/app/supervisord.sandbox.conf status
supervisorctl -c /home/junta/app/supervisord.sandbox.conf stop scheduler nginx php-fpm
supervisorctl -c /home/junta/app/supervisord.sandbox.conf start php-fpm nginx scheduler
supervisorctl -c /home/junta/app/supervisord.sandbox.conf restart php-fpm nginx scheduler
# Apenas se houver trabalho de fila:
# supervisorctl -c /home/junta/app/supervisord.sandbox.conf start worker
```

Depois de reiniciar a Sandbox, recriar `/run/php` se necessário e repetir o comando `nohup supervisord` da secção anterior. Não iniciar duas instâncias simultâneas. Código, `.env` e uploads ficam sob `/home/junta`, enquanto a base pertence ao MySQL gerido. Não reinstalar a Sandbox para fazer deploy; usar release e backups.

Fazer diariamente um backup gerido pela consola e uma exportação adicional para conservar sete dias fora da conta. O [restauro gerido cria uma nova base](https://docs.altare.gg/databases/mysql), preservando a original; confirmar a disponibilidade do plano para a operação. Após restauro, ajustar `DB_*` e CA do novo destino, manter `APP_KEY`, executar `optimize` e reiniciar os processos. Para exportação lógica nativa com cliente MySQL disponível:

```bash
# Cliente usa os dados apresentados pela consola; -p pede a senha em privado.
# Não trocar por um cliente MariaDB sem verificar os parâmetros SSL.
mysqldump --host=HOST_REAL --port=PORTA_REAL --user=UTILIZADOR_REAL -p \
  --ssl-mode=VERIFY_IDENTITY --ssl-ca=/home/junta/mysql-ca.pem \
  --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
  BASE_REAL > /home/junta/backup-mysql.sql
tar -C /home/junta/app/storage/app -czf /home/junta/backup-storage.tar.gz .
chmod 600 /home/junta/backup-mysql.sql /home/junta/backup-storage.tar.gz
```

Guardar ficheiros fora da Sandbox. Se não houver cliente `mysqldump`, usar a consola de backups e preparar a exportação num host com o cliente MySQL 8.4; não instalar um servidor MySQL apenas para obter o cliente.

## 8. Resultados da validação local

Validação inicial em 19/11/2026, exclusivamente com credenciais e base fictícias. A revisão posterior do modo de demonstração e os testes mais recentes constam da secção 10:

| Verificação                 | Resultado                                                                                                                                                           |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `npm run build` no Windows  | Sucesso: 2385 módulos, manifestos, CSS/JS e fontes produzidos                                                                                                       |
| Requisitos PHP de produção  | `composer check-platform-reqs --no-dev` passou no Windows e na imagem Linux                                                                                         |
| Imagem PHP                  | Construída integralmente, incluindo `npm ci` e build frontend Linux; runtime PHP 8.5.12                                                                             |
| Imagem Nginx                | Construída reutilizando os assets da imagem PHP validada; detalhes abaixo                                                                                           |
| Compose com perfil de filas | `config --quiet` passou com ficheiro privado de teste                                                                                                               |
| Configuração Laravel        | `optimize` criou caches de configuração, eventos, rotas e views em todos os processos PHP                                                                           |
| MySQL                       | 8.4.11, ligação autenticada e consultas reais no container; zero contas pré-existentes                                                                              |
| Migrations                  | 13 aplicadas numa base nova; `migrate:status` confirmou todas; `migrate --pretend` posterior sem alterações pendentes                                               |
| Nginx                       | `nginx -t` passou; `/up` devolveu 200; health checks DB/FPM/Nginx saudáveis                                                                                         |
| Rotas públicas              | `/`, `/campanhas`, `/como-funciona`, `/fornecedores`, `/login` e `/register`: 200                                                                                   |
| Assets                      | CSS, JavaScript e fontes: 200; HTML sem endereço do Vite dev                                                                                                        |
| Fronteiras HTTP             | `/admin/dashboard` sem login: 302 para login; `/.env`: 403; outro PHP: 404; webhook de teste em produção: 404                                                       |
| Permissões                  | PHP como UID/GID 33, `storage/app` e `bootstrap/cache` graváveis, link público presente                                                                             |
| Proteções de arranque       | Debug ativo e chave ausente recusados com código 1; nenhuma chave criada automaticamente                                                                            |
| Scheduler/worker            | Processos iniciados e retomados; tarefa de plataforma executada; reconciliação termina com código 1 por falta de prestador, conforme regra existente                |
| Backup/restauro MySQL       | Dump importado numa **nova** base de recuperação fictícia; 13 migrations e zero utilizadores confirmados                                                            |
| Backup/restauro uploads     | Arquivo extraído numa pasta temporária e marcador comparado com sucesso                                                                                             |
| Persistência                | `down` sem volumes seguido de `up`: migrations e marcador de uploads preservados                                                                                    |
| Regressão PHP               | 31 testes/257 asserções de proxies, administração, acessos e gestão de campanhas passaram; autenticação: mais 5 passaram/9 asserções, 1 ignorado por 2FA desativada |
| Formatação/sintaxe          | Pint e lint dos quatro ficheiros PHP desta preparação passaram                                                                                                      |
| `.env` de desenvolvimento   | SHA-256 igual antes/depois; conteúdo não alterado                                                                                                                   |

O marcador público foi também lido por HTTP após a recriação. Os containers de validação foram parados no fim; imagens e volumes fictícios foram preservados, sem eliminar dados. O ficheiro de ambiente temporário dos testes foi removido do workspace.

Os testes PHP existentes usam SQLite em memória pela configuração da suíte; isso não alterou a aplicação nem substituiu o MySQL de produção. As migrations, ligação, runtime, backup e persistência foram adicionalmente verificados em MySQL real isolado. Na validação inicial a suíte completa ainda não tinha sido executada; a revisão final da secção 10.3 inclui essa execução, com `INERTIA_SSR_ENABLED=false` para evitar dependência do SSR de desenvolvimento.

O primeiro build encontrou uma CA de inspeção HTTPS do antivírus local ausente na imagem. Foi usado o bundle já confiado pelo PHP do computador como **segredo temporário BuildKit**; não se desligou TLS nem se instalou essa CA no runtime. Uma tentativa de repetir o build completo do frontend para Nginx sofreu `ECONNRESET` nos downloads npm. A imagem web foi então criada com os assets Linux já compilados com sucesso, pelo mecanismo oficial de contexto nomeado. Quando a rede de build exigir uma CA adicional, usar num host Bash (substituir o caminho):

```bash
docker buildx build --secret id=build_ca,src=/CAMINHO/ca-confiavel.pem \
  --target app --load -t junta-app:production .
docker buildx build --build-context frontend=docker-image://junta-app:production \
  --target web --load -t junta-web:production .
```

Usar sempre a imagem PHP correspondente à mesma release. O [BuildKit permite substituir um estágio por um contexto de imagem](https://docs.docker.com/reference/cli/docker/buildx/build/). Em redes com confiança CA normal, `dc build app web` continua a ser o caminho padrão; o build conjunto sem essa adaptação não foi concluído neste computador.

Os testes na conta Altare (porta, TLS, reinício da Sandbox, quotas e acesso dos avaliadores) não foram executados porque não existe autorização de publicação nem acesso remoto nesta sessão. A alternativa nativa e o Supervisor foram preparados, mas não foram executados no Ubuntu da conta. A disponibilidade pública durante uma semana continua por comprovar. O modo de demonstração da secção 10 resolve pagamentos fictícios e captura de emails; pagamentos reais, entrega externa de email e funcionalidades que exigem OpenAI continuam dependentes de serviços autorizados.

## 9. Ficheiros desta preparação

Criados: `Dockerfile`, `docker-compose.yml`, `nginx.production.conf`, `php.production.ini`, `php-fpm.production.conf`, `.dockerignore`, `.env.production.example`, `docker-entrypoint.sh`, `supervisord.sandbox.conf`, `config/trustedproxy.php`, `tests/Feature/ProductionProxyTest.php`, `AUDITORIA-PRODUCAO.md` e este guia.

Modificados inicialmente: `.gitignore` (proteção de ambientes/backups), `bootstrap/app.php` (headers de proxy aceites) e `config/inertia.php` (SSR configurável). A revisão de demonstração acrescenta `docker-compose.demo.yml`, `supervisord.sandbox-demo.conf` e `tests/Feature/ProductionDemonstrationTest.php`; atualiza `config/app.php`, `config/mail.php`, `AppServiceProvider`, `TestPaymentGateway`, `PurchaseData`, `ReconcilePurchases`, os ficheiros Docker/ambiente e este guia. Os builds geram artefactos ignorados em `public/build` e funções Wayfinder. O `.env` de desenvolvimento, dependências Composer/npm, migrations e componentes visuais não foram alterados.

## 10. Modo de demonstração com configuração de produção

Este modo foi acrescentado a pedido do utilizador para permitir testar a aplicação sem serviços financeiros reais ou credenciais de email pessoais. Continua com `APP_ENV=production`, `APP_DEBUG=false`, autenticação, permissões, CSRF, palavras-passe fortes e MySQL. Os cálculos, prazos, aceitação de condições e isolamento de pedidos mantêm as regras existentes. Apenas na demonstração, a validação das palavras-passe não consulta Have I Been Pwned: continuam obrigatórios 12 caracteres, letras maiúsculas/minúsculas, números e símbolos. A produção normal continua a recusar palavras-passe comprometidas.

O simulador só está disponível em produção quando **os três valores** são explícitos: `APP_DEMO_MODE=true`, `PURCHASE_PAYMENT_PROVIDER=test` e `PURCHASE_TEST_ENABLED=true`. O checkout mostra o aviso e as ferramentas de teste já existentes. Uma conta só pode simular o seu próprio pedido. O webhook continua a exigir assinatura; não é necessário ativá-lo para usar as ferramentas do checkout.

### 10.1 Docker: instalação separada

Usar `docker-compose.yml` junto com `docker-compose.demo.yml`. O segundo ficheiro configura os três valores e captura SMTP. Além do projeto próprio, substitui os mounts por volumes `demo_mysql_data`, `demo_app_data` e `demo_mail`; os volumes normais não são usados. Mesmo assim, passar sempre `-p junta-demo` para não reutilizar os containers do projeto normal: `COMPOSE_PROJECT_NAME` num ficheiro de ambiente pode prevalecer sobre o campo `name` do YAML.

Criar `.env.demo` privado a partir de `.env.production.example`, preencher uma chave estável, palavras-passe fictícias privadas, o endereço HTTPS da demonstração e o proxy real. Não copiar o `.env` de desenvolvimento nem apontar a demonstração para uma base com dados reais. O ficheiro é ignorado pelo Git.

Em Bash, definir o comando próprio:

```bash
ddc() { docker compose -p junta-demo --env-file .env.demo -f docker-compose.yml -f docker-compose.demo.yml "$@"; }
ddc --profile queues config --quiet
ddc build app web
ddc up -d --wait db mailpit
ddc run --rm --no-deps app php artisan migrate --force --no-interaction
ddc up -d --wait app web scheduler
ddc exec -T app php artisan junta:reconcile-purchases --no-interaction
```

No PowerShell, o equivalente de um comando é `docker compose -p junta-demo --env-file .env.demo -f docker-compose.yml -f docker-compose.demo.yml up -d --wait app web scheduler`. Se a rede local exigir CA adicional, usar os builds da secção 8 com a imagem da mesma release; os assets frontend existentes não foram modificados nesta revisão.

Para criar os primeiros perfis, registar contas fictícias pela interface e confirmar os emails na caixa de teste. Conceder administração explicitamente com `ddc exec app php artisan junta:grant-admin 'EMAIL_FICTICIO_EXISTENTE' --no-interaction`. Os restantes perfis seguem as candidaturas e aprovações da interface. Não usar seeders locais ou palavras-passe públicas na demonstração online.

A caixa usa [Mailpit oficial](https://mailpit.axllent.org/docs/install/docker/) `v1.34.0`, com captura SMTP interna, sem relay externo e sem consultas DNS reversas. SMTP tem timeout de 10 segundos na demonstração (`MAIL_TIMEOUT`, 30 segundos por predefinição no modo normal). A interface/API só é publicada em `127.0.0.1:18025` por predefinição (`DEMO_MAIL_PORT` permite escolher outra porta). Não abrir essa porta na Internet: a caixa contém links de recuperação de todas as contas fictícias. Os emails ficam no volume `demo_mail` e não são apagados automaticamente pelo limite de quantidade.

Num host Docker remoto com SSH, abrir a caixa apenas por túnel, usando o host e a porta reais da consola:

```bash
ssh -N -p PORTA_SSH -L 18025:127.0.0.1:18025 root@HOST_SSH
```

Depois abrir o endereço local da porta 18025 no navegador do responsável. O túnel só é necessário para consultar emails; a aplicação continua a funcionar no servidor sem o computador pessoal. A caixa não entrega emails em caixas reais. Nunca usar endereços pessoais durante os testes.

Com o worker opcional, os tetos de CPU continuam em 1,5 cores; Mailpit acrescenta um teto de 128 MiB de RAM. Operar e parar com `ddc restart`, `ddc stop` e `ddc ps`. Não remover os volumes nem usar os comandos de demonstração para operar o projeto normal.

### 10.2 Sandbox Altare sem Docker

A restrição Docker da Sandbox mantém-se. Para essa alternativa, usar a instalação nativa da secção 7 numa base **exclusiva de demonstração**, acrescentar o binário Linux Mailpit e usar `supervisord.sandbox-demo.conf`, que inclui a configuração normal e inicia a caixa de teste como processo separado.

Num computador com Docker, obter o mesmo binário da imagem oficial (Linux amd64; confirmar `uname -m` na Sandbox antes de transferir):

```bash
docker create --platform linux/amd64 --name junta-mailpit-export axllent/mailpit:v1.34.0
docker cp junta-mailpit-export:/mailpit ./mailpit-linux-amd64
docker rm junta-mailpit-export
```

Transferir por SCP/SFTP autorizado e instalar na Sandbox com `install -m 755 /root/mailpit-linux-amd64 /usr/local/bin/mailpit`. Não instalar ou executar Docker dentro da Sandbox. No `.env` **do servidor de demonstração**, configurar:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_DEMO_MODE=true
PURCHASE_PAYMENT_PROVIDER=test
PURCHASE_TEST_ENABLED=true
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_TIMEOUT=10
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=demonstracao@junta.test
OPENAI_ENABLED=false
```

Manter a chave, endereço HTTPS e ligação MySQL da instalação de teste; nunca reutilizar uma base real. Executar `php artisan optimize` como `www-data`. Iniciar o Supervisor com `nohup supervisord -n -c /home/junta/app/supervisord.sandbox-demo.conf >/home/junta/supervisor-console.log 2>&1 </dev/null &` e consultar o estado com `supervisorctl -c /home/junta/app/supervisord.sandbox-demo.conf status`. Não iniciar uma segunda instância se já houver uma ativa: parar primeiro o Supervisor da instalação de teste de forma controlada.

Mailpit escuta apenas em `127.0.0.1:1025` (SMTP) e `127.0.0.1:8025` (caixa), sem portas públicas adicionais. Para consultar pela porta SSH da Sandbox, usar `ssh -N -p PORTA_SSH -L 18025:127.0.0.1:8025 root@HOST_SSH`. Os emails persistem em `/home/junta/mailpit.db`, com permissões restritas pelo Supervisor. Após reiniciar a Sandbox, retomar os processos conforme a secção 7; não existe garantia de autostart da plataforma.

### 10.3 Verificações da revisão

Os dez novos testes de regressão passaram: pagamento, reembolso integral e idempotente, fecho pelo Scheduler, isolamento do pedido, assinatura obrigatória, proteção do modo normal, registo sem dependência externa na demonstração e recusa de palavras-passe comprometidas na produção normal. A suíte PHP completa final passou com **314 testes aprovados, 3 ignorados e 2741 asserções**. Os testes que forçam o ambiente de produção desligam apenas o middleware CSRF dentro da suíte; a proteção real foi adicionalmente verificada no runtime.

As imagens PHP/Nginx da revisão foram construídas com os assets Linux da release anterior, sem modificar o frontend. A configuração Compose confirma que todos os processos PHP recebem o modo de demonstração e que os mounts usam exclusivamente os volumes de teste. MySQL, Mailpit e PHP-FPM passaram os health checks. A reconciliação no runtime MySQL terminou com sucesso, sem a falha periódica da configuração anterior.

A validação HTTP no stack real de demonstração, com MySQL e SMTP capturado, confirmou: página de registo 200; POST sem CSRF 419; registo fictício 201; entrega do email ao Mailpit; link de verificação 302; dashboard verificado 200; conta comum no administrador 403; logout 204; pedido de recuperação 200; segundo email capturado; redefinição pelo token capturado 200; login com a nova palavra-passe 200; dashboard final 200. A conta, os endereços e as palavras-passe usados são fictícios. Nenhuma mensagem foi enviada a uma caixa externa.

A configuração normal foi revista separadamente: `APP_DEMO_MODE=false`, prestador `disabled`, testes de pagamentos desligados e volume `mysql_data`. A variante demo usa os valores explícitos e `demo_mysql_data`. A aplicação não precisou de alterações visuais nem de mudar o `.env` de desenvolvimento.

Os três emails de teste existentes foram preservados após reiniciar o Mailpit; os containers locais foram parados no fim, preservando os volumes. O `.env` de desenvolvimento conservou o mesmo SHA-256 e o ficheiro temporário de validação foi removido. Nenhum teste desta revisão publica a aplicação ou altera recursos da Altare. OpenAI permanece desligada para não gerar custos; análise por IA real exige um serviço e credenciais autorizados.
