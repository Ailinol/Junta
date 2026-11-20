# E6 — Plano de apresentação de oito minutos

**Projecto:** Junta — compra colectiva. **Duração total:** 8:00, ou 480 segundos.  
**Apresentadores:** [nomes/números e distribuição de papéis].  
**Referência:** UEM, material de Teste de Software, pp. 37–38.

## 1. Preparação anterior à sessão

1. Abrir os quatro documentos, `MoneyTest.php`, `DeliveryFeeAllocationTest.php`, `MoneyValidationTest.php`, `phpunit.unit.xml` e o workflow. Fechar o ficheiro privado de acessos e `.env` antes de partilhar o ecrã.
2. Instalar as dependências pelos lockfiles com antecedência. Confirmar PHP 8.5/Node 24 e o driver de cobertura disponível; não descarregar extensões durante os oito minutos.
3. Executar a regressão completa anteriormente e conservar JUnit/Clover/HTML. Ela demorou 400,19 s com instrumentação nesta máquina; repeti-la ao vivo consumiria quase toda a apresentação.
4. Usar apenas SQLite de teste e dados de factories. Desactivar serviços pagos/chaves reais no processo. Não demonstrar reset/migrations numa base publicada.
5. Abrir o [HTML unitário](evidencias/cobertura-unitaria-html/index.html) e [global](evidencias/cobertura-global-html/index.html). Preparar o XML como alternativa se o navegador não abrir ficheiros locais.
6. Cronometrar o roteiro. A demonstração em directo é a execução real dos testes pequenos; os resultados longos serão identificados como execução prévia, não simulados.

## 2. Sequência cronometrada

| Tempo     | Duração | Conteúdo/ecrã                                                                                                       | Responsável editável |
| --------- | ------- | ------------------------------------------------------------------------------------------------------------------- | -------------------- |
| 0:00–0:45 | 45 s    | Objectivo do Junta, critérios UEM e as três perguntas: o que, como e quando considerar testado                      | [A]                  |
| 0:45–1:35 | 50 s    | Arquitectura Laravel/React/Inertia/MySQL; perfis; distinguir pagamento simulado de gateway real                     | [B]                  |
| 1:35–2:20 | 45 s    | RF01/RF02/RF03, riscos e pirâmide desejada; não chamar unitário ao ensaio com BD/processos                          | [B]                  |
| 2:20–3:05 | 45 s    | Matriz: quantidade 0/1, validade no instante limite e preço fora do conversor; rastreabilidade CT→teste             | [C]                  |
| 3:05–4:45 | 100 s   | Demonstração em directo: teste pequeno, E3 completo e cobertura 95%; mostrar oráculo e preparação/chamada/asserções | [C]                  |
| 4:45–5:45 | 60 s    | EXP01, defeito confirmado e significado de teste diagnóstico verde; limitação honesta da alegação de TDD            | [C]                  |
| 5:45–6:40 | 55 s    | CI a cada push, limiar, captura de resultados; regressão prévia 412/3 e JS 54; GitHub remoto ainda pendente         | [D]                  |
| 6:40–7:30 | 50 s    | SC01/SC02 e cinco critérios de aceitação; quem revê quem e quem aceita                                              | [A]                  |
| 7:30–8:00 | 30 s    | Lições, DEF-01/DEF-02 e pendências MySQL/carga/cliente/TDD; conclusão sem declarar ausência de defeitos             | [A]                  |

**Soma:** 45 + 50 + 45 + 45 + 100 + 60 + 55 + 50 + 30 = **480 s**.

## 3. Demonstração executável

### 3.1. Preparar o terminal local

No PowerShell, usando o driver temporário da execução documentada:

```powershell
$env:PAO_DISABLE = 'true'
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
$env:OPENAI_ENABLED = 'false'
$env:OPENAI_API_KEY = ''
$env:INERTIA_SSR_ENABLED = 'false'
$env:XDEBUG_MODE = 'coverage'
$driver = Join-Path $env:TEMP 'junta-uem-xdebug-3.5.3.dll'
```

