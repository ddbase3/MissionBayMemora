# MissionBayMemora Installation

Copy the plugin directory to the BASE3 plugin folder:

```text
plugin/MissionBayMemora
```

Required runtime plugins or equivalent providers:

```text
MissionBay
ResourceFoundation
Memora or ResourceFoundation proxy bindings
```

No database migration is required.

## Verify class discovery

The default `PluginClassMap` scans:

```text
plugin/MissionBayMemora/src
```

The following classes should become discoverable:

```text
MissionBayMemora\MissionBayMemoraPlugin
MissionBayMemora\Resource\MemoraReadAgentTool
MissionBayMemora\Resource\MemoraStructureAgentTool
```

## Verify service bindings

The plugin check reports whether these services are bound:

```text
ResourceFoundation\Api\IEntityDataService
ResourceFoundation\Api\IEntityRelationService
ResourceFoundation\Api\IEntityMetadataService
ResourceFoundation\Api\IEntityTagService
ResourceFoundation\Api\IEntityStructureService
ResourceFoundation\Api\IEntityActivityService
ResourceFoundation\Api\IEntityUserDataService
```

In a manager deployment, these are typically proxies to an XRM microservice endpoint.
