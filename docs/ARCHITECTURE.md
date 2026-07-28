# Arquitetura do ADAM Comunidade

Este documento descreve as fronteiras internas que devem permanecer estáveis
durante a evolução do plugin. O objetivo é permitir alterações incrementais
sem acoplamento direto entre módulos ou com outros plugins ADAM.

## Responsabilidades no ecossistema

ADAM Comunidade é a fonte canónica de conteúdo público:

- Equipas;
- Campos;
- Parceiros, incluindo a categoria Marca;
- Instituições;
- Notícias;
- Eventos e respetivos vocabulários.

ADAM Sócios é responsável por sócios e pelas suas interações:

- contas, adesões e quotizações;
- cartões digitais;
- pontos, recompensas e benefícios;
- inscrições de sócios em Eventos;
- presenças e check-in;
- comunicações dirigidas a sócios.

ADAM Sócios deve consumir Eventos exclusivamente através de
`adam_comunidade_events()` e dos hooks públicos. Não deve aceder aos
repositórios, opções ou tabelas internas deste plugin.

## Arranque e módulos

`Loader` é o composition root. Instancia serviços transversais e regista os
módulos através de `Module_Manager`. Cada módulo implementa
`Module_Interface`, possui o seu próprio bootstrap e pode ser desativado pelo
filtro `adam_comunidade_module_enabled`.

```text
Loader
├── serviços nucleares: definições, páginas, assets e administração
└── módulos
    ├── Teams
    ├── Fields
    ├── Directory
    ├── Events
    ├── Experience
    └── Managers
```

Um módulo não deve instanciar controladores internos de outro módulo. A
comunicação é feita por fachadas públicas, interfaces, actions ou filters.

## Camadas e convenções

- `Schema` e `Migration`: definição, atualização idempotente e reparação de
  dados.
- `Repository`: persistência e consultas; não renderiza HTML.
- modelos/objetos de valor: representação segura de um registo.
- `Service`: regras de negócio e operações transacionais.
- `Controller`/`Router`/`Portal`: autorização, coordenação HTTP e resposta.
- `templates` e `View`: apresentação e escaping final.
- `Uploads\Handler`: validação e persistência de uploads.
- `Uploads\Component`: interface reutilizável do uploader.
- `Experience\Email_Service`: composição e envio de emails.
- `Config`: limites operacionais e políticas filtráveis.

Novas regras de negócio não devem ser adicionadas a templates. SQL não deve
ser adicionado a controladores quando pertence a um repositório ou serviço.

## Configuração operacional

`ADAM\Comunidade\Config` centraliza valores que não são conteúdo editorial:

- tamanhos de página;
- TTL de caches;
- políticas de upload;
- duração e limite de sessões de Gestores;
- validade e retenção de tokens;
- limites de tentativas de autenticação.

Extensões podem usar:

- `adam_comunidade_cache_ttl`;
- `adam_comunidade_upload_policy`;
- `adam_comunidade_manager_security_policy`.

Os filtros recebem sempre valores já limitados novamente pelo núcleo. Não
devem ser usados para armazenar segredos.

## Uploads e media

Todo o processamento de ficheiros passa por `Uploads\Handler`. O serviço:

1. normaliza uploads simples e múltiplos;
2. valida erros de transporte, extensão, tamanho e quantidade;
3. utiliza a API de Media do WordPress;
4. elimina anexos parciais quando um lote falha.

O componente visual `Uploads\Component` não processa ficheiros. Formulários
novos devem combinar o componente com o handler, sem duplicar validação.

## Gestores, autenticação e permissões

As contas de Gestor são deliberadamente independentes de utilizadores
WordPress e de ADAM Sócios. `Managers\Auth` é responsável apenas por sessões,
cookies e CSRF; `Managers\Service` coordena convites, atribuições e revisões.
`Managers\Policy` define os tipos, campos e listas permitidos numa proposta,
mantendo essa configuração fora da autenticação e da persistência.

- tokens aleatórios são guardados apenas como hashes;
- convites e recuperações são de utilização única e expiram;
- cookies são `HttpOnly`, `SameSite=Lax` e `Secure` em HTTPS;
- apenas Gestores ativos com uma atribuição ativa podem editar;
- Gestores nunca publicam diretamente;
- decisões administrativas exigem capability e nonce.

Alterações à política temporal devem ser feitas através de `Config`, nunca
através de números dispersos.

## Submissões e moderação

Submissões públicas são guardadas separadamente dos registos publicados. O
padrão é Post/Redirect/Get. A aprovação:

1. bloqueia a submissão com `SELECT ... FOR UPDATE`;
2. valida novamente o payload;
3. grava o registo e relações numa transação;
4. atualiza o estado;
5. confirma a transação;
6. envia notificações e actions após a confirmação.

Revisões de Gestores preservam um snapshot da versão publicada e uma proposta
separada. Existe no máximo uma revisão ativa por entidade. A versão pública só
muda após aprovação e todas as decisões ficam no histórico.

## Eventos

`Events\Api`, exposta por `adam_comunidade_events()`, é a única fronteira
suportada para outros plugins. Inscrições e presenças são delegadas ao ADAM
Sócios através dos filtros documentados.

O armazenamento compatível atual é `Events\Option_Store`. `Events\Repository`
depende de `Events\Store_Interface`, permitindo introduzir armazenamento
indexado sem mudar a API, URLs ou consumidores. Uma implementação alternativa
pode ser fornecida por `adam_comunidade_events_store`.

Para instalações com milhares de Eventos ou pesquisas complexas, deve ser
criado um store indexado e uma migração versionada. Não se deve alterar a
fachada `Events\Api`.

Os aliases `brands`, `marcas` e shortcodes de Marca são mantidos apenas por
compatibilidade. O modelo canónico é Parceiro com a categoria `brand`.

## Emails

`Experience\Email_Service` é o único emissor de emails de ciclo de vida. O
serviço valida destinatário, remetente, URLs, placeholders e ambiente antes de
renderizar. Templates configuráveis são combinados com defaults seguros.

Integrações podem fornecer o layout visual através de
`adam_render_branded_email`. Falhas do renderer externo são registadas e
ativam o layout local. Chamadas diretas a `wp_mail()` fora deste serviço devem
ser evitadas.

## Base de dados e migrações

Cada módulo que possui tabelas mantém uma versão própria. Migrações devem ser:

- versionadas e automáticas;
- idempotentes;
- retomáveis após falha;
- compatíveis com instalações existentes;
- verificadas antes do módulo ficar disponível.

Nunca se deve assumir que uma coluna nova já existe. Erros internos são
registados com códigos seguros; SQL e stack traces não são apresentados ao
utilizador.

## Actions de domínio

As actions seguintes são contratos de integração:

| Action | Momento |
| --- | --- |
| `adam_comunidade_submission_received` | submissão persistida |
| `adam_comunidade_submission_moderated` | decisão de submissão concluída |
| `adam_comunidade_organisation_saved` | organização atualizada por qualquer fluxo |
| `adam_comunidade_manager_assigned` | atribuição ativa criada |
| `adam_comunidade_manager_invited` | convite de utilização única criado |
| `adam_comunidade_manager_deleted` | Gestor removido e acessos revogados |
| `adam_comunidade_manager_revision_submitted` | revisão confirmada |
| `adam_comunidade_manager_revision_moderated` | decisão de revisão confirmada |
| `adam_comunidade_manager_revision_approved` | revisão publicada |
| `adam_comunidade_manager_revision_rejected` | revisão rejeitada |
| `adam_comunidade_event_saved` | Evento criado ou atualizado |
| `adam_comunidade_event_published` | transição para publicado |
| `adam_comunidade_event_deleted` | Evento removido |

Callbacks devem tratar estes eventos como notificações posteriores à gravação,
não como oportunidade para alterar a transação já concluída.

## Escalabilidade

As listagens públicas utilizam paginação, limites máximos, cache e
pré-carregamento de relações para evitar consultas N+1. APIs limitam
`per_page`. Galerias devem guardar IDs de anexos e usar os tamanhos gerados
pelo WordPress.

Antes de acrescentar uma consulta a uma listagem:

1. confirmar que os filtros possuem índice adequado;
2. evitar uma consulta por cartão;
3. limitar coleções;
4. invalidar caches depois de escritas;
5. medir com dados representativos.

## Regras para evolução

- preservar IDs, slugs, URLs públicas e hooks documentados;
- adicionar migrações antes de usar novo schema;
- preferir dependências injetadas e interfaces em fronteiras de módulo;
- adicionar testes de contrato a novas APIs e hooks;
- manter strings visíveis em PT-PT e através das funções de tradução;
- documentar aliases obsoletos e removê-los apenas numa versão principal;
- não criar uma segunda implementação de Equipas, Campos, Parceiros,
  Instituições, Notícias ou Eventos noutro plugin ADAM.