### 3.2. Aos 3:05 — Executar testes pequenos em directo

```powershell
php vendor/bin/pest tests/Unit/MoneyTest.php --filter=M03

php -d "zend_extension=$driver" -d xdebug.mode=coverage vendor/bin/pest `
  --configuration phpunit.unit.xml --coverage --min=90
```

Explicar que o primeiro comando exercita comissão/arredondamento; o segundo executa 36 casos e mede 38/40 linhas nos dois módulos. Abrir o relatório e mostrar a diferença entre 100% do rateio, 83,33% de Money e 95% agregado. Os dois caminhos monetários restantes dependem do Validator e têm cobertura de componente separada.

### 3.3. Aos 4:45 — Demonstrar o defeito conhecido

```powershell
php vendor/bin/pest tests/Feature/MoneyValidationTest.php --filter=EXP01
```

Mostrar as duas asserções: submissão aceite e selecção recusada por `amount`. Declarar: «o teste passou porque reproduziu o defeito; o requisito de consistência monetária continua reprovado nesta partição». Mostrar DEF-01 no relatório.

Não apresentar estes testes de código preexistente como prova de TDD. Descrever Red→Green→Refactor e a forma de guardar evidência nas próximas alterações legítimas. Se for necessário demonstrar uma falha do gate, o comando abaixo é opcional e deve substituir parte da explicação, não aumentar a duração:

```powershell
# Falha de limiar intencional: 95% é menor do que 100%.
php -d "zend_extension=$driver" -d xdebug.mode=coverage vendor/bin/pest `
  --configuration phpunit.unit.xml --coverage --min=100
```

Essa falha é de **política de cobertura**, não um Red de TDD nem um defeito novo. Não editar a aplicação publicada para produzir uma falha visual.

### 3.4. Aos 5:45 — CI e evidências prévias

Mostrar no YAML: eventos, ambiente isolado, E3, mínimo de casos, regressão e upload com `if: always()`. Mostrar o JUnit da execução local e a cobertura global: **412 PHP aprovados, 3 ignorados; 54 JS aprovados; 88,62% de linhas em app/**. Explicar por que os subconjuntos não se somam à regressão.

Se ainda não existir execução publicada do workflow, dizer isso expressamente e não mostrar uma execução de outro commit como se fosse esta. Após publicação, substituir a observação pela URL/revisão e pelo artefacto da execução efectivamente correspondente.

## 4. Cenários e mensagem final

- **SC01:** Ana e Helena compram 3/2 unidades, o fornecedor propõe, Carlos selecciona e a administração confirma portes. Verificar totais 367,50/245 MZN, fornecimento, código e conferência financeira.
- **SC02:** só Ana paga; mínimo insuficiente; reembolso integral da referência original, sem duplicação.
- Mostrar que a aceitação depende do cliente; os percursos completos ainda carecem de homologação. Os componentes automatizados não substituem essa decisão.

Mensagem de fecho proposta: «Temos evidência automatizada dos comportamentos executados e cobertura medida com âmbito explícito. Encontrámos um defeito monetário e uma lacuna de teste que permanecem registados. A próxima entrega deve corrigi-los, confirmar MySQL/CI remoto e obter aceitação do cliente.»

## 5. Contingência e avaliação

Se falhar o terminal, guardar/mostrar o erro real e usar os relatórios pré-gerados, identificados como tal. Não simular uma execução bem-sucedida. Se faltar Xdebug, executar os testes sem cobertura e apresentar o relatório medido anteriormente; não inventar uma percentagem.

No ensaio geral, conferir: duração de 480 s, testes legíveis no ecrã, inexistência de credenciais visíveis, leitura clara dos números, distinção verificação/validação, TDD/caracterização, ignorado/aprovado e unidade/integração/sistema. Reservar perguntas para depois dos oito minutos, salvo regra diferente do docente.
