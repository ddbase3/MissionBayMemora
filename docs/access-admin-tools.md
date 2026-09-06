# MissionBayMemora Access, Role and Permission Tools

MissionBayMemora exposes entry ACL administration and RBAC administration through `ResourceFoundation\Api\IEntityAccessService`.

The implementation remains a ResourceFoundation bridge. It does not call Memora tables or Memora implementation classes directly.

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

## Access model

Entry access and RBAC are deliberately separate:

```text
Entry ACL:
  user/group -> entry access

RBAC:
  user/group -> role -> permission
```

Entry role access is no longer part of this plugin. Use `useraccess` and `groupaccess` for concrete entry ACL, and use roles/permissions for general capabilities.

## Read tools

### `memora_get_entry_access`

Reads direct user and group access grants for one entry.

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
    "groupaccess": []
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
  "permission": "admin",
  "query": "admin",
  "include_archived": false,
  "limit": 25,
  "offset": 0
}
```

`scope` and `permission` filter against the permissions assigned to each role, not against fields on the role row. Role rows may include a `permissions` array returned by the ResourceFoundation implementation.

### `memora_get_role`

Reads one role by id.

Arguments:

```json
{
  "role_id": 10
}
```

### `memora_get_permissions`

Lists permissions with optional filters.

Arguments:

```json
{
  "scope": "entry",
  "permission": "admin",
  "query": "entry",
  "include_archived": false,
  "limit": 25,
  "offset": 0
}
```

### `memora_get_permission`

Reads one permission by id.

Arguments:

```json
{
  "permission_id": 7
}
```

### `memora_get_role_permissions`

Reads permissions assigned to one role.

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
  "confirm": false
}
```

Only categories present in the arguments are replaced. For example, providing only `users` changes only user access and leaves group access untouched.

Scalar shorthand is allowed:

```json
{
  "entry_id": 123,
  "groups": [2, 3]
}
```

The tool normalizes user and group scalar shorthand to rows with `visitor` as the default mode because Memora user/group access rows require an explicit mode.

### `memora_create_role`

Creates a role.

Required fields:

```json
{
  "name": "project_editor"
}
```

Optional fields:

```json
{
  "label": "Project Editor",
  "info": "Can edit entries when paired with suitable permissions.",
  "permission_ids": [7, 8],
  "archive": false
}
```

### `memora_update_role`

Partially updates a role. Permission ids can be replaced as part of the update.

Arguments:

```json
{
  "role_id": 10,
  "label": "Project Editor",
  "permission_ids": [7, 8],
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

### `memora_create_permission`

Creates a permission.

Required fields:

```json
{
  "scope": "entry",
  "permission": "admin"
}
```

Optional fields:

```json
{
  "label": "Entry administration",
  "info": "May bypass normal entry ACL checks.",
  "archive": false
}
```

### `memora_update_permission`

Partially updates a permission.

Arguments:

```json
{
  "permission_id": 7,
  "label": "Entry administration",
  "confirm": false
}
```

### `memora_archive_permission`

Archives one permission. This requires destructive capability.

Arguments:

```json
{
  "permission_id": 7,
  "confirm": false
}
```

### `memora_replace_role_permissions`

Replaces all permissions assigned to one role.

Arguments:

```json
{
  "role_id": 10,
  "permission_ids": [7, 8],
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
