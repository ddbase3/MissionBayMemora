# Memora as semantic source of truth

MissionBayMemora communicates XRM meaning from Memora itself.

The canonical semantic records are:

- `systype` / `IEntityStructureService::getTypes()` for technical shapes
- `sysmodule` / `IEntityStructureService::getModules()` for domain modules
- `sysscope` / `IEntityStructureService::getScopes()` for domain scopes
- `sysscopemodule` / `IEntityStructureService::getScopeModules()` and `getModuleScopes()` for scope-module membership
- `systagdesc`, `systagscope` and `sysmoduletag` / `IEntityTagService` for tag semantics
- entry tags and alloc relations for concrete record meaning

The plugin must not duplicate this semantic model in PHP constants or BASE3 SettingsStore datasets.

## Domain profiles

A domain profile is an agent-facing read model derived from one Memora module. It contains:

- module name
- module description
- technical type
- assigned scopes
- assigned module tags
- guidance for using alloc relations

For example, a `dancephotographyshooting` profile comes from the Memora module row, its `project` type, its `dancephotography` scope assignment, and its module tags. A chatbot should not need a separate profile record to understand that meaning.

## Relations / allocs

Relations are data. They span the knowledge graph and should be inspected through graph tools. A photoshooting may be connected to dancer/contact, location/address, event/date, gallery/link or photo/file entries. Those connections are concrete Memora alloc relations, not fixed plugin presets.

## SettingsStore boundary

The SettingsStore remains useful for tool configuration, endpoint settings, credentials or UI options. It is not the source for XRM entity semantics when Memora already stores the structures.
