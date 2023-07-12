# Introduction
rest-on-php is a REST API framework built on top of symfony components. This document explains how to set up rest-on-php from scratch with minimal explanation. Find more detailed documentation for each component here https://symfony.com/components.
rest-on-php uses Doctrine ORM/DBAL for database access. Find the documentation here https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/getting-started.html.

To start developing right away install rest-on-php-project which has base environment already in place.
```
php spectar/composer create-project --stability dev palethorn/rest-on-php-project my_project
```

# Installation

```
composer require palethorn/rest-on-php
```

# Configuration

RestOnPhp uses XML for configuration files. It enables autocomplete and easy validation.

### bin/console
Enable executing symfony commands.

```php
#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use RestOnPhp\Kernel;

$kernel = new Kernel('cli', true);
$application = $kernel->getDependencyContainer()->get('api.command.application');

$application->run();
```

Execute ```chmod +x bin/console```

### config/cli-config.php
This configuration creates configuration for running doctrine console commands.
Example:

```php
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use RestOnPhp\Kernel;

require_once __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel('cli', false);
$dependencyContainer = $kernel->getDependencyContainer();
$parameters = $dependencyContainer->getParameterBag()->all();

// replace with mechanism to retrieve EntityManager
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
Include this file for migrations support. Modify to suit the application needs. Detailed documentation here https://www.doctrine-project.org/projects/doctrine-migrations/en/2.2/reference/configuration.html

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-migrations xmlns="http://doctrine-project.org/schemas/migrations/configuration"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://doctrine-project.org/schemas/migrations/configuration
          ../vendor/doctrine/migrations/lib/Doctrine/Migrations/Configuration/XML/configuration.xsd">

    <name>Migrations</name>
    <migrations-namespace>Migrations</migrations-namespace>
    <table name="doctrine_migration_versions" column="version" column_length="14" executed_at_column="executed_at"></table>
    <migrations-directory>doctrine_migrations</migrations-directory>
    <all-or-nothing>true</all-or-nothing>
    <check-database-platform>true</check-database-platform>
</doctrine-migrations>
```

### config/parameters.xml
Example parameters.xml is included with the project. Productions one should look something like this:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<container xmlns="http://symfony.com/schema/dic/services"
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:framework="http://symfony.com/schema/dic/symfony"
xsi:schemaLocation="http://symfony.com/schema/dic/services
    https://symfony.com/schema/dic/services/services-1.0.xsd
    http://symfony.com/schema/dic/symfony
    https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">
    <parameters>
        <!-- # usually dev, test, staging, production -->
        <parameter key="environment">prod</parameter>
        <!-- # Title for the project -->
        <parameter key="project_name">Application</parameter>
        <!-- # Host of application database. If mysql runs in separate containers or on separate servers change this to reflect that -->
        <parameter key="database_host">127.0.0.1</parameter>
        <!-- # Standard mysql port, change this if database uses non-standard ports or proxies -->
        <parameter key="database_port">3306</parameter>
        <!-- # Mysql database where database tables live -->
        <parameter key="database_name">app_database</parameter>
        <!-- # Non-permissive user -->
        <parameter key="database_user">app_user</parameter>
        <parameter key="database_password">app_password
        <!-- # Domain under which the application lives -->
        <parameter key="dns_name">app.example.com</parameter>
        <!-- # Does it use https? Used for generating URLs and redirects -->
        <parameter key="ssl">true</parameter>
        <!-- # Key with which JWT API tokens are signed -->
        <parameter key="jwt_secret">UvKLsxg2Be5v4Fun</parameter>
        <!-- # Namespace for doctirne entities. Specify namespace here for preexisting entities -->
        <parameter key="entity_namespace">App\Entity</parameter>
        <!-- # Entity which will be used for authentication and authorization of users accessing the API -->
        <parameter key="user_entity">App\Entity\User</parameter>
        <!-- # Supports cookie, header, and query_parameter. -->
        <parameter key="token_bearer">cookie</parameter>
        <!-- # Key which holds token value. Ex. token=eyJhbGciOiJIUzI1NiIsInR5... -->
        <parameter key="token_key">token</parameter>
    </parameters>
</container>
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

