# MissionBayMemora Write Tools

This document describes the write-tool package for MissionBayMemora.

The write tools are designed for MissionBay agents and MCP clients that support a review-and-confirm workflow. A model should never silently mutate Memora/XRM data. It should first produce a proposed change plan, show it to the user, and then wait for an explicit confirmation before executing the same tool call with `confirm=true`.

## Tool class

```text
MissionBayMemora\Resource\MemoraWriteAgentTool
```

The class implements:

```text
MissionBay\Api\IAgentTool
MissionBay\Api\IAgentPromptProvider
```

It does not implement `IAgentResourceProvider`, because write operations are actions, not read-only resources.

## Services used

The tool depends only on ResourceFoundation contracts and MissionBayMemora helpers:

```text
ResourceFoundation\Api\IEntityDataService
MissionBayMemora\Service\MemoraAgentInputNormalizer
MissionBayMemora\Service\MemoraAgentEntryFormatter
MissionBayMemora\Service\MemoraAgentResultBuilder
MissionBayMemora\Service\MemoraAgentAccessPolicy
```

It does not depend on Memora implementation classes.

## Confirmation workflow

Every mutating function has a `confirm` argument.

Default behavior:

```json
{
  "confirm": false
}
```

or omitted `confirm` returns:

```json
{
  "ok": true,
  "status": "confirmation_required",
  "confirmation_required": true,
  "confirm_argument": "confirm",
  "data": {
    "plan": {},
    "arguments": {}
  }
}
```

The assistant should show `data.plan` to the user. If the user approves, the assistant should call the same tool again with the reviewed payload and:

```json
{
  "confirm": true
}
```

This matches the intended MissionBay/MCP flow where tools can first offer a change for correction and then execute only after confirmation.

## `memora_create_entry`

Creates a new entry through:

```text
IEntityDataService::createEntry()
```

The first call returns a proposed `entry` payload. The confirmed call executes it.

Supported arguments:

```text
type          required Memora type alias
module        optional module alias
name          optional entry display name
data          typed entry data
metadata      entry-wide metadata
tags          tag list
relations     related entry ids, mapped to Memora allocs
useraccess    optional direct user access rows
groupaccess   optional group access rows
include       aspects to load after creation
confirm       false for plan, true for execution
```

The tool maps:

```text
relations -> allocs
```

so the existing Memora create pipeline can handle relation creation.

## `memora_update_entry`

Updates one entry through:

```text
IEntityDataService::updateEntry()
```

The first call returns the proposed patch. The confirmed call executes it.

Supported arguments:

```text
entry_id          required entry id
patch             native Memora update patch
set               base-entry field patch
setname           name update
setdata           typed data values to set
unsetdata         typed data keys to remove
setmetadata       metadata values to set
unsetmetadata     metadata keys to remove
addtags           tags to add
removetags        tags to remove
replacetags       complete tag replacement
addrelations      relation ids to add, mapped to addallocs
removerelations   relation ids to remove, mapped to removeallocs
replacerelations  complete relation replacement, mapped to replaceallocs
include           aspects to load after update
confirm           false for plan, true for execution
```

The tool intentionally forwards the final patch to the existing Memora update pipeline. Access checks, delete-lock checks, typed data handling, metadata handling, tag handling, relation handling, user access and group access stay inside the existing ResourceFoundation/Memora layer. Role access is no longer part of entry ACL; use the access/RBAC tools for roles and permissions.

## Policy

The write tool consults:

```text
MemoraAgentAccessPolicy::CAPABILITY_ENTRY_WRITE
```

before preparing or executing write actions.

Configuration keys:

```text
enabled
readonly
allow_write
allow_entry_write
allowed_capabilities
denied_capabilities
require_confirmation
```

`require_confirmation` defaults to `true`.
