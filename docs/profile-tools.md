# MissionBayMemora Profile Tools

Package 08 adds profile tools based on `ResourceFoundation\Api\IEntityProfileService`.

Profiles are user-specific Memora/XRM view or filter presets. The exact profile payload is backend-defined, but common fields are:

```text
id
user_id
name
profile
standard
protected
active
archive
```

The tool never accesses Memora tables directly. It delegates all work to the active `IEntityProfileService` implementation.

## Tools

### `memora_get_active_profile`

Reads the active profile for the current or specified user.

Parameters:

```json
{
  "user_id": 123
}
```

`user_id` is optional. When it is omitted, the backend may resolve the current user from the active runtime context.

### `memora_get_profiles`

Lists profiles for the current or specified user.

Parameters:

```json
{
  "user_id": 123,
  "include_archived": false,
  "limit": 25,
  "offset": 0
}
```

The tool slices the returned profile list for stable MCP output paging. Backends may still load the full profile list internally.

### `memora_create_profile`

Creates a new profile for a user.

This is a write tool and is confirmation-aware.

First call:

```json
{
  "user_id": 123,
  "profile": {
    "name": "My Tasks",
    "profile": {
      "filters": {
        "type": "task"
      }
    },
    "active": true
  },
  "confirm": false
}
```

The tool returns `status=confirmation_required` with a review plan. After explicit user approval, call the same tool again with `confirm=true`.

If `profile.profile` is provided as an object, the tool serializes it as JSON before passing it to the service. If it is already a string, it is passed as-is.

### `memora_update_profile`

Updates one profile row.

Supported patch keys:

```text
name
profile
standard
protected
active
archive
```

This is confirmation-aware.

### `memora_archive_profile`

Archives one profile.

This is a soft archive operation using `IEntityProfileService::archiveProfile()`. It requires confirmation but does not require the destructive capability because the ResourceFoundation contract models it as an archive operation, not a physical deletion.

### `memora_set_active_profile`

Marks one profile as active for a user and lets the backend deactivate the user's other profiles.

This is confirmation-aware because it changes the user's active view/filter state.

## Resources

Package 08 adds these read-only resources:

```text
memora://profile/active
memora://profile/active/{user_id}
memora://profiles
memora://user/{user_id}/profiles
```

Resources never mutate profile state.

## Prompts

Package 08 adds these prompts:

```text
memora_read_profiles
memora_create_profile
memora_update_profile
memora_set_active_profile
```

The write prompts instruct models to first produce a review plan and wait for explicit user approval before setting `confirm=true`.

## Capabilities

Profile tools use two tool-surface capabilities:

```text
profile_read
profile_write
```

Read-only resource configurations allow `profile_read`. Profile mutations require `profile_write` and normally also confirmation.
