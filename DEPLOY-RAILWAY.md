# Junta no Railway — publicação de demonstração

## Publicação configurada em 19 de novembro de 2026

- Aplicação: https://junta-production-7bcb59e2.up.railway.app
- Painel: https://railway.com/project/434d4f9b-4e6e-4361-b522-39ba093b06c6
- Projeto `Junta`, ambiente `production`, branch GitHub `railway-deploy`.
- O MySQL existente foi reutilizado com a sua imagem `mysql:8` (8.4.13), base
  `railway` e volume original. Não criar outra base nem substituir esse volume.
- A aplicação usa `junta_app`, autorizado apenas na base `railway`, através de
  `MySQL.JUNTA_DB_USERNAME` e `MySQL.JUNTA_DB_PASSWORD`. As credenciais root do
  serviço MySQL foram preservadas; os seus valores não pertencem ao repositório.
- Ambos os serviços têm uma réplica, limite de 0,5 GB de RAM / 0,5 vCPU e um
  volume de 500 MB. O MySQL não tem proxy TCP público.
- O serviço antigo no outro projeto ficou sem origem GitHub e sem deployment
  ativo, evitando publicações duplicadas. Nenhum projeto ou volume foi apagado.

Os passos seguintes documentam a configuração e permitem reproduzi-la num
ambiente novo. A publicação acima já está configurada; não é necessário recriar
os serviços nem gerar outra APP_KEY.

## Configuração auditada

O projeto usa PHP 8.5, Laravel 13.38, Inertia Laravel 3.5, React 19.3,
Inertia React 3.9, Vite 8.3 e MySQL 8.4 nos testes Docker. Composer e npm
instalam as versões dos respetivos lockfiles. A compilação executa Wayfinder
e inclui os assets e as fontes em `public/build`. SSR fica desligado: não é
necessário um servidor Node em execução.

O Dockerfile anterior tinha alvos separados `app` e `web`. O último alvo
era apenas Nginx e dependia de `app:9000`, um endereço do Docker Compose.
O novo último alvo, `railway`, contém Nginx, PHP-FPM, um worker da fila e o
agendador, geridos pelo Supervisor. Os alvos anteriores continuam disponíveis.
A aplicação mantém os seus controladores, permissões e regras de negócio.

São necessários **dois serviços**, na mesma região e ambiente:

| Serviço | Origem | Rede pública | Volume |
| --- | --- | --- | --- |
| MySQL | imagem oficial `mysql:8.4` | Desligada | `/var/lib/mysql` |
| Junta | GitHub `Ailinol/Junta`, branch `railway-deploy`, Dockerfile na raiz | HTTPS, destino 8080 | `/var/www/html/storage` |

A caixa de email de teste é opcional e corre dentro do serviço Junta.
As suas portas 1025 e 8025 e a porta PHP 9000 ficam ligadas apenas a
`127.0.0.1`. Não criar domínios nem proxies TCP para essas portas.

## 1. Preservar o limite dos US$5

