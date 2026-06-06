# Framework

Framework MVC minimalista em PHP 8.2+ com arquitetura limpa baseada em repositórios, injeção de dependência e Tailwind CSS.

## Stack

- PHP 8.2+
- Tailwind CSS 4
- MySQL / MariaDB
- Docker
- Composer

## Arquitetura

```
app/
├── Controllers/        Recebem dependências via construtor (autowiring automático)
├── Core/
│   ├── App.php         Entry point — registra bindings e dispara o router
│   ├── Auth.php        Autenticação via sessão
│   ├── Connection.php  Singleton PDO
│   ├── Container.php   IoC Container com autowiring via Reflection
│   ├── Csrf.php        Geração e verificação de token CSRF
│   ├── Router.php      Roteador simples baseado em método + path
│   ├── Session.php     Wrapper de sessão
│   └── View.php        Renderização de views com layouts
├── Middlewares/        AuthMiddleware, CsrfMiddleware
├── Models/             Apenas propriedades, getters e setters — sem queries
├── Repositories/
│   ├── Interfaces/     Contratos (ClasseInterface.php)
│   └── *Repository.php Implementações concretas com PDO
└── Support/
    └── helpers.php     csrf_input(), e(), redirect()
```

## Como usar

### 1. Instalar dependências

```bash
composer install
npm install
```

### 2. Configurar ambiente

```bash
cp .env.example .env
# edite o .env com suas credenciais
```

### 3. Subir com Docker

```bash
docker-compose up -d
```

### 4. Compilar CSS

```bash
npm run dev   # watch
npm run build # produção
```

## Fluxo para adicionar uma feature

1. Crie o **Model** em `app/Models/`
2. Crie a **Interface** em `app/Repositories/Interfaces/ClasseInterface.php`
3. Crie o **Repository** em `app/Repositories/ClasseRepository.php`
4. Registre o binding em `app/Core/App.php`:
   ```php
   $container->bind(ClasseInterface::class, fn() => new ClasseRepository());
   ```
5. Crie o **Controller** injetando a interface pelo construtor
6. Adicione as **rotas** em `config/routes.php`
7. Crie as **Views** em `app/Views/`
