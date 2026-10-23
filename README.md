# Assina — MVP de assinatura eletrônica

Laravel 13, Livewire 4, Flux, PostgreSQL e fila em banco. Nome provisório. Repositório local, sem vínculo com GitHub e sem implantação pública.

## Estado do piloto

Upload privado de PDF (até 10 MB), 1–10 signatários, painel por operador, convite de sete dias, código de e-mail de dez minutos, aceite expresso, conferência SHA-256, auditoria encadeada e pacote ZIP de evidências. O ZIP preserva o PDF original e inclui relatório PDF e manifesto JSON. Cadastro público desabilitado; operadores são provisionados pelo terminal.

**Ainda não é uma solução liberada para uso jurídico em produção.** Envio e aceite são bloqueados com `APP_ENV=production`. Não há assinatura criptográfica PAdES, certificado ICP-Brasil ou carimbo do tempo integrado. O estado final do piloto é `awaiting_timestamp`. Datas locais e hashes não substituem uma ACT nem armazenamento imutável. E-mail OTP confirma controle da caixa postal, não identidade civil. O código não atribui validade jurídica automaticamente aos documentos.

## Executar localmente

Requisitos: PHP 8.5 com PDO PostgreSQL, SQLite (testes), mbstring, XML, DOM, fileinfo, ZIP e GD; Composer 2; Node 22+; Docker Compose para PostgreSQL/Mailpit. Use as versões travadas nos lockfiles.

```bash
cp .env.example .env
composer install
php artisan key:generate
docker compose up -d
php artisan migrate
npm ci
npm run build
php artisan assina:operator operador@example.com --name="Operador"
php artisan serve
```

O comando de operador solicita senha sem exibi-la. Use um endereço sob seu controle; o provisionamento considera esse e-mail conferido pelo administrador. Não existe senha padrão. Em outro terminal:

```bash
php artisan queue:work --tries=3 --timeout=60
```

Acesse o endereço exibido por `artisan serve`. O Mailpit fica em `http://localhost:8025`; captura mensagens apenas localmente, sem enviá-las a pessoas reais. Ajuste `APP_URL` ao endereço utilizado antes de gerar convites. Abra o convite no Mailpit, solicite o código, consulte a segunda mensagem, abra o PDF e confirme o aceite. Todos os signatários precisam aceitar para gerar o pacote. Para retomar falhas de fila: `php artisan queue:failed` e, após corrigir a causa, `php artisan queue:retry <id>`.

Não use `composer setup` em uma instalação existente: ele gera outra chave de aplicação. Guarde `APP_KEY` com os backups; sessões e convites em fila usam criptografia. Nunca versionar `.env`, banco ou documentos. O volume do Compose contém o banco; não executar `docker compose down -v` se quiser preservá-lo.

## Verificações

```bash
composer test
npm run build
composer audit
```

`phpunit.xml` usa SQLite em memória. Para PostgreSQL real, crie um banco **exclusivo de teste** chamado `assina_test`, configure host/usuário/senha e execute `composer test:pgsql`. Os testes apagam tabelas; nunca apontar para banco de uso. Há workflow preparado com PostgreSQL 17, ainda não executado no GitHub.

A suite cobre autorização por proprietário, fluxo completo, OTP/expiração/tentativas, cancelamento, consentimento, integridade e geração idempotente do pacote. Não representa teste de carga ou homologação de concorrência.

## Laravel Boost

Boost instalado e consultado via MCP na implementação. Configuração em `.codex/config.toml`, orientações em `AGENTS.md`, skills em `.agents/skills`. O comando do servidor é `php artisan boost:mcp`. No ambiente do agente, PHP precisa estar no PATH. Referência oficial: https://laravel.com/for/agents .

## Próxima etapa

1. Obter documentação, credenciais de homologação e política da ACT contratada. O preço informado é R$ 180/mês por 1.500 carimbos (R$ 0,12 por unidade incluída). Reservar esse valor, sem presumir tarifa excedente ou fornecedor confirmado.
2. Definir o artefato a carimbar, integração RFC 3161/API, validação de cadeia e política, persistência do token, retentativas e exportação verificável. Uma conclusão por documento deixa margem nominal de 500 carimbos para 1.000 documentos, antes de retentativas/outros usos.
3. Homologar evidências, termos, tipos de documento aceitos e política de retenção com responsável jurídico. Implementar armazenamento imutável, backups com teste de restauração e regras de acesso/eliminação.
4. Configurar domínio/HTTPS, SMTP transacional, monitoramento, proteção de logs, worker supervisionado e recuperação de finalizações interrompidas. Convites não têm reenvio pelo operador nesta versão.
5. Executar testes com PostgreSQL nativo, concorrência e navegador, revisão de segurança e integração real da ACT antes de retirar o bloqueio de produção. Não contornar o bloqueio mudando o ambiente de um serviço público para `local`.

A infraestrutura foi mantida enxuta: uma aplicação, PostgreSQL, fila no próprio banco, arquivos privados locais ou S3 compatível (adaptador instalado). Redis, Kubernetes, assinatura qualificada e operação como autoridade certificadora estão fora do MVP. Valores de hospedagem, SMTP e retenção dependem da contratação e não estão orçados aqui.

## Resultado desta entrega

- 47 testes aprovados, 226 assertions; dois testes de cadastro público ignorados porque o recurso está desativado.
- Suite aprovada em SQLite e via PDO PostgreSQL contra PGlite (PostgreSQL em WebAssembly). PGlite não substitui um servidor PostgreSQL nativo e não homologa concorrência/locks de produção. A conexão PostgreSQL fixa UTC para evitar deslocamentos nos prazos.
- Pint aprovado, PHPStan sem erros, build de frontend aprovado, Composer validate aprovado e Composer audit sem avisos de vulnerabilidade na consulta realizada.
- Não houve envio real de e-mails, contratação de serviços, publicação ou conexão com GitHub. Testes de navegador e PostgreSQL nativo ainda pendentes.
