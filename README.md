# Inova CRM — MVP PHP + MySQL

MVP funcional de CRM multiempresa com identidade visual Inova CRM: login seguro, dashboard, clientes, oportunidades, tarefas, planos Free/Pro/Premium, limites no servidor, painel administrativo global, suspensão/reativação e auditoria.

## Requisitos

- PHP 8.1+ com PDO MySQL, sessões e `password_hash()`;
- MySQL/MariaDB com suporte a `utf8mb4`;
- Apache com `mod_rewrite` ou acesso direto a `public/index.php`.

O pacote não presume que a InfinityFree ofereça todos esses recursos. Confirme versões, limites, termos de uso e política sobre uso comercial antes de usar dados reais.

## Instalação local

1. Crie um banco MySQL.
2. Importe `database/schema.sql` **dentro** desse banco (o arquivo não cria nem seleciona banco, então funciona no phpMyAdmin da hospedagem e pode ser reimportado sem risco).
3. Copie `.env.example` para `.env` e preencha os dados do banco. O sistema carrega o `.env` sem depender do Composer.
4. Aponte o document root do servidor para `public/`.
5. Crie o primeiro administrador **em ambiente local ou terminal seguro**:

```bash
php database/seed.php "Seu Nome" voce@example.com "UmaSenhaLonga-e-Unica"
```

No XAMPP no Linux, execute o seed com o PHP do próprio XAMPP para usar o driver MySQL incluído nele:

```bash
/opt/lampp/bin/php database/seed.php "Seu Nome" voce@example.com "UmaSenhaLonga-e-Unica"
```

Se preferir usar o `php` do sistema, instale/habilite a extensão PDO MySQL correspondente à versão ativa do PHP (por exemplo, `php8.3-mysql`) e confirme com `php -m | grep pdo_mysql`.

6. Acesse `public/index.php?page=login`.

O arquivo `.env` deve ficar na raiz do projeto, fora de `public/`. Nunca publique a senha real no repositório ou envie o `.env` para terceiros.

Se a hospedagem não tiver SSH, execute o seed localmente contra o banco remoto apenas se isso for permitido, ou crie o usuário com um procedimento administrativo seguro. Não coloque a senha no código.

## Estrutura

- `public/`: ponto público, CSS e logo.
- `app/`: helpers, autenticação e acesso PDO.
- `config/`: configuração PHP; as credenciais ficam no `.env`, fora do web root.
- `database/`: schema e criação do administrador.
- `tests/`: roteiro manual de testes.

## Segurança implementada

PDO com prepared statements, hash de senhas, regeneração de sessão, cookie HttpOnly/SameSite, timeout de sessão por inatividade, cabeçalhos HTTP básicos, CSRF em mutações e login/logout, escape HTML, checagem de tenant, validações de entrada, rate limit persistente de login, autorização do painel global e auditoria administrativa. O limite bloqueia após 5 falhas por e-mail ou 20 por IP em 15 minutos; o IP é armazenado como hash.

Para produção ainda recomendamos recuperação de senha segura, MFA para administradores, política de privacidade/LGPD, backup externo criptografado, monitoramento, uso de SFTP/FTPS no deploy e revisão profissional.

## Backup

Faça exportação manual com `mysqldump` em ambiente local/administrativo, armazene uma cópia criptografada fora do servidor e teste a restauração em outro banco. Nunca guarde a única cópia no mesmo servidor.

## Limitações do MVP

A alteração de plano é manual pelo administrador. Não há gateway de pagamento, e-mail automático, cron, upload, múltiplos pipelines, recuperação automática de senha nem exclusão de dados. O downgrade preserva dados, mas o bloqueio de novas inserções deve ser respeitado pela aplicação.

## Publicação na InfinityFree (sem SSH)

1. Importe `database/schema.sql` no phpMyAdmin do banco da hospedagem. Se estiver atualizando um banco existente, importe também `database/migrate_login_attempts.sql` antes de publicar a versão atualizada.
2. Gere o hash da senha no seu computador (`php -r "echo password_hash('SUA_SENHA', PASSWORD_DEFAULT);"`), edite `database/create_admin.sql` e execute na aba SQL.
3. O deploy do GitHub **não envia o `.env`** (está no `exclude` do workflow e no `.gitignore`). Envie o `.env` uma vez por FTP/Gerenciador de Arquivos para `/htdocs/.env`, com os dados do banco da hospedagem (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
4. Acesse `https://seu-dominio/public/index.php?page=login`.

Os arquivos `.htaccess` incluídos bloqueiam arquivos ocultos e extensões privadas, além de diretórios como `app/`, `config/`, `database/`, `storage/` e `tests/`. Confirme abrindo `https://seu-dominio/.env`, `/.ftp-deploy-sync-state.json` e `/database/schema.sql`: devem retornar 403/404. O `.htaccess` depende de Apache e pode ser ignorado por outros servidores; prefira apontar o document root para `public/`.

## Se aparecer erro ao entrar

Os erros são gravados em `storage/logs/app.log`. Para ver o motivo na tela, coloque `APP_DEBUG=1` no `.env` do servidor, tente de novo e **volte para `0`** depois. Causas comuns: `.env` ausente no servidor, usuário/senha do banco errados, `DB_HOST` incorreto (na InfinityFree é algo como `sqlXXX.infinityfree.com`, não `localhost`) ou tabelas não importadas.
