# VETTRYX WP Audit Log

> ⚠️ **Atenção:** Este repositório agora atua exclusivamente como um **Submódulo** do ecossistema principal `VETTRYX WP Core`. Ele não deve mais ser instalado como um plugin standalone (isolado) nos clientes.

Este submódulo registra e monitora silenciosamente todas as atividades críticas do sistema, servindo como a base de dados oficial para auditoria de segurança e geração de relatórios de SLA da VETTRYX Tech.

## 🚀 Funcionalidades

* **Monitoramento Invisível:** Registra logins, criação/edição/exclusão de conteúdo e atualizações de plugins, temas e core do WordPress sem impactar o tempo de resposta do servidor.
* **Tabela Isolada e Segura:** Utiliza banco de dados próprio otimizado (`wp_vettryx_audit_log`), prevenindo o inchaço e a lentidão das tabelas nativas do WordPress.
* **Retenção Inteligente (Cron):** Rotina automática diária atrelada ao WP-Cron que expurga dados mais antigos que 180 dias, garantindo um banco leve e em conformidade com o Marco Civil.
* **Integração com Reports:** Alimenta de forma automatizada o submódulo de relatórios mensais de manutenção.

## ⚙️ Arquitetura e Deploy (CI/CD)

Este repositório não gera mais arquivos `.zip` para instalação manual. O fluxo de deploy é 100% automatizado:

1. Qualquer push na branch `main` deste repositório dispara um webhook (Repository Dispatch) para o repositório principal do Core.
2. O repositório do Core puxa este código atualizado para dentro da pasta `/modules/`.
3. O GitHub Actions do Core empacota tudo e gera uma única Release oficial.

## 🛠️ Como Usar

Uma vez que o **VETTRYX WP Core** esteja instalado e o módulo ativado no painel do cliente:

1. Navegue até **VETTRYX Tech > Audit Log** no painel do WordPress.
2. A tabela de monitoramento será criada ou verificada com segurança no momento do acesso.
3. Visualize o histórico contínuo de ações, usuários e IPs registrados.

---

**VETTRYX Tech**
*Transformando ideias em experiências digitais.*
