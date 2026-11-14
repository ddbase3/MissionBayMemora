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
Base3\MissionBayMemora\MissionBayMemoraPlugin
Base3\MissionBayMemora\Resource\MemoraReadAgentTool
Base3\MissionBayMemora\Resource\MemoraStructureAgentTool
```

## Verify service bindings

The plugin check reports whether these services are bound:

```text
Base3\ResourceFoundation\Api\IEntityDataService
Base3\ResourceFoundation\Api\IEntityRelationService
Base3\ResourceFoundation\Api\IEntityMetadataService
Base3\ResourceFoundation\Api\IEntityTagService
Base3\ResourceFoundation\Api\IEntityStructureService
Base3\ResourceFoundation\Api\IEntityActivityService
Base3\ResourceFoundation\Api\IEntityUserDataService
```

In a manager deployment, these are typically proxies to an XRM microservice endpoint.