### config/routing.xml
Import framework routing table:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<routes xmlns="http://symfony.com/schema/routing"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/routing
        https://symfony.com/schema/routing/routing-1.0.xsd">
    <import resource="../vendor/palethorn/rest-on-php/config/routing.xml" prefix="/"></import>
</routes>
```

### config/services.xml
Default service loading. Not recommended to alter this file.

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">
    <imports>
        <import resource="../vendor/palethorn/rest-on-php/config/services.xml" />
        <import resource="parameters.xml" />
        <import resource="services/*.xml" />
    </imports>
</container>
```

### config/services
Directory for all additional service definitions. Example definition files:
- config/services/handlers.xml
- config/services/commands.xml
- config/services/filters.xml
- config/services/fillers.xml
- config/services/normalizers.xml

### config/doctrine_mapping
Directory for holding doctrine mapping files

### doctrine_migrations
Directory for holding database migrations. It's possible to change this directory in ```config/migrations.yml``` under ```migrations_directory``` key if required.

### src
Directory for application PHP code. Put services, handlers, commands, and other PHP classes here.

### src/Kernel.php
Extending default framework kernel is not necessary, but enables overriding default paths to project_dir, cache_dir, config_dir, public_dir, and log_dir parameters.

```php
// src/Kernel.php
namespace App;
use RestOnPhp\Kernel as RestOnPhpKernel;

class Kernel extends RestOnPhpKernel {

    public function getPublicDir() {
        return $this->getProjectDir() . '/web';
    }

    public function getCacheDir() {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir() {
        return $this->getProjectDir() . '/log';
    }

    public function getConfigDir() {
        return $this->getProjectDir() . '/config';
    }

    public function getProjectDir() {
        return __DIR__ . '/..';
    }
}
```

All these paths can be used as parameters in dependency container.

Edit web/index.php, config/cli-config.php and bin/console to use derived class.

### cache
Framework writes compiled parts as cache files into this directory. Default path is <project_root>/cache. It can be changed by overriding Kernel::getCacheDir in src/Kernel.php.

### log
Framework writes log files into this directory. Default path is <project_root>/log. It can be changed by overriding Kernel::getLogDir in src/Kernel.php.

### web
Keep publicly accesible static files here. index.php resides here also.

### web/index.php
Entry point for the application.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use RestOnPhp\Kernel;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;

ErrorHandler::register();

$request = Request::createFromGlobals();
$kernel = new Kernel('prod', false);
$response = $kernel->handle($request);
$response->send();
```

For dev purposes and debugging index_dev.php can also be created, which enables verbose error reporting and debug component.

```php
require_once __DIR__ . '/../vendor/autoload.php';

use RestOnPhp\Kernel;
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

Gist of it is to implement a class, register a service, and inject service wherever. More detailed explanation https://symfony.com/doc/current/components/dependency_injection.html.

## Creating a resource
Let's say there's a table in a database we wish to expose as a resource through a REST API.

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

A doctrine mapping file exists, Video.orm.xml

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

Doctrine entity exists.

```php
// src/Entity/Video
namespace App\Entity;

class Video {
    ...
}
```

A command exists to create default resource definition in config/resources.xml. Definition can be modified by need. Command throws exception if the resource definition already exists. It will not modify any existing resource definitions.
```
bin/console api:create-resource App\Entity\Video
```


To create a resource definition manually, edit config/resources.xml under mapping element, and add new resource element:

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
    <route name="delete" method="DELETE" path="/videos/{id}" ></route>

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

Resource is now available on http://app.example.com/index.php/videos. Resource definition should also be visible here http://app.example.com/index.php/docs.json

## Filters
Let's say that we only want to list published videos.
Implement a class

```php
// src/Filters/PublishedFilter.php
namespace App\Filters;
use Doctrine\ORM\QueryBuilder;

class PublishedFilter {
    public function filter(QueryBuilder $queryBuilder) {
        $queryBuilder->andWhere('r.published = :published');
        $queryBuilder->setParameter('published', 1);
        return $queryBuilder
    }
}
```

Register service:

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">

    <services>
        <service id="app.filter.published" class="App\Filter\PublishedFilter"></service>
    </services>
</container>
```

Specify filters on the resource definition:

```xml
    <resource 
        id="id" 
        secure="false"
        name="videos" 
        entity="App\Entity\Video">
    ...
    <filter id="app.filter.published_filter" />
