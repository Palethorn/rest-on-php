# Introduction
rest-on-php is a REST API framework built on top of symfony components. This document explains how to set up rest-on-php from scratch with minimal explanation. You can find more detailed documentation for each component here https://symfony.com/components.
rest-on-php uses Doctrine ORM/DBAL for database access. You can find the documentation here https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/getting-started.html.

To start developing right away install rest-on-php-project which has base environment already in place.
```
php spectar/composer create-project --stability dev palethorn/rest-on-php-project my_project
```

# Installation

```
composer require palethorn/rest-on-php
```

# Configuration

### config/cli-config.php
This configuration creates configuration for running doctrine console commands.
Example:

```php
<?php
use Doctrine\ORM\Tools\Console\ConsoleRunner;
require_once __DIR__ . '/../vendor/autoload.php';
$parameters = Symfony\Component\Yaml\Yaml::parseFile(__DIR__ . '/parameters.yml');
$parameters = $parameters['parameters'];

// replace with mechanism to retrieve EntityManager in your app
$doctrineEntityManagerFactory = new RestOnPhp\Factory\DoctrineEntityManagerFactory();
$entityManager = $doctrineEntityManagerFactory->create(
    $parameters['database_host'],
    $parameters['database_port'],
    $parameters['database_name'],
    $parameters['database_user'],
    $parameters['database_password'],
    $parameters['project_dir'] . '/config',
    $parameters['cache_dir'],
    $parameters['entity_namespace']
);

return ConsoleRunner::createHelperSet($entityManager);
```

### config/migrations.yml
If you want doctrine migrations support then include this file. Modify to suit your needs. Detailed documentation here https://www.doctrine-project.org/projects/doctrine-migrations/en/2.2/reference/configuration.html

```yaml
name: "Migrations"
migrations_namespace: "Migrations\\Namespace"
table_name: "doctrine_migration_versions"
column_name: "version"
column_length: 14
executed_at_column_name: "executed_at"
migrations_directory: "doctrine_migrations"
all_or_nothing: true
check_database_platform: true
```

### config/parameters.yml
Example parameters.yml is included with the project. Productions one should look something like this:

```yaml
parameters:
    environment: prod # usually dev, test, staging, production
    project_name: Video Share Platform # Title for your project
    project_dir: /srv/video_share # Where your project is located
    log_dir: /var/log/video_share # Where the application logs go
    cache_dir: /var/cache/video_share # Where the application writes cache
    database_host: 127.0.0.1 # Host of your database, if you run mysql in separate containers or on separate servers change this to reflect that
    database_port: 3306 # Standard mysql port, change this if you use non-standard mysql ports or proxies
    database_name: video_share_database # Mysql database where your tables live
    database_user: video_share_user # Non-permissive user
    database_password: video_share_password
    dns_name: video-share.example.com # Domain under which your application lives
    ssl: true # Does it use https? Used for generating URLs and redirects
    jwt_secret: UvKLsxg2Be5v4Fun # Key with which JWT API tokens are signed
    entity_namespace: VideoShare\Entity # Namespace for your entities. If you already have entities generated, specify their namespace here
    user_entity: VideoShare\Entity\User # Entity which will be used for authentication and authorization of users accessing the API
    token_bearer: cookie # Supports cookie, header, and query_parameter.
    token_key: token # Key which holds token value. Ex. token=eyJhbGciOiJIUzI1NiIsInR5...
```

### config/resources.xml
Definition of API resources. Empty file for now:

```xml
<?xml version="1.0" encoding="utf-8"?>
<mapping 
    xmlns="urn:mapping" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:mapping ../vendor/palethorn/rest-on-php/config/api-resource.xsd">

</mapping>
```

### config/routing.yml
Import framework routing table:

```yaml
api:
    resource: '../vendor/palethorn/rest-on-php/config/routing.yml'
    prefix: /
```

### config/services.yml
Default service loading. Not recommended to alter this file.

```yaml
imports:
    - { resource: parameters.yml }
    - { resource: '../vendor/palethorn/rest-on-php/config/services.yml' }
    - { resource: services/*.yml }

services:
```

### config/services
Directory for all your additional service definitions. You can have for example:
- config/services/handlers.yml
- config/services/commands.yml
- config/services/filters.yml
- config/services/autofillers.yml
- config/services/normalizers.yml

### config/doctrine_mapping
Directory for holding doctrine mapping files

### doctrine_migrations
Directory for holding database migrations. You can change this in ```config/migrations.yml``` under ```migrations_directory``` key.

### src
Directory for application PHP code. Put services, handlers, commands, and other PHP classes here.

### web
Keep your publicly accesible static files here. index.php resides here also.

### web/index.php
Entry point for your application.

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;

ErrorHandler::register();

$request = Request::createFromGlobals();
$kernel = new Kernel('prod', false);
$response = $kernel->handle($request);
$response->send();
```

For dev purposes and debugging you can also create index_dev.php which enables verbose error reporting and debug component.

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;

Debug::enable();
$error_handler = ErrorHandler::register();

$request = Request::createFromGlobals();
$kernel = new Kernel('dev', true);

$response = $kernel->handle($request);
$response->send();
```