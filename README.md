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
    project_name: Application # Title for your project
    project_dir: /srv/app # Where your project is located
    log_dir: /var/log/app # Where the application logs go
    cache_dir: /var/cache/app # Where the application writes cache
    database_host: 127.0.0.1 # Host of your database, if you run mysql in separate containers or on separate servers change this to reflect that
    database_port: 3306 # Standard mysql port, change this if you use non-standard mysql ports or proxies
    database_name: app_database # Mysql database where your tables live
    database_user: app_user # Non-permissive user
    database_password: app_password
    dns_name: app.example.com # Domain under which your application lives
    ssl: true # Does it use https? Used for generating URLs and redirects
    jwt_secret: UvKLsxg2Be5v4Fun # Key with which JWT API tokens are signed
    entity_namespace: App\Entity # Namespace for your entities. If you already have entities generated, specify their namespace here
    user_entity: App\Entity\User # Entity which will be used for authentication and authorization of users accessing the API
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

### src/Kernel.php

Implement framework abstract kernel

```php
namespace App;
use RestOnPhp\Kernel as RestOnPhpKernel;

class Kernel extends RestOnPhpKernel {
    public function getProjectDir() {
        return __DIR__ . '/..';
    }
}

```

### cache
Framework writes compiled parts as cache files into this directory. Default path is <project_root>/cache. It can be changed by overriding Kernel::getCacheDir in src/Kernel.php.

### log
Framework writes log files into this directory. Default path is <project_root>/log. It can be changed by overriding Kernel::getLogDir in src/Kernel.php.

### web
Keep your publicly accesible static files here. index.php resides here also.

### web/index.php
Entry point for your application.

```php
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

# How to use the framework

## Creating a resource
Let's say there's a table in a database you wish to expose as a resource through a REST API.

```sql
CREATE TABLE video(
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255),
    filepath TEXT,
    url TEXT,
    published TINYINT(1) DEFAULT 0,
    container VARCHAR(16),
    audio_codec VARCHAR(32),
    video_codec VARCHAR(32),
    checksum VARCHAR(128),
    created_at datetime,
    updated_at datetime,
    published_at datetime NULL,
    tags TEXT NULL COMMENT "(DC2Type:array)";
);
```

You have a doctrine mapping file, Video.orm.xml

```xml
<?xml version="1.0" encoding="utf-8"?>
<doctrine-mapping 
    xmlns:ns="http://doctrine-project.org/schemas/orm/doctrine-mapping" 
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
    xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">
  <entity name="App\Entity\Video" table="video">
    <id name="id" type="integer" column="id">
      <generator strategy="IDENTITY"/>
    </id>

    <field name="filename" column="filename" type="string" />
    <field name="filepath" column="filepath" type="text" />
    <field name="url" column="url" type="text" />
    <field name="published" column="published" type="boolean" />
    <field name="container" column="container" type="string" />
    <field name="audioCodec" column="audio_codec" type="string" />
    <field name="videoCodec" column="video_codec" type="string" />
    <field name="checksum" column="checksum" type="string" />
    <field name="createdAt" column="created_at" type="datetime" />
    <field name="updatedAt" column="updated_at" type="datetime" />
    <field name="publishedAt" column="published_at" type="datetime" />
    <field name="tags" column="tags" type="array" />
  </entity>
</doctrine-mapping>
```

Specify the default repository class ```<entity repository-class="RestOnPhp\Repository\DefaultRepository" name="App\Entity\Video" table="video">...```

Doctrine entity exists.

```php
// src/Entity/Video
namespace App\Entity;

class Video {
    ...
}
```

Create a resource config in config/resources.xml under mapping element.

```xml
<resource 
    id="id" 
    secure="false"
    name="videos" 
    entity="App\Entity\Video">

    <route name="getCollection" method="GET" path="/videos" ></route>
    <route name="getItem" method="GET" path="/videos/{id}" ></route>
    <route name="create" method="POST" path="/videos" ></route>
    <route name="update" method="PATCH" path="/videos/{id}" ></route>
    <route name="replace" method="PUT" path="/videos/{id}" ></route>

    <field name="id" type="integer" />
    <field name="filename" type="string" />
    <field name="filepath" type="text" />
    <field name="url" type="text" />
    <field name="published" type="boolean" />
    <field name="container" type="string" />
    <field name="audioCodec" type="string" />
    <field name="videoCodec" type="string" />
    <field name="checksum" type="string" />
    <field name="createdAt" type="datetime" />
    <field name="updatedAt" type="datetime" />
    <field name="publishedAt" type="datetime" />
    <field name="tags" type="array" />

</resource>
```

Resource is now available on http://app.example.com/index.php/videos. You should also be able to see the listing on http://app.example.com/index.php/docs.json

## Autofilters
Let's say that you only want to list published videos.
Implement a class

```php
// src/Autofilters/PublishedFilter.php
namespace App\Autofilters;
use Doctrine\ORM\QueryBuilder;

class PublishedAutofilter {
    public function filter(QueryBuilder $queryBuilder) {
        $queryBuilder->andWhere('r.published = :published');
        $queryBuilder->setParameter('published', 1);
        return $queryBuilder
    }
}
```

Register service:

```yaml
# config/services/filters.yml
services:
    App\Autofilter\PublishedAutofilter:
        tags: [ name: api.autofilters.default ]
```

Tag ```api.autofilters.default``` is required.
Specify autofilters on the resource definition:

```xml
    <resource 
        id="id" 
        secure="false"
        name="videos" 
        entity="VideoShare\Entity\Video">
    ...
    <autofilter class="App\Autofilter\PublishedFilter" />
```

## Autofillers
Autofillers set property values based on a custom logic before changes to database are applied. Example, updatedAt. Create a class:

```php
// src/Autofiller/UpdatedAtAutofiller
namespace App\Autofiller;

class UpdatedAtAutofiller {
    public function fill($object) {
        $object->setUpdatedAt(new \DateTime());
    }
}
```

Register service:

```yaml
# config/services/autofillers.yml
services:
    App\Autofiller\UpdatedAtAutofiller:
        tags: [ name: api.autofillers.default ]
```

Tag ```api.autofillers.default``` is required.
Configure resource to use custom autofiller:

```xml
    <resource 
        id="id" 
        secure="false"
        name="videos" 
        entity="VideoShare\Entity\Video">
    ...
    <autofiller class="App\Autofiller\UpdatedAtAutofiller" />
```

## Custom Handlers
Sometimes a conventional controller is required which doesn't quite fit with the data model. Although framework doesn't support normal MVC controllers, it supports request handlers. CRUD operations are executed by default ones built in framework. Those are ```CollectionHandler``` and ```ItemHandler```. They implement all basic functionality that is required for the API to operate (database querying, pagination, filtering). You can implement your own handler for a custom custom logic and custom response.

Create a handler class:

```php
// src/Handler/HelloHandler.php
namespace App\Handler;
use Symfony\Component\HttpFoundation\Response;

class HelloHandler {
    public function handle($who) {
        return new Response(sprintf('Hello %s!', $who), 200, [
            'Content-Type' => 'text/plain'
        ]);
    }
}
```

Register a service:

```yaml
# config/services/handlers.yml
services:
    app.handler.hello:
        class: App\Handler\HelloHandler
        public: true
```

Service must be public.
Register a route on top:

```yaml
# config/routing.yml
hello:
    path: '/hello/{who}'
    methods: [ GET ]
    controller: app.handler.hello

api: ...
```

Then you can invoke http://app.example.com/index.php/hello/world which should give you ```Hello world!``` response.

## Commands

## Security
