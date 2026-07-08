# MissionBayMemora Activity Tools

This document describes the activity package for MissionBayMemora.

## Tool class

```text
MissionBayMemora\Resource\MemoraActivityAgentTool
```

The class implements:

```text
MissionBay\Api\IAgentTool
MissionBay\Api\IAgentPromptProvider
```

## Services used

```text
ResourceFoundation\Api\IEntityActivityService
MissionBayMemora\Service\MemoraAgentInputNormalizer
MissionBayMemora\Service\MemoraAgentResultBuilder
MissionBayMemora\Service\MemoraAgentAccessPolicy
```

## `memora_get_activity`

Reads logs and comments for one entry.

Arguments:

```text
entry_id  required entry id
include   logs, comments, or all
limit     maximum rows per part, default 25, hard maximum 100
offset    pagination offset for backends that support it
```

The tool calls:

```text
IEntityActivityService::getLogs()
IEntityActivityService::getComments()
```

It is read-only but still policy-controlled through:

```text
MemoraAgentAccessPolicy::CAPABILITY_ACTIVITY_READ
```

## `memora_add_comment`

Adds a comment to one entry.

Arguments:

```text
entry_id   required entry id
comment    required comment text
parent_id  optional parent comment id
confirm    false for plan, true for execution
```

Like the entry write tools, `memora_add_comment` is confirmation-aware. The first call returns a proposed comment plan. The confirmed call executes:

```text
IEntityActivityService::addComment()
```

The tool is controlled through:

```text
MemoraAgentAccessPolicy::CAPABILITY_ACTIVITY_WRITE
```

## Comment safety

The tool does not silently create comments. It returns `status=confirmation_required` until `confirm=true` is supplied.

A model should present the exact comment text to the user before confirmation.
