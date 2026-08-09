# Graph Report - .  (2026-08-09)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 509 nodes · 954 edges · 32 communities (25 shown, 7 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 12 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `37cdbea7`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Community 0
- Community 1
- Community 2
- Community 3
- Community 4
- Community 5
- Community 6
- Community 7
- Community 8
- Community 9
- Community 10
- Community 11
- Community 12
- Community 13
- Community 14
- Community 15
- Community 16
- Community 17
- Community 18
- Community 19
- Community 20
- Community 21
- Community 25

## God Nodes (most connected - your core abstractions)
1. `QueryBuilder` - 27 edges
2. `BillingControllerTest` - 23 edges
3. `require` - 19 edges
4. `Container` - 19 edges
5. `Kernel` - 19 edges
6. `LicenseManager` - 19 edges
7. `AbstractController` - 18 edges
8. `ErrorPageRenderer` - 15 edges
9. `FileCache` - 14 edges
10. `LemonSqueezyClientTest` - 13 edges

## Surprising Connections (you probably didn't know these)
- `BillingController` --inherits--> `AbstractController`  [EXTRACTED]
  src/Application/BillingController.php → src/Core/Controller/AbstractController.php
- `Kernel` --references--> `Container`  [EXTRACTED]
  src/Core/Kernel.php → src/Core/Container/Container.php
- `Kernel` --references--> `LicenseManager`  [EXTRACTED]
  src/Core/Kernel.php → src/Services/LicenseManager.php
- `DashboardController` --inherits--> `AbstractController`  [EXTRACTED]
  src/Application/DashboardController.php → src/Core/Controller/AbstractController.php
- `DashboardController` --references--> `TemplateRenderer`  [EXTRACTED]
  src/Application/DashboardController.php → src/Core/Template/TemplateRenderer.php

## Import Cycles
- None detected.

## Communities (32 total, 7 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (21): Environment, Psr\Http\Message\ResponseInterface, Psr\Http\Message\ServerRequestInterface, Psr\Http\Server\MiddlewareInterface, Psr\Http\Server\RequestHandlerInterface, DashboardController, HelloController, TodoController (+13 more)

### Community 1 - "Community 1"
Cohesion: 0.04
Nodes (46): autoload, autoload-dev, psr-4, psr-4, bin, description, homepage, license (+38 more)

### Community 2 - "Community 2"
Cohesion: 0.06
Nodes (13): Nyholm\Psr7\Factory\Psr17Factory, self, PremiumRequired, RequiredScope, ErrorPageRenderer, Closure, HttpBridge, Kernel (+5 more)

### Community 3 - "Community 3"
Cohesion: 0.06
Nodes (13): Psr\Container\ContainerInterface, RouterInterface, Inject, Service, Singleton, Container, FastRouter, RouteExtractor (+5 more)

### Community 4 - "Community 4"
Cohesion: 0.10
Nodes (12): Command, DbMigrateCommand, DbSeedCommand, MakeControllerCommand, MakeUsecaseCommand, RouteCacheCommand, ConnectionFactory, PDO (+4 more)

### Community 5 - "Community 5"
Cohesion: 0.11
Nodes (10): MigrationInterface, PDO, SeederInterface, down(), MigrationRunner, PDO, up(), SeederRunner (+2 more)

### Community 6 - "Community 6"
Cohesion: 0.18
Nodes (4): PDOStatement, PDO, QueryBuilder, static

### Community 7 - "Community 7"
Cohesion: 0.12
Nodes (7): LemonSqueezyClientInterface, PHPUnit\Framework\TestCase, LemonSqueezyClient, Closure, HelloControllerTest, LemonSqueezyClientTest, Closure

### Community 8 - "Community 8"
Cohesion: 0.15
Nodes (6): LicenseManagerInterface, Psr\Log\LoggerInterface, LoggerFactory, PremiumAccessMiddleware, LicenseManager, DateTimeImmutable

### Community 10 - "Community 10"
Cohesion: 0.23
Nodes (3): DateInterval, Psr\SimpleCache\CacheInterface, FileCache

### Community 11 - "Community 11"
Cohesion: 0.25
Nodes (4): Nikanzo\Services\LemonSqueezyClientInterface, Nikanzo\Services\LicenseManagerInterface, BillingController, DateTimeImmutable

### Community 12 - "Community 12"
Cohesion: 0.19
Nodes (10): down(), PDO, up(), down(), PDO, up(), down(), PDO (+2 more)

### Community 13 - "Community 13"
Cohesion: 0.29
Nodes (4): Psr\EventDispatcher\EventDispatcherInterface, Psr\EventDispatcher\ListenerProviderInterface, EventDispatcher, ListenerProvider

### Community 15 - "Community 15"
Cohesion: 0.25
Nodes (3): extendSubscription(), DateTimeImmutable, upgradeUser()

### Community 16 - "Community 16"
Cohesion: 0.38
Nodes (5): Psr\Container\ContainerExceptionInterface, Psr\Container\NotFoundExceptionInterface, RuntimeException, ContainerException, NotFoundException

### Community 18 - "Community 18"
Cohesion: 0.50
Nodes (3): PDO, run(), Nikanzo\Core\Database\SeederInterface

## Knowledge Gaps
- **40 isolated node(s):** `name`, `description`, `homepage`, `license`, `type` (+35 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **7 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `LicenseManager` connect `Community 8` to `Community 2`, `Community 5`?**
  _High betweenness centrality (0.068) - this node is a cross-community bridge._
- **Why does `QueryBuilder` connect `Community 6` to `Community 0`, `Community 5`?**
  _High betweenness centrality (0.062) - this node is a cross-community bridge._
- **Why does `Container` connect `Community 3` to `Community 2`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **What connects `name`, `description`, `homepage` to the rest of the system?**
  _40 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.05471956224350205 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.0425531914893617 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.06382978723404255 - nodes in this community are weakly interconnected._