# Changelog

Todas as mudanças relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto utiliza [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Unreleased]

## [0.1.5] - 2026-10-15

### Fixed

- `csrf_input()` agora pertence ao pacote e cuida da geração e reutilização do token da sessão sem exigir tratamento nos controllers ou views.
- Falhas de entropia na geração do token agora interrompem a operação com `CsrfTokenGenerationException`, preservando a `RandomException` original para o tratamento global.
- O skeleton não declara mais uma segunda função `csrf_input()`, eliminando a ambiguidade que fazia o PhpStorm herdar incorretamente o `@throws RandomException` do arquivo em `vendor`.

## [0.1.4] - 2026-10-15

### Fixed

- O logging de requisições agora abrange respostas `404` e `405`, preserva o cabeçalho `Allow` e evita registros duplicados.

## [0.1.3] - 2026-10-15

### Fixed

- O container agora respeita dependências tipadas com valor padrão quando não há registro explícito, permitindo usar `useRequestLogging()` com o logger HTTP padrão e ainda substituí-lo pela aplicação.

## [0.1.2] - 2026-10-15

### Fixed

- O logging de requisições agora registra o status HTTP final, inclusive após antiforgery, exceções e filtros de exceção.

## [0.1.1] - 2026-09-09

### Fixed

- CLI instalada pelo Composer agora localiza corretamente o autoloader do projeto consumidor.

## [0.1.0] - 2026-09-07

Primeira versão pública.

### Added

- Pacote Composer `palacios/framework` para PHP 8.5.
- Bootstrap centralizado para configurar serviços, middleware e rotas.
- Container com lifetimes singleton, scoped e transient.
- Controllers declarativos, descoberta automática e binding convencional.
- Pipeline de middleware, filtros, autenticação, autorização e antiforgery.
- Minimal APIs, grupos de rotas, metadados e geração de URLs.
- Resultados HTTP, Problem Details, ambientes, views e acesso a banco via PDO.
- Testes HTTP em memória, PHPUnit e PHPStan.
- CLI com criação de aplicações, geradores e migrations.
- Skeleton independente distribuído com o pacote.
- Integração contínua para PHP 8.5.
