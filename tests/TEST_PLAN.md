# Roteiro de testes

1. Executar o schema e criar um administrador.
2. Confirmar login, logout e regeneração de sessão.
3. Confirmar que usuário comum não abre `?page=admin`.
4. Criar duas empresas e dois usuários; confirmar que clientes e oportunidades não cruzam entre tenants.
5. Tentar usar `customer_id` de outra empresa em uma oportunidade; deve ser rejeitado.
6. Atingir 5 clientes no Free; a sexta inserção deve ser bloqueada.
7. Alterar plano no painel global; confirmar auditoria em `admin_audit_logs`.
8. Suspender empresa; confirmar bloqueio operacional sem apagar dados.
9. Inserir valores com HTML; confirmar escape na tela.
10. Enviar formulário sem CSRF; confirmar erro 419.
11. Testar consultas com aspas e caracteres especiais.
12. Exportação CSV ainda é etapa posterior do MVP; não considerá-la implementada.
13. Testar telas em viewport móvel.
14. Simular indisponibilidade do banco e confirmar mensagem genérica no ambiente público.
15. Restaurar um backup em banco separado e conferir contagens.

16. Login: enviar credenciais inválidas e confirmar bloqueio após 5 falhas em 15 minutos para o mesmo e-mail; confirmar limite agregado por IP.
17. Login/logout: enviar formulários sem CSRF e confirmar erro 419; conferir que logout por GET não encerra a sessão.
18. Sessão: após 30 minutos de inatividade, confirmar redirecionamento para o login.
19. Tarefas: concluir e reabrir tarefa; tentar usar ID de outra empresa e confirmar que nada é alterado.
20. Oportunidades: editar título, valor, cliente e etapa; tentar cliente de outra empresa, etapa inválida, data inválida e valor negativo.
21. Conferir que o valor do pipeline exclui oportunidades ganhas e perdidas.
22. Acessar `/.env`, `/.ftp-deploy-sync-state.json` e `/database/schema.sql`; confirmar resposta 403/404 no servidor de produção.
