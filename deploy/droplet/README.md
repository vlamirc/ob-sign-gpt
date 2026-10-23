# Protótipo em um Droplet DigitalOcean

Esta configuração mantém Laravel/Apache, worker da fila, PostgreSQL 17 e um runtime Python no mesmo Droplet. Caddy termina HTTPS. Só Caddy publica portas; PostgreSQL, Apache e Python ficam sem porta pública. Não há Redis, banco gerenciado nem Kubernetes.

**Escopo desta PR:** infraestrutura de demonstração. `APP_ENV=production` e `APP_DEBUG=false` são fixados pelo Compose. O bloqueio atual de envio/aceite de assinaturas em produção permanece ativo; a interface e os rascunhos podem ser avaliados, mas esta PR não libera o fluxo jurídico. Não mudar para `local` para contornar o bloqueio. Basic Auth protege o protótipo inteiro, além do login dos operadores.

## Droplet e rede

Sugestão inicial de engenharia, sem benchmark: Ubuntu 24.04 LTS x86_64, 2 vCPU e 4 GB RAM. Há build de frontend, geração de PDF e banco na mesma máquina; um plano menor exige medir memória e eventualmente compilar fora do servidor. Confirmar preço/região no painel antes de contratar.

1. Criar Droplet com chave SSH e usuário de administração; instalar Docker Engine e plugin Compose v2 pelo repositório oficial. Habilitar Docker no boot.
2. Criar registro DNS A do domínio apontando para o IPv4 do Droplet. Só configurar AAAA se IPv6 estiver realmente habilitado.
3. Associar Cloud Firewall: TCP 22 apenas dos IPs administrativos; TCP 80/443 públicos para HTTPS/ACME. Saída liberada inicialmente. Não liberar 5432, 8000 ou 8025. Não confiar apenas em UFW para portas publicadas pelo Docker.
4. Manter atualizações de segurança, monitoramento de disco/memória e backups. Acesso ao grupo Docker equivale a acesso administrativo ao host.

## Preparar segredos

Clonar o repositório em `/opt/ob-sign-gpt`, fazer checkout do commit revisado e executar na raiz:

```bash
cp deploy/droplet/env.example .env.droplet
chmod 600 .env.droplet
openssl rand -base64 32
openssl rand -hex 32
docker run --rm -it caddy:2-alpine caddy hash-password
```

Editar `.env.droplet`:
- `APP_DOMAIN`: domínio sem protocolo nem caminho; `ACME_EMAIL`: e-mail válido.
- `APP_KEY`: `base64:` seguido do primeiro resultado (32 bytes); criar uma única vez, guardar com os backups e nunca regenerar em uma atualização.
- `DB_PASSWORD`: segundo resultado. Alterá-lo no arquivo após a primeira inicialização **não** altera a senha do banco existente.
- `PREVIEW_PASSWORD_HASH`: hash retornado pelo Caddy, entre aspas simples para preservar os sinais de dólar. A senha é uma barreira compartilhada do piloto, não autenticação de assinatura.
- SMTP transacional: host, porta, usuário, senha, remetente e esquema compatíveis com o provedor (`smtp` com STARTTLS ou `smtps` para TLS implícito). Não instalar servidor de e-mail no Droplet.

Não versionar esse arquivo, imprimir `docker compose config` sem `--quiet`, nem copiar segredos para a PR. Não há segredo na imagem. Nenhum token da DigitalOcean é necessário para os comandos deste guia.

## Primeiro deploy

```bash
./deploy/droplet/dc config --quiet
./deploy/droplet/dc build app
./deploy/droplet/dc up -d --wait postgres
./deploy/droplet/dc run --rm app php artisan migrate --force
./deploy/droplet/dc run --rm --user www-data app php artisan assina:operator operador@seu-dominio.com --name="Operador"
./deploy/droplet/dc up -d --wait app queue caddy
./deploy/droplet/dc ps
```

Abrir `https://SEU_DOMINIO`, passar pelo Basic Auth e entrar com o operador criado. Testar login, upload/consulta de rascunho e download privado. O Caddy exige DNS correto e acesso às portas 80/443 para emitir certificado. O healthcheck `/up` verifica o boot HTTP; não comprova SMTP, ACT ou funcionamento integral do banco. Migrações são explícitas, nunca executadas simultaneamente por cada worker.

A imagem instala dependências Composer sem ferramentas de desenvolvimento e compila assets via `npm ci`; Boost continua disponível no desenvolvimento, não no servidor. Os lockfiles permanecem inalterados. Não há scheduler porque o projeto ainda não possui tarefas agendadas de negócio.

## Python: integração pendente do script real

