# Inova CRM — MVP PHP + MySQL

MVP funcional de CRM multiempresa com identidade visual Inova CRM: login seguro, dashboard, clientes, oportunidades, tarefas, planos Free/Pro/Premium, limites no servidor, painel administrativo global, suspensão/reativação e auditoria.

## Requisitos

- PHP 8.1+ com PDO MySQL, sessões e `password_hash()`;
- MySQL/MariaDB com suporte a `utf8mb4`;
- Apache com `mod_rewrite` ou acesso direto a `public/index.php`.

O pacote não presume que a InfinityFree ofereça todos esses recursos. Confirme versões, limites, termos de uso e política sobre uso comercial antes de usar dados reais.

## Instalação local

1. Crie um banco MySQL.
2. Execute `database/schema.sql`.
3. Copie `config/config.example.php` para `config/config.php` e preencha o banco.
4. Aponte o document root do servidor para `public/`.
5. Crie o primeiro administrador **em ambiente local ou terminal seguro**:

```bash
php database/seed.php "Seu Nome" voce@example.com "UmaSenhaLonga-e-Unica"
```

6. Acesse `public/index.php?page=login`.

Se a hospedagem não tiver SSH, execute o seed localmente contra o banco remoto apenas se isso for permitido, ou crie o usuário com um procedimento administrativo seguro. Não coloque a senha no código.

## Estrutura

- `public/`: ponto público, CSS e logo.
- `app/`: helpers, autenticação e acesso PDO.
- `config/`: exemplo de configuração; mantenha `config.php` fora do web root quando possível.
- `database/`: schema e criação do administrador.
- `tests/`: roteiro manual de testes.

## Segurança implementada

PDO com prepared statements, hash de senhas, regeneração de sessão, cookie HttpOnly/SameSite, CSRF em mutações, escape HTML, checagem de tenant em clientes e oportunidades, validação de cliente pertencente à empresa, autorização do painel global e auditoria administrativa.

Para produção ainda recomendamos rate limiting persistente de login, recuperação de senha, política de privacidade/LGPD, backup externo criptografado, monitoramento e revisão profissional.

## Backup

Faça exportação manual com `mysqldump` em ambiente local/administrativo, armazene uma cópia criptografada fora do servidor e teste a restauração em outro banco. Nunca guarde a única cópia no mesmo servidor.

## Limitações do MVP

A alteração de plano é manual pelo administrador. Não há gateway de pagamento, e-mail automático, cron, upload, múltiplos pipelines, recuperação automática de senha nem exclusão de dados. O downgrade preserva dados, mas o bloqueio de novas inserções deve ser respeitado pela aplicação.