```

## Filler
Filler set property values based on a custom logic before changes to database are applied. Example, updatedAt. Create a class:

```php
// src/Filler/UpdatedAtFiller
namespace App\Filler;

class UpdatedAtFiller {
    public function fill($object) {
        $object->setUpdatedAt(new \DateTime());
    }
}
```

Register service:

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">

    <services>
        <service id="app.filler.updated_at" class="App\Filler\UpdatedAtFiller">
        </service>
    </services>
</container>
```

Configure resource to use custom filler:

```xml
    <resource 
        id="id" 
        secure="false"
        name="videos" 
        entity="App\Entity\Video">
    ...
    <filler id="app.filler.updated_at" />
```

## Custom Handlers
Sometimes a conventional controller is required which doesn't quite fit with the data model. Although framework doesn't support normal MVC controllers, it supports request handlers. CRUD operations are executed by default ones built in framework. Those are ```CollectionHandler``` and ```ItemHandler```. They implement all basic functionality that is required for the API to operate (database querying, pagination, filtering). It's possible to implement a handler for a custom logic and custom response.

Create a handler class:

```php
// src/Handler/HelloHandler.php
namespace App\Handler;
use Symfony\Component\HttpFoundation\Response;

class HelloHandler {
    public function handle($who) {
        return new HandlerResponse(HandlerResponse::CARDINALITY_NONE, new Response(sprintf('Hello %s!', $who), 200, [
            'Content-Type' => 'text/plain'
        ]));
    }
}
```

Register a service:

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">

    <services>
        <service id="app.handler.hello" class="App\Handler\HelloHandler" public="true" />
    </services>
</container>
```

Service must be public.
Register a route on top:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<routes xmlns="http://symfony.com/schema/routing"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/routing
        https://symfony.com/schema/routing/routing-1.0.xsd">

    <route id="hello" path="/hello/{who}"
        controller="app.handler.hello"
        methods="GET"/>
        ...
</routes>
```

Invoking http://app.example.com/index.php/hello/world should give ```Hello world!``` response.

## Commands
Implement a command.

```php
// src/Command/HelloCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class HelloCommand extends Command {
    protected static $defaultName = 'app:hello';

    protected function configure() {
        $this->addArgument('who', InputArgument::REQUIRED, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln(sprintf('Hello %s!', $input->getArgument('who')));
        return 0;
    }
}
```

Register command as service:

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">

    <services>
        ...
        <service id="app.command.hello" class="App\Command\HelloCommand">
            <tag>command</tag>
        </service>
        ...
    </services>
</container>
```

Tag ```command``` is required.
Command can be executed ```bin/console app:hello world```.

## Security

Framework has a basic security implemented. If some of the resources require user to be authenticated, or a specific permission to be accessed, that can be configured on a resource definition. Just add ```secure="true"``` attribute like this.

```xml
...
    <resource 
        id="id" 
        secure="true"
        name="videos" 
        entity="App\Entity\Video">
...
```

Now the resource /videos requires authentication. Authorization is specified by using ```roles``` attribute as such:

```xml
...
    <resource 
        id="id" 
        secure="true"
        roles="USER|ADMIN|SUPERADMIN"
        name="videos" 
        entity="App\Entity\Video">
...
```

Authenticated user must have one of specified roles to be able to access the resource now.

To allow users to authenticate create users table in database with these required fields: username, password, roles.

```sql
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(64) NOT NULL,
  `password` varchar(255) NOT NULL,
  `roles` text DEFAULT NULL COMMENT '(DC2Type:array)'
)
```

Create a doctrine mapping and a user entity. User entity must have additional property called ```token``` with corresponding getter and setter.
User entity must implement ```RestOnPhp\Security\SecureUser```. Final entity should look something like this:

```php
namespace App\Entity;

use RestOnPhp\Security\SecureUser;

class User implements SecureUser {

    private $id;
    private $username;
    private $password;
    private $roles;
    private $token;

    public function __construct() {
        $this->roles = [];
    }