Não há arquivo Python na branch base. O serviço opcional `python` prepara um runtime 3.13 no mesmo host, mas não inventa um processador de assinaturas nem chama a ACT.

Para um script **de execução única e que use apenas a biblioteca padrão**, disponibilizar `deploy/droplet/python-source/main.py` no servidor, legível pelo UID 10001, e executar:

```bash
./deploy/droplet/dc --profile python run --rm python
```

O diretório está fora do Git e do build da aplicação. O mount é somente leitura; escrita temporária é permitida em `/tmp`. Não há credenciais do Laravel, acesso a documentos, dependências pip, integração HTTP/fila ou agendamento implícitos. O teste da PR usa um pequeno script temporário apenas para verificar o runtime.

Antes de integrar o script real, definir seu código, bibliotecas travadas, entrada/saída, segredos, acesso ao banco/arquivos e se é serviço contínuo ou tarefa pontual. Se ele já existe fora deste repositório, trazê-lo para uma próxima alteração revisada. Não ativar o profile no `up` geral esperando um worker permanente.

## Persistência e backup

Volumes: `postgres-data` (banco), `app-storage` (PDFs/evidências e storage Laravel), `caddy-data` (certificados) e `caddy-config`. O projeto Compose tem nome fixo `ob-sign`; não mudar esse nome sem migrar os volumes. Nunca usar `down -v` em ambiente com dados.

Para obter um backup consistente do protótipo, em janela de indisponibilidade, pausar todos os escritores (inclusive eventual script Python) e executar:

```bash
umask 077
BACKUP_DIR="backups/$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"
./deploy/droplet/dc stop caddy queue app
./deploy/droplet/dc exec -T postgres pg_dump -U assina -d assina -Fc > "$BACKUP_DIR/database.dump"
./deploy/droplet/dc run --rm --no-deps app tar -C /var/www/html -czf - storage > "$BACKUP_DIR/storage.tar.gz"
cp .env.droplet "$BACKUP_DIR/environment"
./deploy/droplet/dc up -d --wait app queue caddy
```

Verificar códigos de saída e tamanho dos arquivos; se um comando falhar, corrigir e não tratar o backup como válido. Guardar cópia cifrada **fora do Droplet**, com a chave da aplicação. Backup no próprio disco não protege contra perda do servidor. Agendamento, destino externo e retenção ainda devem ser definidos. Snapshots/backup do Droplet complementam, não substituem, teste de restauração.

Restaurar primeiro em outro host/projeto isolado: mesmos segredos e versão de código, PostgreSQL vazio, `pg_restore -U assina -d assina --exit-on-error` recebendo o dump; extrair storage no volume e ajustar dono para `www-data`; depois subir aplicação/worker. Conferir hashes e abrir um documento de teste. Não restaurar sobre banco em uso.

## Atualizar e reverter

1. Fazer backup consistente e registrar SHA atual.
2. Parar Caddy, worker e aplicação; checkout do novo SHA revisado; usar um novo `RELEASE_TAG` no arquivo de ambiente.
3. Build, migração explícita e `up -d --wait app queue caddy` como no primeiro deploy. Recriar containers também após mudanças de segredos/configuração. Nunca rodar `composer setup` no servidor.
4. Conferir login, upload e fila: `./deploy/droplet/dc exec -T --user www-data app php artisan queue:failed`.
5. Para rollback de código, restaurar SHA/tag anterior e recriar containers. Migrações não são automaticamente reversíveis: se incompatíveis, restaurar banco **e** arquivos do backup correspondente, aceitando a perda de alterações posteriores. Não executar `migrate:rollback` às cegas.

Os logs Docker têm rotação. Apache não registra URLs de acesso para não gravar tokens dos convites. Erros ainda podem conter dados pessoais: restringir acesso aos logs. O worker tem encerramento gracioso de 90 segundos, timeout de 60 e retry_after de 120. Todo o sistema compartilha o mesmo ponto de falha; isso é uma escolha do protótipo.

## Validação e referências

A PR acrescenta workflow `Droplet container smoke test`: build da imagem, migração em PostgreSQL nativo, HTTP, geração de links HTTPS, persistência após recriar container, validação Caddy e runtime Python. O teste não cria recursos na DigitalOcean nem emite certificados públicos. A homologação em Droplet real e o teste com o script Python definitivo permanecem pendentes.

- https://docs.digitalocean.com/products/compute/
- https://docs.digitalocean.com/products/droplets/how-to/create/
- https://docs.digitalocean.com/products/networking/firewalls/
- https://docs.docker.com/engine/install/ubuntu/
- https://docs.docker.com/compose/how-tos/startup-order/
- https://caddyserver.com/docs/automatic-https
- https://laravel.com/docs/13.x/deployment
