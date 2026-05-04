# Equatorial - Passagem de Turno (Backend)

Backend em Laravel 12 para a aplicacao de passagem de turno. Este README foi feito para quem nao tem familiaridade com a tecnologia.

## Visao geral

- API REST para o sistema de passagem de turno.
- Autenticacao via Sanctum.
- Fila de jobs configurada por padrao no banco (database).
- Assets com Vite.

## Requisitos

- PHP 8.2+ com extensoes comuns do Laravel (pdo, mbstring, openssl, tokenizer, xml, ctype, json).
- Composer.
- Node.js 18+ e npm.
- Git (para clonar o repositorio).
- Banco de dados (padrao: SQLite).

## Inicio rapido (1 comando)

Opcao recomendada (faz tudo automaticamente):

```bash
composer setup
```

Esse comando instala dependencias, cria o arquivo [.env](.env) se nao existir, gera `APP_KEY`, roda migrations, instala npm e gera o build.

## Passo a passo super detalhado (primeira vez)

Use este passo a passo se voce nunca mexeu com isso.

### 0) Baixe o projeto com git

- Abra um terminal e rode:

```bash
git clone https://github.com/Equatorial-Passagem-de-Turno/equatorial-backend.git
```

### 1) Antes de tudo

- Tenha PHP 8.2+, Composer, Node.js 18+ e npm instalados.
- Abra a pasta do projeto no VS Code.

### 2) Abra o terminal

- No VS Code: menu Terminal > New Terminal.
- Um terminal aparece na parte de baixo.

### 3) Entre na pasta do projeto

- Digite `cd` e aperte Enter.
- Se voce acabou de clonar, rode:

```bash
cd equatorial-backend
```

- Se precisar de caminho completo (ajuste se necessario):

```bash
cd C:\Users\wesle\Documents\equatorial-backend
```

### 4) Crie o arquivo de configuracao

- O arquivo [.env](.env) precisa existir.
- Se ele nao existe, crie copiando de [.env.example](.env.example):

```bash
copy .env.example .env
```

### 5) Instale as dependencias do PHP

```bash
composer install
```

### 6) Gere a chave do app

```bash
php artisan key:generate
```

- Abra [.env](.env) e confirme que `APP_KEY` nao esta vazio.

### 7) Prepare o banco de dados

- O padrao eh SQLite.
- Se der erro dizendo que o banco nao existe, crie o arquivo:

```bash
New-Item -Path database/database.sqlite -ItemType File -Force
```

- Rode as migrations:

```bash
php artisan migrate
```

### 8) Instale dependencias do frontend

```bash
npm install
```

### 9) Gere os arquivos do frontend

```bash
npm run build
```

### 10) Rode o projeto

```bash
composer dev
```

- Nao feche esse terminal.
- Abra o navegador e entre em: `http://localhost:8000`

### 11) Parar o projeto

- No terminal, aperte `Ctrl + C`.

## Rodar em desenvolvimento

Recomendado (sobe tudo junto):

```bash
composer dev
```

Alternativa simples (duas janelas de terminal):

Terminal 1:

```bash
php artisan serve
```

Terminal 2:

```bash
npm run dev
```

API local: `http://localhost:8000`

## Usabilidade do dia a dia

- Rotas de API: [routes/api.php](routes/api.php)
- Rotas web (se houver): [routes/web.php](routes/web.php)
- Controllers: [app/Http/Controllers](app/Http/Controllers)
- Models: [app/Models](app/Models)
- Variaveis principais no [.env](.env): `APP_URL`, `DB_CONNECTION`, `DB_DATABASE`, `QUEUE_CONNECTION`
- Para filas: `php artisan queue:listen` (ja incluso no `composer dev`)
- Para dados de exemplo: `php artisan db:seed`

## Docker (opcional)

Existe um [Dockerfile](Dockerfile) para rodar via Apache.

```bash
docker build -t equatorial-backend .
docker run --rm -p 8080:80 --name equatorial-backend equatorial-backend
```

Se o container ainda nao tiver chave ou banco criado, rode dentro dele:

```bash
php artisan key:generate
php artisan migrate
```

## Problemas comuns

- Erro sobre `APP_KEY`: rode `php artisan key:generate`.
- Erro de banco: confira `DB_CONNECTION` e rode `php artisan migrate`.
- Assets nao atualizam: rode `npm run dev` ou `npm run build`.
