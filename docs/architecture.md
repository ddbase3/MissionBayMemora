# MissionBayMemora Architecture

MissionBayMemora is a bridge plugin. It does not own Memora persistence and it does not call Memora implementation classes directly.

The dependency direction is:

```text
MissionBayMemora
  -> MissionBay contracts
  -> ResourceFoundation contracts
```

It intentionally avoids:

```text
MissionBayMemora -> Memora\Service\*
MissionBayMemora -> Memora\Api\*
MissionBayMemora -> Memora database tables
```

This keeps the plugin portable across local and remote XRM deployments.

## Main runtime flow

```text
MissionBay / MCP runtime
  -> discovers IAgentTool / IAgentResourceProvider / IAgentPromptProvider
  -> calls MissionBayMemora tool
  -> tool normalizes arguments
  -> tool calls ResourceFoundation service
  -> service enforces backend access and loads data
  -> tool formats serializable result
```

## Components

### MissionBayMemoraPlugin

Registers shared helper services:

```text
MemoraAgentInputNormalizer
MemoraAgentEntryFormatter
MemoraAgentResultBuilder
```

The tool classes are not manually registered. They are discoverable through the class map because they implement MissionBay agent interfaces.

### MemoraReadAgentTool

Provides read-only entity lookup and context functions.

Consumes:

```text
IEntityDataService
IEntityRelationService
IEntityMetadataService
IEntityTagService
IEntityActivityService
IEntityUserDataService
```

### MemoraStructureAgentTool

Provides read-only structure discovery.

Consumes:

```text
IEntityStructureService
IEntityTagService
```

### Helper services

`MemoraAgentInputNormalizer` converts arbitrary model-provided arguments into stable ids, lists, limits and include sets.

`MemoraAgentEntryFormatter` converts backend entries into compact agent-friendly arrays and truncates long strings.

`MemoraAgentResultBuilder` standardizes tool result, error result, resource result and prompt result shapes.

## Result shape

Successful tool result:

```json
{
  "ok": true,
  "tool": "memora_get_entry",
  "message": "Entry loaded.",
  "data": {},
  "items": [],
  "links": [],
  "warnings": []
}
```

Error result:

```json
{
  "ok": false,
  "tool": "memora_get_entry",
  "code": "entry_not_found",
  "message": "Entry not found or not visible.",
  "suggestions": [],
  "data": {}
}
```

Resource result:

```json
{
  "contents": [
    {
      "uri": "memora://structure",
      "mimeType": "application/json",
      "text": "{...}"
    }
  ]
}
```

## Package roadmap

Package 04:

```text
read-only entry tools
read-only structure tools
resources
prompts
```

Package 05:

```text
write tools
activity write tools
agent access policy
```

Package 06:

```text
access administration tools
role diagnostics
external MCP allowlist documentation
```
