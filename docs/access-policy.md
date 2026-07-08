# MissionBayMemora Access Policy

MissionBayMemora has two access layers.

## Resource access

Entity visibility, entry updates, comments, metadata changes, relations, tags, and access grants are still enforced by the active ResourceFoundation service implementation.

MissionBayMemora does not bypass these checks.

## Tool-surface access

`MissionBayMemora\Service\MemoraAgentAccessPolicy` controls which tool categories may execute from a MissionBay resource configuration.

This policy decides whether a tool is available at the agent/MCP surface. It is not the final data authorization layer.

## Capabilities

Current capabilities:

```text
entry_write
activity_read
activity_write
access_read
access_write
role_admin
membership_admin
file_read
file_write
userdata_read
userdata_write
profile_read
profile_write
destructive
```

## Default behavior

The default policy is usable for internal chatbots:

* read access is allowed,
* entry write is allowed,
* activity write is allowed,
* access and role administration are allowed,
* destructive actions are denied,
* confirmation is required.

`allow_destructive` defaults to false. This means `memora_archive_role` remains disabled unless explicitly allowed.

For external MCP deployments, prefer a restrictive resource configuration such as:

```json
{
  "readonly": true,
  "allow_access_read": true,
  "allowed_capabilities": [
    "activity_read",
    "access_read"
  ]
}
```

For a trusted internal administration agent, a configuration may allow broader use:

```json
{
  "allow_write": true,
  "allow_access_write": true,
  "allow_role_admin": true,
  "allow_membership_admin": true,
  "allow_destructive": false,
  "require_confirmation": true
}
```

## Confirmation

`require_confirmation` defaults to true.

When confirmation is required, write tools return:

```json
{
  "status": "confirmation_required",
  "confirmation_required": true,
  "confirm_argument": "confirm"
}
```

The model should show the proposed plan to the user and wait for explicit confirmation before repeating the same tool call with `confirm=true`.

## Deny and allow lists

The policy supports explicit allow and deny lists.

```json
{
  "allowed_capabilities": ["activity_read", "access_read"],
  "denied_capabilities": ["destructive"]
}
```

If `allowed_capabilities` is non-empty, only those capabilities can run.

If `denied_capabilities` contains a capability, that capability is always denied.


## Package 07 capabilities

Package 07 adds file and user-data capabilities:

```text
file_read
file_write
userdata_read
userdata_write
```

Read-only configurations allow `file_read` and `userdata_read` together with the other read capabilities.

Package 08 adds profile capabilities:

```text
profile_read
profile_write
```

Read-only configurations allow `profile_read`. Profile mutations are controlled separately from entry writes because profiles are user-specific view/filter presets rather than shared entity data.

File deletion is destructive and also requires:

```text
destructive
```

In practical resource configuration this means:

```json
{
  "allow_file_write": true,
  "allow_destructive": true
}
```

User-data writes are controlled separately from entry writes because user data is personal or per-user state rather than the entity's shared domain payload.


## Package 08 capabilities

Package 08 adds `profile_read` and `profile_write`.

`profile_read` is allowed in read-only configurations. `profile_write` requires `allow_write=true`, `allow_profile_write=true`, and normally a confirmation roundtrip.
