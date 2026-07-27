# Migração controlada de Eventos

## Fronteira de responsabilidade

- **ADAM Comunidade** é a origem canónica de eventos, categorias, locais, gestão, páginas públicas, arquivo, calendário, pesquisa e REST.
- **ADAM Sócios** mantém inscrições de sócios, lista de espera, presenças, QR check-in, pontos, bónus, recompensas, estatísticas de participação e comunicação com sócios.
- **ADAM Bot** consome os filtros públicos fornecidos pelo Comunidade.

O contrato suportado entre plugins é `adam_comunidade_events()`. Consumidores não devem instanciar repositórios ou controladores do Comunidade.

## Inventário da implementação anterior

Não existiam tabelas de base de dados, endpoints REST, shortcodes ou tarefas cron específicos de Eventos. A persistência era feita por opções:

- `adam_membership_events`
- `adam_membership_event_next_id`
- `adam_membership_event_registrations`
- `adam_membership_event_registration_next_id`
- `adam_membership_event_checkins`
- `adam_membership_event_checkin_next_id`
- `adam_membership_event_bonus_lock_{event_id}`
- `adam_membership_events_rewrite_version`

Hooks e rotas anteriores:

- `init`: regras e atualização de rewrite
- `query_vars`: `adam_events`, `adam_event`, `adam_event_checkin`
- `template_redirect`: arquivo, detalhe e check-in
- `wp_enqueue_scripts`: estilos
- `admin_menu`
- `admin_post_adam_membership_save_event`
- `admin_post_adam_membership_delete_event`
- `/eventos/`
- `/eventos/{slug}/`
- `/eventos/check-in/{token}/`

Dependências encontradas:

- `EventService`, `EventRepository`, `EventFrontend` e `EventController`
- `PointsService`: movimentos `event_check_in` e `event_checkin_bonus`
- `StatisticsService`, `StatisticsController` e `PointsController`
- Dashboard administrativo de Sócios
- `MemberRepository`, histórico e estado ativo para check-in
- QR externo, nonces, aviso de imagem/vídeo e definições de privacidade
- ADAM Bot: `adam_bot_dynamic_events` e `adam_bot_knowledge_event_items`

## Estratégia de dados

A migração copia cada evento para `adam_comunidade_events`, mantendo o ID original e todos os metadados, incluindo slug, imagem, datas, permissões, token de check-in e estado. A sequência seguinte também é mantida.

As opções legadas não são eliminadas. Constituem uma cópia de rollback e permitem que o ADAM Sócios funcione em modo de compatibilidade se o Comunidade for temporariamente desativado. Inscrições e check-ins permanecem nas opções dos Sócios porque pertencem ao domínio dos membros.

A migração é idempotente, nunca substitui um registo canónico já existente e grava um relatório em `adam_comunidade_events_migration_report`.

## Compatibilidade

- Os URLs `/eventos/`, `/eventos/{slug}/` e `/eventos/check-in/{token}/` não mudam.
- IDs usados pelo livro de pontos, estatísticas e bónus não mudam.
- O arquivo e detalhe são renderizados pelo Comunidade.
- A rota de check-in é do Comunidade, mas a interação autenticada é delegada aos Sócios.
- A administração legada dos Sócios só aparece quando a API do Comunidade não está disponível.
- As ligações do painel de Sócios encaminham administradores para a gestão canónica.

## Rollback

Desativar temporariamente o Comunidade faz o repositório dos Sócios voltar a ler as opções legadas. Não apagar as opções legadas até uma janela de produção validada e uma cópia de segurança confirmada. Um rollback não exige reconstrução de IDs nem de URLs.

## Matriz de regressão

Automatizado neste repositório:

- importação e idempotência
- IDs, slugs, imagens, tokens e próxima sequência
- preservação de inscrições e check-ins
- criação, edição, eliminação e consultas pela API
- URLs e integração REST/Bot
- propriedade administrativa e bridge dos Sócios
- sintaxe PHP e testes existentes dos dois plugins

Requer validação num WordPress de staging com dados reais:

- entrega de emails e notificações
- leitura de QR por câmara
- lançamento efetivo de pontos/bónus/recompensas
- permissões por perfis reais
- widgets do tema e pesquisa global
- apresentação em dispositivos móveis e desktop
- cache, redirects e sitemap/SEO
