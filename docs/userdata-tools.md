# MissionBayMemora User Data Tools

Package 07 adds user-data tools for MissionBayMemora.

The tools use `ResourceFoundation\Api\IEntityUserDataService` and treat user data as per-user entry state, separate from entry-wide metadata.

## Functions

```text
memora_get_user_data
memora_get_user_data_value
memora_set_user_data
memora_remove_user_data
```

## Read functions

### memora_get_user_data

Loads all user-data values for one entry/user pair.

```json
{
  "entry_id": 123,
  "user_id": null
}
```

When `user_id` is omitted, the backend may resolve the current user.

### memora_get_user_data_value

Loads one named user-data value.

```json
{
  "entry_id": 123,
  "name": "favorite",
  "default": false
}
```

## Write functions

User-data write functions are confirmation-aware.

### memora_set_user_data

Sets one or more user-data values while preserving other keys.

```json
{
  "entry_id": 123,
  "data": {
    "favorite": true,
    "view_mode": "compact"
  }
}
```

### memora_remove_user_data

Removes one or more user-data keys.

```json
{
  "entry_id": 123,
  "names": ["favorite", "view_mode"]
}
```

## MCP resources

```text
memora://entry/{entry_id}/userdata
memora://entry/{entry_id}/userdata/{name}
```

The resources are read-only and use the current backend user when no explicit user id can be supplied through the URI.
