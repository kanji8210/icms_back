# ICMS Back Architecture Scaffold

This scaffold follows strict layered architecture and OOP separation of concerns.

## Layers

- Domain: entities, value objects, repository interfaces, domain services
- Application: use cases, DTOs, orchestration logic
- Infrastructure: WordPress adapters, persistence, auth, external integrations
- Presentation: REST/GraphQL controllers and middleware

## Folder Structure

```
config/
  graphql/

src/
  Domain/
    Entities/
    Exceptions/
    Repositories/
    Services/
    Traits/
    ValueObjects/

  Application/
    DTOs/
    Services/
    UseCases/

  Infrastructure/
    Auth/
    External/
    Logging/
    Persistence/
      Mappers/
      Migrations/
      Repositories/
    Providers/
    Security/

  Presentation/
    Controllers/
    GraphQL/
      Mutations/
      Resolvers/
    Middleware/
    REST/

api/
graphql/
database/
  migrations/
  seeds/
includes/
tests/
  Application/
  Domain/
  Integration/
  Unit/
docs/
languages/
public/
```

## OOP Rules for This Codebase

- Depend on interfaces in Domain, not concrete classes.
- Keep UseCases thin and orchestration-focused.
- Keep WordPress-specific logic in Infrastructure/Presentation only.
- Share reusable behavior through abstract classes and traits sparingly.
- Use constructor injection for all dependencies.
