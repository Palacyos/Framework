# Usa a imagem base do PHP com Apache
FROM php:8.2-apache

# Define o diretório de trabalho dentro do container
WORKDIR /var/www/html

# Instala pacotes do sistema e configura o fuso horário
RUN apt-get update && apt-get install -y \
    git unzip tzdata \
 && ln -snf /usr/share/zoneinfo/America/Belem /etc/localtime \
 && echo "America/Belem" > /etc/timezone \
 && rm -rf /var/lib/apt/lists/*

# Instala extensões PHP necessárias (pdo e pdo_mysql para o banco de dados)
RUN docker-php-ext-install pdo pdo_mysql

# --- INÍCIO DAS MUDANÇAS PARA O COMPOSER ---

# Instala o Composer globalmente no container
# Copia o binário do Composer da imagem oficial do Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copia os arquivos composer.json e composer.lock para o diretório de trabalho
# Isso permite que o Composer instale as dependências. É importante copiar antes do "composer install"
# para aproveitar o cache do Docker.
COPY composer.json composer.lock ./

# Instala as dependências do Composer
# --no-dev: não instala dependências de desenvolvimento (bom para produção)
# --optimize-autoloader: otimiza o autoloader para melhor performance
RUN composer install --no-dev --optimize-autoloader

# --- FIM DAS MUDANÇAS PARA O COMPOSER ---

# Copia o restante dos arquivos da sua aplicação para o container
# O .dockerignore deve ser usado para excluir arquivos desnecessários (como node_modules, .git, etc.)
# Este comando deve vir DEPOIS do composer install para que o cache do Docker seja usado de forma eficiente.
COPY . .

# Configura o Apache para usar a pasta 'public' como DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Habilita o mod_rewrite e permite AllowOverride All para .htaccess
RUN a2enmod rewrite
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Expõe a porta 80 (padrão do Apache)
EXPOSE 80