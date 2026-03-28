Arcanum is under heavy development. v0.0.01

# Arcanum: A Cutting-Edge PHP Framework

Arcanum is a transformative PHP Framework with a fresh take on web application development, crafted meticulously with the modern software engineer in mind.

## Packages

Arcanum is a collection of packages that work together to create a robust, scalable, and maintainable web application framework. Each package is designed to be used independently, and combined they form the core of Arcanum applications.

### Arcanum Ignition

[Ignition](https://github.com/arcanum-org/framework/tree/main/src/Ignition) is the bootstrap package for Arcanum applications. Every Arcanum app uses an Ignition Kernel to get things started, keep them running, and terminate gracefully.

### Arcanum Cabinet

[Cabinet](https://github.com/arcanum-org/framework/tree/main/src/Cabinet) is a flexible, PSR-11 compliant dependency injection container, along with an Application interface which you can use to register your application's services, factories, providers, and other dependencies.

### Arcanum Codex

[Codex](https://github.com/arcanum-org/framework/tree/main/src/Codex) is an automatic class resolver. Give it a class name, and it uses PHP's Reflection API to inspect the constructor, resolve all dependencies recursively, and hand you a fully built instance. It supports manual overrides via specifications — you can tell it "when building X, use Y for this parameter" — and integrates with Echo's event system to fire events before and after resolution. It's used by Cabinet under the hood, but works independently too.

### Arcanum Echo

[Echo](https://github.com/arcanum-org/framework/tree/main/src/Echo) is a PSR-14 compliant event dispatcher. Register listeners for event classes, dispatch events, and Echo calls the right listeners in order. It walks the class hierarchy, so a listener for a base event class fires for all subclasses too. You can dispatch any object — non-Event objects get wrapped automatically. Under the hood, it uses Flow's Pipeline to chain listeners with propagation control.

### Arcanum Flow

[Flow](https://github.com/arcanum-org/framework/tree/main/src/Flow) is all about moving data through your application from point A to point B. Everything in Flow builds on the `Stage` interface — a callable that takes an object in and sends an object out. It's composed of four subpackages:

1. [Pipeline](https://github.com/arcanum-org/framework/tree/main/src/Flow/Pipeline) chains stages in a straight line — the output of one becomes the input of the next. If you have a series of steps, Pipeline wraps them up in a nice, neat system.
2. [Continuum](https://github.com/arcanum-org/framework/tree/main/src/Flow/Continuum) is middleware. Each stage gets a `$next` callback it must call to continue the chain, just like middleware in Laravel or Express. This lets stages run logic both before and after the inner stages.
3. [Conveyor](https://github.com/arcanum-org/framework/tree/main/src/Flow/Conveyor) is Arcanum's Command Bus. It combines Pipeline and Continuum to dispatch objects to handlers with before/after middleware. Handlers are resolved by convention — `PlaceOrder` dispatches to `PlaceOrderHandler`.
4. [River](https://github.com/arcanum-org/framework/tree/main/src/Flow/River) is a PSR-7 Stream implementation. It wraps PHP's low-level stream resources into type-safe objects that auto-close, support caching for non-seekable streams, and generally make working with streams a breeze.

### Arcanum Gather

[Gather](https://github.com/arcanum-org/framework/tree/main/src/Gather) is a typed key-value collection system. The core `Registry` class wraps arrays with PSR-11 container compliance, type coercion (`asString`, `asInt`, `asBool`, etc.), and serialization support. Three specialized variants extend it: `Configuration` adds dot-notation access for nested settings, `Environment` locks down serialization and cloning to prevent leaking secrets, and `IgnoreCaseRegistry` provides case-insensitive key lookups (used by Hyper for HTTP headers).

### Arcanum Glitch

[Glitch](https://github.com/arcanum-org/framework/tree/main/src/Glitch) is Arcanum's error handling package. It converts PHP errors to exceptions, manages shutdown handling, and dispatches to reporters for logging and alerting. `HttpException` lets you throw exceptions that carry a precise HTTP status code — a 409 Conflict, a 422 Unprocessable Entity, a 503 Service Unavailable — and the framework renders the correct response automatically.

### Arcanum Hyper

[Hyper](https://github.com/arcanum-org/framework/tree/main/src/Hyper) is Arcanum's HTTP layer — PSR-7 messages, PSR-15 server handling, and a type-safe `StatusCode` enum covering the full HTTP status code spectrum. Where most frameworks treat status codes as an afterthought (200 for success, 404 for missing, 500 for everything else), Hyper gives every code in the spec a first-class representation and encourages precise, semantic use throughout the framework.

### Arcanum Parchment

[Parchment](https://github.com/arcanum-org/framework/tree/main/src/Parchment) is a library of utilities designed to make working with files a breeze.

### Arcanum Quill

[Quill](https://github.com/arcanum-org/framework/tree/main/src/Quill) is a package for logging messages to different logging channels. It uses the excellent [Monolog](https://github.com/Seldaek/monolog) package under the hood.

### Arcanum Atlas

[Atlas](https://github.com/arcanum-org/framework/tree/main/src/Atlas) is Arcanum's convention-based CQRS router. It maps HTTP requests to Query and Command handlers by converting URL path segments to PascalCase namespaces — no route files, no annotations, no configuration for the common case. The HTTP method determines intent: GET reads (Queries), PUT/POST/PATCH/DELETE writes (Commands). Atlas enforces this at the routing level — a GET to a Command-only path is a **405 Method Not Allowed**, not a silent misroute. Request a path that doesn't exist? **404 Not Found**. Every error gets the right status code.

### Arcanum Shodo

[Shodo](https://github.com/arcanum-org/framework/tree/main/src/Shodo) (書道, "the way of writing") is the output rendering package. It converts handler results into HTTP responses through a format-aware registry — the same handler can produce JSON, HTML, or CSV based on the file extension in the URL. Request an unsupported format? **406 Not Acceptable**. Shodo also handles exception rendering, mapping `HttpException` status codes to properly structured error responses.

### Arcanum Toolkit

[Toolkit](https://github.com/arcanum-org/framework/tree/main/src/Toolkit) is a collection of utilities, like string manipulation, etc.

## Arcanum is Different

Unlike most PHP Frameworks, Arcanum applications don't follow the classic Model-View-Controller (MVC) paradigm.

MVC is a cornerstone of web development. It's simple. It encapsulates and separates the relationship between the data (Model) and the user interface (View), earning a reputation as the de facto architecture for small to mid-sized applications.

### MVC Fails Complex Apps

A tenet of "doing MVC right" is following the principle of "Skinny Controllers, Fat Models." This advice saves us from bloated controllers, but it inadvertently nudges us toward "God Models"—monolithic entities handling an array of responsibilities that span thousands of lines of code. As the complexity of an MVC application grows, so do these models, becoming increasingly difficult to maintain, test, and extend.

MVC offers little guidance on managing these "Fat Models," leading to sprawling codebases where one model serves too many masters, a tightly coupled system that hampers an application's ability to scale and evolve.

Highly complex apps demand a different approach.

### Command Query Responsibility Segregation

Arcanum applications follow the Command Query Responsibility Segregation (CQRS) pattern for a distinctive, robust, and scalable solution to building complex web applications. Operations that mutate state (Commands) are separated from those that read state (Queries), leading to a leaner, highly maintainable architecture. We're not trying to reinvent the wheel, but we're offering a different, perhaps more suitable tool for specific applications.

### Domain-Driven CQRS is a Game-Changer

Successful apps get bigger. Arcanum applications tackle this truth by utilizing clear bounded contexts. A bounded context represents a specific area of responsibility with its own ubiquitous language and model design. This separation simplifies the codebase, providing more evident areas of responsibility and making debugging and adding features a breeze.

## The Arcanum Philosophy

### CQRS and Domain-Driven Design

In Arcanum, we embrace CQRS and domain-driven design to tackle the complexity of modern web applications head-on. We're not trying to replace all the lovely MVC frameworks out there. We respect and appreciate their immense contributions. Instead, we're offering a novel perspective, a unique tool in your toolbox that can handle the intricacies of highly complex web applications differently.

### HTTP Status Codes Mean Something

Most frameworks collapse the richness of HTTP into a handful of status codes: 200 for success, 404 for missing, 500 for errors, and 403 if you're feeling fancy. The HTTP specification defines dozens of status codes, each with precise semantics — and Arcanum uses them.

A Command that creates a resource returns **201 Created**. A Command that completes with nothing to report returns **204 No Content**. A Command that's accepted for deferred processing returns **202 Accepted**. Request a path with the wrong HTTP method? That's **405 Method Not Allowed**, not a 404 — because the resource exists, you just asked for it wrong. Ask for an unsupported response format? **406 Not Acceptable**.

This isn't pedantry. Status codes are part of your API's contract. They tell clients, proxies, caches, and monitoring systems exactly what happened — without parsing a response body or guessing from context. When a load balancer sees a spike in 503s it knows the service is overloaded. When a client receives a 201 it knows something was created. When a cache sees a 304 it knows it can reuse what it has.

Arcanum's `StatusCode` enum provides a type-safe representation of every standard HTTP status code, and the framework enforces their correct use. `HttpException` carries the precise status code with it. Renderers choose the right code based on what actually happened. The router distinguishes "nothing here" from "something here, wrong method." This precision flows from the framework into your application code, so your APIs communicate clearly by default.

With that spirit, welcome to Arcanum.