    public function getId() {
        return $this->id;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function setUsername($value) {
        $this->username = $value;
    }

    public function setPassword($value) {
        $this->password = $value;
    }

    public function getToken() {
        return $this->token;
    }

    public function setToken($token) {
        $this->token = $token;
    }

    public function getRoles() {
        return $this->roles;
    }

    public function addRole($role) {
        $this->roles[] = $role;
    }

    public function setRoles($value) {
        $this->roles = $value;
    }

    public function hasRole($role) {
        return in_array($role, $this->roles);
    }

    public function isSuperAdmin() {
        return $this->hasRole('SUPERADMIN');
    }
}
```

Change ```user_entity``` value in ```config/parameters.xml``` to this user entity. By this example it should be ```App\Entity\User```.
Creating a user using framework command:

```
bin/console api:create-user <username> <password>
```

Framework has build in LoginHandler. User logs in by sending post request to ```/login``` with the following json data:

```json
{
    "username": "<username>",
    "password": "<password>"
}
```

Response is a user information, json encoded, with a token property:
```json
{
    "id": 1,
    "roles": [],
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1OTg5NTQ3NzMsImlkIjoxfQ.duBdfnzY0GEDm1Ok1wLkrt_ix8HkVHGL3W1sGEs6DqU",
    "username": "user"
}
```

### Using a token to authorize
Depending on what is set as token_bearer and token_key in ```config/parameters.xml``` token is sent either as a ```query_parameter```, ```header```, or ```cookie```.
For token_key as ```token```, examples in curl are listed.

#### Token bearer "query_parameter"

```
curl -XGET "https://app.example.com/index.php/videos?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1OTg5NTQ3NzMsImlkIjoxfQ.duBdfnzY0GEDm1Ok1wLkrt_ix8HkVHGL3W1sGEs6DqU"
```

#### Token bearer "cookie"

```
curl -XGET -H "Cookie: token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1OTg5NTQ3NzMsImlkIjoxfQ.duBdfnzY0GEDm1Ok1wLkrt_ix8HkVHGL3W1sGEs6DqU" "https://app.example.com/index.php/videos"
```

#### Token bearer "header"

```
curl -XGET -H "token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE1OTg5NTQ3NzMsImlkIjoxfQ.duBdfnzY0GEDm1Ok1wLkrt_ix8HkVHGL3W1sGEs6DqU" "https://video-share.test.com/index.php/videos"
```

### Custom auth handler

### Custom authorization service

## Custom Normalizers
For the purpose of serialization and deserialization Symfony serializer component is used. More details here: https://symfony.com/doc/current/components/serializer.html. RestOnPhp uses default symfony normalizers. Performance issues may rise when trying to serialize doctrine entities which have deep relations to other entities. To avoid that, framework can be configured to use ```RestOnPhp\Normalizer\RelationNormalizer``` which returns ID values instead of whole nested objects. To use custom normalizers entity on which the normalization is applied should implement ```RestOnPhp\Normalizer\Normalizable``` interface, which in turn applies framework default normalizer: ```RestOnPhp\Normalizer\EntityNormalizer```. This normalizer reads resource definition and applies normalizers set on field definitions. To apply this behaviour follow the example.

Implement ```RestOnPhp\Normalizer\Normalizable``` interface:

```php
// src/Entity/Video.php
...
use RestOnPhp\Normalizer\Normalizable;

class Video implements Normalizable {
...
```

To implement custom normalizer for any field in resource definition, implement a normalizer class:

```php
// src/Normalizer/CustomNormalizer.php
namespace App\Normalizer;

class CustomNormalizer {

    public function normalize($object, $value) {
        // implement normalization logic here
    }

    public function denormalize($data, string $type) {
        // implement denormalization logic here
    }
}
```

Register normalizer as a service:

```xml
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services
        https://symfony.com/schema/dic/services/services-1.0.xsd
        http://symfony.com/schema/dic/symfony
        https://symfony.com/schema/dic/symfony/symfony-1.0.xsd">

    <services>
        <service id="app.serializer.normalizer.custom" class="App\Normalizer\CustomNormalizer">
            <tag>app.serializer.normalizers</tag>
        </service>
    </services>
</container>
```

Tag ```app.serializer.normalizers``` is required.
Specify on which field to use this normalizer:

```xml
<field name="customField" type="App\Entity\SomeEntityClass" normalizer="App\Normalizer\CustomNormalizer" />
```

Custom normalizer will apply normalize method on value before serialization.
To apply denormalization, entity class must implement ```RestOnPhp\Normalizer\Denormalizable``` interface, then, normalizer will apply denormalize method on value after deserialization.
