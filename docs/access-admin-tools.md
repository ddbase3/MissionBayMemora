# MissionBayMemora Access and Role Tools

Package 06 adds access and role administration tools to MissionBayMemora.

The implementation remains a ResourceFoundation bridge. It consumes:

```text
ResourceFoundation\Api\IEntityAccessService
```

It does not call Memora tables or Memora implementation classes directly.

## Tool class

```text
MissionBayMemora\Resource\MemoraAccessAgentTool
```

The class implements:

```text
MissionBay\Api\IAgentTool
MissionBay\Api\IAgentResourceProvider
MissionBay\Api\IAgentPromptProvider
```

This makes the tool usable by MissionBay flows, internal chatbots, and MCP transports that expose MissionBay tools, resources, and prompts.

## Read tools

### `memora_get_entry_access`

Reads direct access grants for one entry.

Arguments:

```json
{
  "entry_id": 123
}
```

Result data contains:

```json
{
  "entry_id": 123,
  "access": {
    "useraccess": [],
    "groupaccess": [],
    "roleaccess": []
  }
}
```

The exact inner rows are defined by the active ResourceFoundation implementation.

### `memora_get_roles`

Lists roles with optional filters.

Arguments:

```json
{
  "scope": "entry",
  "permission": "edit",
  "query": "editor",
  "include_archived": false,
  "limit": 25,
  "offset": 0
}
```

The tool loads roles through `IEntityAccessService::getRoles()` and filters in the tool layer. Filtering is intentionally simple and stable: scope, permission, and a text query over common role fields.

### `memora_get_role`

Reads one role by id.

Arguments:

```json
{
  "role_id": 10
}
```

### `memora_get_principal_roles`

Reads role assignments for a user or group.

Arguments:

```json
{
  "principal_type": "user",
  "principal_id": 5,
  "include_effective": true,
  "include_groups": true
}
```

For users, the tool can include:

* directly assigned roles
* effective roles inherited through groups
* direct group ids

For groups, the tool returns directly assigned group roles.

## Confirmation-aware write tools

All mutating access tools follow the standard MissionBayMemora confirmation workflow.

First call with missing `confirm` or `confirm=false`:

```json
{
  "status": "confirmation_required",
  "confirmation_required": true,
  "data": {
    "plan": {},
    "arguments": {
      "confirm": true
    }
  }
}
```

The chatbot should present the plan to the user and wait for explicit approval. Only after approval should it repeat the call with the reviewed arguments and `confirm=true`.

### `memora_set_entry_access`

Replaces one or more direct access categories for an entry.

Arguments:

```json
{
  "entry_id": 123,
  "users": [
    { "user_id": 5, "mode": "owner" }
  ],
  "groups": [
    { "group_id": 2, "mode": "moderator" }
  ],
  "roles": [
    { "role_id": 10 }
  ],
  "confirm": false
}
```

Only categories present in the arguments are replaced. For example, providing only `roles` changes only role access and leaves user and group access untouched.

Scalar shorthand is allowed:

```json
{
  "entry_id": 123,
  "roles": [10, 11]
}
```

The tool normalizes this to rows with `role_id`. For user and group scalar shorthand, the tool uses `visitor` as the default mode because Memora user/group access rows require an explicit mode.

### `memora_create_role`

Creates a role.

Required fields:

```json
{
  "name": "project_editor",
  "scope": "entry",
  "permission": "edit"
}
```

Optional fields:

```json
{
  "label": "Project Editor",
  "info": "Can edit entries shared through this role.",
  "archive": false
}
```

### `memora_update_role`

Partially updates a role.

Arguments:

```json
{
  "role_id": 10,
  "label": "Project Editor",
  "info": "Updated description.",
  "confirm": false
}
```

### `memora_archive_role`

Archives one role.

This tool requires both role administration and destructive capability. It should be exposed only to trusted internal clients.

Arguments:

```json
{
  "role_id": 10,
  "confirm": false
}
```

### `memora_replace_principal_roles`

Replaces all directly assigned roles for a user or group.

Arguments:

```json
{
  "principal_type": "user",
  "principal_id": 5,
  "role_ids": [10, 11],
  "confirm": false
}
```

### `memora_replace_user_groups`

Replaces all group memberships for one user.

Arguments:

```json
{
  "user_id": 5,
  "group_ids": [2, 3],
  "confirm": false
}
```

## MCP resources

The access tool exposes read-only resources:

```text
memora://roles
memora://role/{role_id}
memora://entry/{entry_id}/access
```

Resources never mutate state.

## Prompts

The access tool exposes prompts for safe model workflows:

```text
memora_explain_access
memora_manage_entry_access
memora_manage_roles
memora_assign_roles
```

These prompts instruct the model to inspect current state first, prepare a proposed change, present it to the user, and only execute after explicit confirmation.

## Capability policy

Package 06 extends `MemoraAgentAccessPolicy` with these capabilities:

```text
access_read
access_write
role_admin
membership_admin
```

Existing capabilities remain available:

```text
entry_write
activity_read
activity_write
destructive
```

Important configuration flags:

```text
readonly
allow_access_read
allow_access_write
allow_role_admin
allow_membership_admin
allow_destructive
require_confirmation
allowed_capabilities
denied_capabilities
```

`require_confirmation` defaults to true.

`allow_destructive` defaults to false. This keeps `memora_archive_role` disabled unless the tool configuration explicitly allows destructive operations.
