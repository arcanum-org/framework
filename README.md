Arcanum is under heavy development. v0.0.01

# Arcanum: A Cutting-Edge PHP Framework
Arcanum is a transformative PHP Framework with a fresh take on web application development, crafted meticulously with the modern software engineer in mind.

## Packages
Arcanum is a collection of packages that work together to create a robust, scalable, and maintainable web application framework. Each package is designed to be used independently, but they work best together.

### Arcanum Cabinet
[Cabinet](https://github.com/arcanum-org/framework/tree/main/src/Cabinet) is a flexible PSR-11 compliant dependency injection application container. It's designed to be easy to use while providing a lot of flexibility. It's one of the pillars of the Arcanum Framework.

### Arcanum Codex
[Codex](https://github.com/arcanum-org/framework/tree/main/src/Codex) is a practically magical Class Resolver. It's primarily used by Arcanum Cabinet to automatically resolve classes and their dependencies, but it can be used independently to build pretty much anything you can throw at it.

### Arcanum Echo
[Echo](https://github.com/arcanum-org/framework/tree/main/src/Echo) is a PSR-14 event dispatcher. All of the Arcanum packages use it to dispatch events, but it can be used independently to build any event-driven system.

### Arcanum Flow
[Flow](https://github.com/arcanum-org/framework/tree/main/src/Flow) is all about moving data from point A to point B. It's composed of four related packages:

1. [Continuum](https://github.com/arcanum-org/framework/tree/main/src/Flow/Continuum) is a package for creating and managing middleware pipelines.
2. [Conveyor](https://github.com/arcanum-org/framework/tree/main/src/Flow/Conveyor) is Arcanum's Command Bus. It's all about dispatching Data Transfer Objects (DTOs) to their handlers, and managing the before and after middleware for those DTOs.
3. [Pipeline](https://github.com/arcanum-org/framework/tree/main/src/Flow/Pipeline) is a package for creating and managing pipelines of operations. If you have a series of steps, Pipeline can wrap them up in a nice, neat system.
4. [River](https://github.com/arcanum-org/framework/tree/main/src/Flow/River) is a PSR-7 Stream implementation. It's a simple but thorough package that makes working with streams a breeze.

### Arcanum Gather
[Gather](https://github.com/arcanum-org/framework/tree/main/src/Gather) is a package for collecting and managing configuration data, environment variables, and other collections of key/value pairs.

### Arcanum Hyper
[Hyper](https://github.com/arcanum-org/framework/tree/main/src/Hyper) is a package for working with PSR-7 HTTP requests and responses in a nice, object-oriented way.

### Arcanum Ignition
[Ignition](https://github.com/arcanum-org/framework/tree/main/src/Ignition) is the bootstrap package for Arcanum applications. Every Arcanum app uses an Ignition Kernel to get things started, keep them running, and terminate gracefully.

### Arcanum Parchment
[Parchment](https://github.com/arcanum-org/framework/tree/main/src/Parchment) is a library of utilities designed to make working with files a breeze.

### Arcanum Quill
[Quill](https://github.com/arcanum-org/framework/tree/main/src/Quill) is a package for logging messages to different logging channels. It uses the excellent [Monolog](https://github.com/Seldaek/monolog) package under the hood.

### Arcanum Toolkit
[Toolkit](https://github.com/arcanum-org/framework/tree/main/src/Quill) is a collection of utilities, like string manipulation, etc.

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
In Arcanum, we embrace CQRS and domain-driven design to tackle the complexity of modern web applications head-on. We're not trying to replace all the lovely MVC frameworks out there. We respect and appreciate their immense contributions. Instead, we're offering a novel perspective, a unique tool in your toolbox that can handle the intricacies of highly complex web applications differently.

With that spirit, welcome to Arcanum.