Antes de lançar serviços, confirmar no painel que a conta ainda está no
**Trial**, com crédito disponível e sem subscrição paga. Não fazer upgrade,
comprar créditos, contratar SMTP ou ativar APIs pagas. O Trial termina após
30 dias ou quando os US$5 forem consumidos, e os serviços param. A duração
efetiva depende do consumo; US$5 não garantem um mês de execução contínua.
[Regras oficiais do Trial](https://docs.railway.com/pricing/free-plan).

O limite rígido de compute do Railway tem um mínimo de US$10: não permite
configurar um teto de US$5 ou US$4,50. A proteção desta demonstração é manter
a conta no Trial sem upgrade. Acompanhar Usage e o saldo restante, incluindo
outros projetos da conta, e parar os deployments antes de esgotar o crédito.
[Limites oficiais](https://docs.railway.com/cli/usage).

Usar uma réplica por serviço, sem ambientes de preview, Redis, bucket,
réplicas MySQL, backups automáticos pagos ou outros serviços. Em
Settings → Deploy → Replica Limits, começar com 0,5 GB por serviço e
0,5 vCPU por serviço, se o painel permitir essa fração. Se houver OOM,
verificar as métricas antes de aumentar qualquer limite. A cobrança usa
o consumo real; estes limites não são um orçamento financeiro.
[Controlo de recursos](https://docs.railway.com/pricing/cost-control).

Usar dois volumes de 0,5 GB. O Trial permite até três volumes por projeto,
portanto esta arquitetura cabe nesse limite. O Free posterior permite apenas
um: a mesma arquitetura não deve ser tratada como alojamento permanente gratuito.
[Limites de volumes](https://docs.railway.com/volumes/reference).

O agendador e a fila consultam MySQL regularmente. Manter Serverless/Auto
Sleep desligado durante a demonstração, para executar tarefas a cada minuto.
Para economizar após os testes, parar os dois deployments mantendo os volumes;
o armazenamento continua a consumir crédito. Fazer backup antes do fim do Trial.

## 2. Disponibilizar os ficheiros no GitHub

Usar a branch **`railway-deploy`**, que contém os sete ficheiros desta alteração.
Selecioná-la no Railway depois de configurar variáveis e volumes.
Para futuras atualizações, no PowerShell a partir da pasta do projeto:

```powershell
git switch railway-deploy
git status --short
git add Dockerfile .gitignore .env.railway.example railway-entrypoint.sh supervisord.railway.conf php-fpm.railway.conf DEPLOY-RAILWAY.md
git diff --cached --check
git commit -m "Prepare Railway deployment with persistent MySQL"
git push origin railway-deploy
```

Se estes ficheiros já estiverem num commit, basta `git push origin railway-deploy`.
Nunca adicionar `.env`, dumps SQL, volumes ou credenciais. O ficheiro
`.env.railway.example` contém apenas exemplos e referências Railway.

Não é necessário `railway.json`: a documentação atual descontinuou esse
formato para serviços novos. Este guia usa o painel e a deteção do Dockerfile,
sem acrescentar uma dependência IaC ao projeto.
[Configuração atual](https://docs.railway.com/config-as-code).

## 3. Criar MySQL persistente

Este procedimento é para **um serviço novo**, sem dados anteriores.
Se já existir MySQL com dados, preservar a imagem/versão e o volume existentes;
não fazer downgrade, não recriar o volume e fazer backup antes das migrations.

1. No projeto criado, usar New → Empty Service e dar o nome exato `MySQL`.
2. Em Source, escolher Docker Image e indicar `mysql:8.4`.
3. Anexar um volume persistente em **`/var/lib/mysql`**.
4. Em Variables → Raw Editor, adicionar as variáveis abaixo. Substituir os
   dois placeholders por palavras-passe aleatórias, diferentes e privadas.
   Usar um gestor de palavras-passe; não enviar os valores para o chat/GitHub.

```dotenv
MYSQL_DATABASE=junta
MYSQL_USER=junta_app
MYSQL_PASSWORD=SUBSTITUIR_POR_SEGREDO_PRIVADO
MYSQL_ROOT_PASSWORD=SUBSTITUIR_POR_OUTRO_SEGREDO_PRIVADO
MYSQLHOST=${{RAILWAY_PRIVATE_DOMAIN}}
MYSQLPORT=3306
MYSQLDATABASE=${{MYSQL_DATABASE}}
MYSQLUSER=${{MYSQL_USER}}
MYSQLPASSWORD=${{MYSQL_PASSWORD}}
JUNTA_DB_USERNAME=${{MYSQL_USER}}
JUNTA_DB_PASSWORD=${{MYSQL_PASSWORD}}
```

5. Em Deploy → Custom Start Command, usar a linha completa:

```sh
docker-entrypoint.sh mysqld --innodb-use-native-aio=0 --innodb-buffer-pool-size=64M --innodb-log-buffer-size=8M --innodb-redo-log-capacity=64M --max-connections=20 --performance-schema=OFF --mysqlx=OFF --skip-log-bin
```

Esta configuração reduz memória e crescimento de logs para uma demonstração
pequena. Mantém a durabilidade transacional padrão; não fornece replicação nem
recuperação por binlog. O entrypoint oficial inicializa a base e o utilizador
apenas quando o volume está vazio. Alterar as variáveis de password depois
não altera automaticamente os utilizadores de uma base já inicializada.

6. Manter apenas Private Networking, sem Public Access/TCP Proxy/domínio.
7. Aplicar as alterações e esperar pelo log `ready for connections` na porta
   3306. Confirmar que o volume está anexado antes de criar dados.

A aplicação usa o utilizador `junta_app`, limitado à sua base, e as referências
do Railway evitam copiar credenciais para o repositório.
[MySQL no Railway](https://docs.railway.com/databases/mysql).

## 4. Configurar Junta antes do primeiro deploy

1. Criar outro serviço vazio, com nome `Junta`, na mesma região do MySQL.
2. Ligar Source → GitHub Repo → `Ailinol/Junta` → branch `railway-deploy`. Autorizar a
   Railway GitHub App apenas para o repositório necessário.
3. Usar a raiz do repositório e o `Dockerfile` da raiz. Deixar Custom Build
   Command e Custom Start Command vazios: a imagem já define ambos.
4. Anexar o segundo volume em **`/var/www/html/storage`**. Não usar `/app/storage`:
   a imagem deste projeto trabalha em `/var/www/html`.
5. Em Networking, gerar um domínio Railway com destino **8080**. Não é
   necessário comprar um domínio. Copiar o endereço HTTPS atribuído.
6. Copiar `.env.railway.example` para Variables → Raw Editor. Confirmar que
   as referências `MySQL.*` resolvem para o serviço do passo anterior.
7. Gerar uma APP_KEY **uma única vez**, num terminal privado local:

```powershell
php artisan key:generate --show --no-interaction
```

O `--show` não modifica o `.env` local. Colocar o resultado apenas na variável
`APP_KEY` do Railway. Guardar uma cópia privada e manter a mesma chave nos
redeploys: sessões e dados cifrados dependem dela.

8. `APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}` utiliza o domínio gerado.
   Se a referência ainda não resolver, usar o URL HTTPS completo copiado no
   passo 5. Não incluir uma barra final. `PORT=8080` alinha o domínio e o health
   check; o runtime também aceita outra porta em `PORT`, sem recompilar.
9. `TRUSTED_PROXIES=*` destina-se exclusivamente a esta imagem atrás do ingress
   HTTPS Railway. Não expor a origem por outro caminho. O Laravel existente
   confia em X-Forwarded-Proto e X-Forwarded-For; Nginx remove os headers de
   forwarded host/port/prefix antes do PHP. Não adicionar `ASSET_URL`,
   `DB_URL`, `SESSION_DOMAIN` ou `VITE_*` com segredos.
10. Em Deploy, definir **Pre-deploy Command**:

```sh
php artisan migrate --force --no-interaction
```

Definir Pre-deploy Timeout em **300 segundos**. Este comando executa somente
migrations pendentes, sem reset/rollback/seed. Tem acesso à rede privada e às
variáveis; uma falha impede a publicação. Não precisa do volume da aplicação,
que só é montado no arranque.
[Pre-deploy](https://docs.railway.com/deployments/pre-deploy-command).

11. Definir **Healthcheck Path `/up`**, timeout **120 segundos**, uma réplica e
    Restart Policy **On Failure**, máximo **3** tentativas. O `/up` existente
    verifica que Laravel arrancou. Não testa MySQL, SMTP ou regras de negócio;
    verificar essas dependências nos testes abaixo. O Railway consulta o health
    check durante a publicação, não como monitorização contínua.
    [Healthchecks](https://docs.railway.com/deployments/healthchecks).
12. Se configurar graceful shutdown, usar
    `RAILWAY_DEPLOYMENT_DRAINING_SECONDS=75`, para permitir terminar jobs em curso.
13. Aplicar as alterações e selecionar Deploy Latest Commit. Confirmar que os
    logs de build indicam o Dockerfile e `npm run build`, e que o deploy fica
    Active após migrations e health check. Com um volume anexado e uma réplica,
    os redeploys podem ter uma breve interrupção.

O arranque valida APP_ENV/DEBUG/KEY/URL/MySQL, corrige permissões dos diretórios
do volume e executa `artisan optimize` como `www-data`. Não gera chaves nem
executa migrations/seeders no entrypoint. O código PHP corre como `www-data`;
o processo gestor usa root para preparar o volume e arrancar os serviços.

## 5. Contas e seeders sem apagar dados

O `DatabaseSeeder` atual não insere dados em MySQL/produção. Os seeders locais
continuam a exigir local/testing e/ou SQLite isolado. Não alterar APP_ENV para
contornar essas proteções, nem usar passwords conhecidas num serviço público.
Nenhum seeder é executado automaticamente neste deploy.

Para povoar explicitamente esta demonstração MySQL, usar:

```sh
php artisan junta:seed-demo --credentials=/tmp/junta-demo-access.private.json --force --no-interaction
```

O comando exige `APP_DEMO_MODE=true`, pagamentos de teste ativos e um ficheiro
JSON privado com `accounts.<perfil>.password` para os nove perfis definidos em
`HostedDemoSeeder::PROFILES`. Usar passwords aleatórias, únicas, com pelo menos
16 caracteres. O ficheiro local `junta-demo-access.private.json` está excluído
do Git e da imagem Docker; transferir por SSH e remover a cópia remota depois.
Guardar um backup privado antes de executar. São acrescentadas 24 campanhas
`[DEMO]`, fornecedores, candidaturas, participações, propostas e compras de
teste. A operação é transacional e pode ser repetida: preserva contas,
passwords, decisões e progresso existentes, recusando conflitos com dados
de outras contas. Não exige migrations adicionais nem permissões MySQL globais.

Criar contas através de Registar e verificar o email na caixa de teste.
Para atribuir administração a **uma conta própria já registada**, usar o
comando existente no terminal remoto, substituindo o email:

```sh
cd /var/www/html
runuser -u www-data -- php artisan junta:grant-admin teu-email@example.com --no-interaction
```

O comando não cria contas nem altera palavras-passe. Usar depois a interface
administrativa para aprovar candidaturas e criar os dados necessários ao ensaio.

Nunca usar `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`,
rollback automático, `truncate` ou comandos de limpeza de demonstração.
Antes de futuros deploys com alterações de esquema, rever as migrations e
guardar um dump privado da base. Não existe rollback automático dos dados.

## 6. Email de teste e acesso ao terminal

O exemplo ativa Mailpit interno. Emails de verificação e recuperação chegam
à caixa de teste, sem entrega externa e sem credenciais de uma conta pessoal.
Guarda até 100 mensagens, retidas no máximo um dia, no volume Junta. A base
principal da aplicação continua em MySQL.

Neste computador já existe uma chave dedicada autorizada e registada no Railway:
`$env:USERPROFILE\.ssh\junta_railway_ed25519`. Para abrir novamente o túnel:

```powershell
ssh -i "$env:USERPROFILE\.ssh\junta_railway_ed25519" -o ExitOnForwardFailure=yes -N -L 127.0.0.1:8025:127.0.0.1:8025 junta-production-7bcb59e2.up.railway.app@ssh.railway.com
```

Manter esse processo aberto e visitar http://127.0.0.1:8025. Para um terminal
remoto da aplicação, usar a mesma chave:

```powershell
ssh -i "$env:USERPROFILE\.ssh\junta_railway_ed25519" junta-production-7bcb59e2.up.railway.app@ssh.railway.com
```

A chave privada permanece neste computador. Pode ser revogada na área SSH Keys
da conta Railway; não copiar o seu conteúdo para mensagens ou para o GitHub.

Para aceder, instalar a CLI oficial no computador, sem alterar as dependências
do projeto, e autenticar por browser:

```powershell
npm install --global @railway/cli --ignore-scripts=false
railway login
railway link
railway ssh --service Junta
```

Selecionar o projeto e o ambiente certos. A CLI orienta o registo de uma chave
SSH pessoal na primeira ligação. O terminal remoto permite executar os comandos
Artisan deste guia. Para abrir a caixa no browser, num segundo terminal local:

```powershell
ssh -N -L 127.0.0.1:8025:127.0.0.1:8025 TEU-DOMINIO.up.railway.app@ssh.railway.com
```

Substituir o domínio pelo atribuído ao Junta, sem `https://`. Manter o túnel
aberto e visitar **http://127.0.0.1:8025**. A chave SSH deve estar registada no
Railway. Só o responsável deve ter este acesso; os links de recuperação são
sensíveis. A interface não está publicada no domínio da aplicação.
[SSH e encaminhamento de portas](https://docs.railway.com/cli/ssh).

Para emails reais no futuro, configurar um SMTP que já possuas nas variáveis,
definir `MAIL_CAPTURE_ENABLED=false` e redeploy. A presente configuração é uma
demonstração com caixa de teste, não entrega de email para utilizadores reais.

O exemplo mantém o modo de demonstração existente com pagamentos sintéticos:
`APP_DEMO_MODE=true`, `PURCHASE_PAYMENT_PROVIDER=test`, `PURCHASE_TEST_ENABLED=true`.
Não movimenta dinheiro. Para utilização comercial, desativar esses três valores
(`false`, `disabled`, `false`) e configurar um prestador real separadamente.
OpenAI permanece desligado, evitando consumo externo.

## 7. Testar a publicação e a persistência

1. Abrir o URL HTTPS Railway e `/up`: ambos devem responder sem erro; `/up`
   deve devolver 200. Confirmar que JS, CSS e fontes carregam de `/build/`,
   sem pedidos a localhost:5173, conteúdo misto ou necessidade de `npm run dev`.
2. Registar uma conta de teste. Abrir a mensagem no Mailpit através do túnel,
   seguir o link e concluir a verificação. Testar login, logout e recuperação
   de palavra-passe com uma nova mensagem. Os links devem apontar ao domínio
   HTTPS Railway e os cookies de sessão devem usar Secure.
3. No terminal remoto, executar as verificações sem escrever dados:

```sh
cd /var/www/html
runuser -u www-data -- php artisan migrate:status --no-interaction
runuser -u www-data -- php artisan db:show --counts --no-interaction
runuser -u www-data -- php artisan schedule:list --no-interaction
runuser -u www-data -- php artisan queue:failed --no-interaction
supervisorctl -c /etc/supervisor/junta.conf status
```

As migrations devem estar todas em Ran; a ligação deve ser MySQL; devem
existir duas tarefas por minuto; php-fpm, nginx, queue e scheduler devem estar
RUNNING, além de mail-capture se ativo. A fila não deve ter falhas inesperadas.
4. Criar uma campanha e um ficheiro através da interface com as permissões
   adequadas. Registar os identificadores para verificar a persistência.
5. Redeploy do Junta: a campanha, a conta, o ficheiro e o login devem continuar
   disponíveis. A APP_KEY e os dois volumes devem permanecer iguais.
6. Reiniciar o serviço MySQL mantendo o volume. Depois da recuperação, confirmar
   novamente conta e campanha. `migrate --force` repetido deve informar que
   não há migrations pendentes, preservando os registos existentes.
7. Testar candidatura, aprovação, proposta e compra com os perfis adequados.
   No modo de demonstração, os pagamentos devem indicar explicitamente teste.
8. Confirmar nos logs as tarefas do agendador a executar. Verificar memória,
   CPU, utilização dos volumes e saldo de crédito no painel após os testes.

Volumes persistentes protegem contra substituição do contentor, não contra
eliminação do volume/conta, expiração de retenção ou erros humanos. Guardar
backups privados fora do Railway antes do fim do crédito; não usar o volume
efémero da aplicação para guardar o único dump.

## 8. Backup privado antes de alterações futuras

No computador, abrir `railway ssh --service MySQL`. No terminal Linux remoto:

```sh
umask 077
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE" > /tmp/junta-backup.sql
```

Obter Copy Service Instance ID no menu do serviço MySQL e, no computador,
substituir o placeholder pelo identificador da instância:

```powershell
scp ID_DA_INSTANCIA_MYSQL@ssh.railway.com:/tmp/junta-backup.sql ./junta-backup.sql
```

Verificar que o download está completo e guardar o ficheiro num local privado.
Não publicar o dump. `/tmp` é efémero; descarregar antes de reiniciar o serviço.
Para copiar também os uploads, no terminal remoto do Junta:

```sh
umask 077
tar --exclude='app/private/mailpit.db*' -czf /tmp/junta-files.tar.gz -C /var/www/html/storage app
```

Descarregar do computador:

```powershell
scp TEU-DOMINIO.up.railway.app@ssh.railway.com:/tmp/junta-files.tar.gz ./junta-files.tar.gz
```

Fazer a cópia numa janela sem alterações aos dados, para manter a coerência
entre MySQL e ficheiros. A caixa de email de ensaio não faz parte deste backup.

## 9. Atualizações via GitHub

Depois do primeiro deploy validado, ativar Autodeploy na branch `railway-deploy`
se desejares publicar cada push. Os workflows existentes executam em pushes
para `main` e em pull requests; manter Wait for CI desligado nesta branch até
existirem workflows configurados para a executar. Cada publicação
reutiliza as variáveis, executa migrations pendentes e verifica `/up`.
[Autodeploys](https://docs.railway.com/deployments/github-autodeploys).

Para diagnosticar: 502 costuma indicar porta/arranque; 500 pode indicar chave,
permissões ou ligação MySQL; 419 pode indicar cookies/HTTPS/APP_URL. Consultar
logs privados com APP_DEBUG=false. Se a chave ou uma password ficar exposta,
revogar/rodar o segredo afetado; nunca publicá-lo para pedir ajuda.

## Validação da publicação no Railway

- Build Docker e assets concluídos na infraestrutura Railway, com TLS normal.
- 13 migrations executadas; repetição sem migrations pendentes e sem reset de dados.
- Laravel confirmado a usar `junta_app`; a conta não pode ler `mysql.user`.
- Páginas HTTPS, assets, registo e cookies Secure verificados externamente.
- Verificação assinada e recuperação testadas com os emails efetivamente
  capturados pelo Mailpit; login com a nova palavra-passe confirmado.
- Conta e sessão preservadas após reiniciar MySQL e republicar a aplicação.
- Mailpit acessível em `http://127.0.0.1:8025` pelo túnel SSH privado neste computador.
- Backup da base descarregado para um ficheiro SQL local, ignorado pelo Git.

## Validação local desta preparação

- 13 testes existentes de produção e proxy HTTPS: passaram, 79 assertions.
- Build com PHP 8.5.13 e assets React/Vite: concluído; 128 assets servidos por HTTP.
- MySQL 8.4.11: 13 migrations aplicadas; repetição sem alterações aos dados.
- Registo, SMTP interno, verificação assinada de email, recuperação e login:
  passaram com contas fictícias, cookies Secure e headers HTTPS do ingress.
- Contas, ficheiro de teste e caixa de email preservados após recriar ambos
  os contentores com os mesmos volumes; health check Docker saudável.
- Imagem recusa APP_KEY ausente, PORT inválido e arranque Railway sem volume.

O build local exigiu uma cópia temporária do Dockerfile com tolerância de três
horas apenas para datas futuras dos metadados Debian, mantendo assinatura e TLS.
Essa opção não foi introduzida no Dockerfile de produção. O Railway não suporta
mounts BuildKit `type=secret`: os mounts opcionais do certificado local foram
retirados do Dockerfile de produção. Composer e npm continuam com a validação TLS
normal; nenhum certificado específico deste computador é necessário no Railway.

Antes de integrar alterações futuras em `main`, executar também a suite completa
local com `php artisan test --compact`. Nenhum PHP de negócio ou teste foi
modificado nesta preparação.
