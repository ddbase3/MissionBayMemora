# MissionBayMemora

## Patch 11 correction: Memora is the semantic source of truth

MissionBayMemora no longer stores domain semantics in BASE3 settings. The tools now derive semantic profiles directly from Memora/XRM structure data: types, modules, scopes, scope-module assignments, tags and module-tag assignments. Settings may still be useful elsewhere for tool configuration, but they are not the canonical source for XRM entity meaning.


MissionBayMemora connects the MissionBay agent runtime with the BASE3 ResourceFoundation service layer used by Memora/XRM installations.

The plugin exposes Memora/XRM entities as MissionBay agent tools, MCP-readable resources, and reusable prompt templates. It is intentionally implemented against ResourceFoundation interfaces instead of Memora implementation classes, so it can run in several deployment modes:

* directly inside an XRM runtime where Memora provides the services,
* inside a manager runtime where ResourceFoundation proxies talk to XRM microservices,
* inside a MissionBay chatbot runtime,
* behind an MCP endpoint for external tool clients.

## Scope

MissionBayMemora is an agent/MCP bridge. It does not implement Memora storage logic itself.

The plugin consumes ResourceFoundation contracts such as:

```text
ResourceFoundation\Api\IEntityDataService
ResourceFoundation\Api\IEntityFileService
ResourceFoundation\Api\IEntityRelationService
ResourceFoundation\Api\IEntityMetadataService
ResourceFoundation\Api\IEntityTagService
ResourceFoundation\Api\IEntityStructureService
ResourceFoundation\Api\IEntityActivityService
ResourceFoundation\Api\IEntityUserDataService
ResourceFoundation\Api\IEntityAccessService
ResourceFoundation\Api\IEntityProfileService
```

This keeps the plugin portable across direct Memora runtimes, manager runtimes, and proxy-based deployments.

## Package history

### Package 04: read-only foundation

Package 04 introduced:

```text
MissionBayMemora/src/MissionBayMemoraPlugin.php
MissionBayMemora/src/Resource/MemoraReadAgentTool.php
MissionBayMemora/src/Resource/MemoraStructureAgentTool.php
MissionBayMemora/src/Service/MemoraAgentInputNormalizer.php
MissionBayMemora/src/Service/MemoraAgentEntryFormatter.php
MissionBayMemora/src/Service/MemoraAgentResultBuilder.php
MissionBayMemora/docs/architecture.md
MissionBayMemora/docs/tools.md
MissionBayMemora/docs/resources.md
MissionBayMemora/docs/prompts.md
MissionBayMemora/docs/access-policy.md
MissionBayMemora/docs/installation.md
MissionBayMemora/VERSION
```

It provided search, read, relation, context, and structure discovery tools.

### Package 05: write and activity tools

Package 05 added:

```text
MissionBayMemora/src/Resource/MemoraWriteAgentTool.php
MissionBayMemora/src/Resource/MemoraActivityAgentTool.php
MissionBayMemora/src/Service/MemoraAgentAccessPolicy.php
MissionBayMemora/src/Service/MemoraAgentResultBuilder.php
MissionBayMemora/src/MissionBayMemoraPlugin.php
MissionBayMemora/docs/write-tools.md
MissionBayMemora/docs/activity-tools.md
MissionBayMemora/docs/confirmation-workflow.md
MissionBayMemora/docs/access-policy.md
```

The package kept write operations confirmation-aware. A chatbot should first present the proposed change to the user and then wait for explicit confirmation before executing the same tool call with `confirm=true`.

### Package 06: access and role tools

Package 06 adds access and role administration:

```text
MissionBayMemora/src/Resource/MemoraAccessAgentTool.php
MissionBayMemora/src/Service/MemoraAgentAccessPolicy.php
MissionBayMemora/src/MissionBayMemoraPlugin.php
MissionBayMemora/docs/access-admin-tools.md
MissionBayMemora/docs/access-policy.md
```

It adds role lookup, entry access lookup, principal role inspection, and confirmation-aware access administration.

### Package 07: file and user-data tools

Package 07 adds file and user-data access:

```text
MissionBayMemora/src/Resource/MemoraFileAgentTool.php
MissionBayMemora/src/Resource/MemoraUserDataAgentTool.php
MissionBayMemora/src/Service/MemoraAgentAccessPolicy.php
MissionBayMemora/src/MissionBayMemoraPlugin.php
MissionBayMemora/docs/file-tools.md
MissionBayMemora/docs/userdata-tools.md
MissionBayMemora/docs/open-work.md
MissionBayMemora/docs/access-policy.md
```

It adds read-only file/user-data tools and confirmation-aware mutation tools. File deletion is treated as destructive and requires destructive capability configuration.


### Package 08: profile tools and capability overview

Package 08 adds profile tooling:

```text
MissionBayMemora/src/Resource/MemoraProfileAgentTool.php
MissionBayMemora/src/Service/MemoraAgentAccessPolicy.php
MissionBayMemora/src/MissionBayMemoraPlugin.php
MissionBayMemora/docs/profile-tools.md
MissionBayMemora/docs/capability-overview.md
MissionBayMemora/docs/open-work.md
MissionBayMemora/docs/access-policy.md
```

It adds read-only profile lookup plus confirmation-aware profile creation, update, archive, and active-profile switching.

## Exposed tools

### MemoraReadAgentTool

Read-only entity tool.

Functions:

```text
memora_search_entries
memora_get_entry
memora_get_related_entries
memora_get_entry_context
```

Use this tool when an agent needs to find visible entries, load one entry, inspect relations, or collect an entry-centered context package.

### MemoraStructureAgentTool

Read-only structure discovery tool.

Functions:

```text
memora_get_structure
memora_describe_type
```

Use this tool before create/update workflows or when a model needs to know which types, modules, scopes, or tags are available.

### MemoraWriteAgentTool

Confirmation-aware entry write tool.

Functions:

```text
memora_create_entry
memora_update_entry
```

Both tools return a review plan until the caller supplies:

```json
{
  "confirm": true
}
```

### MemoraActivityAgentTool

Activity and comment tool.

Functions:

```text
memora_get_activity
memora_add_comment
```

`memora_get_activity` is read-only. `memora_add_comment` follows the same confirmation workflow as entry write tools.

### MemoraAccessAgentTool

Access, role, and membership tool.

Read functions:

```text
memora_get_entry_access
memora_get_roles
memora_get_role
memora_get_permissions
memora_get_permission
memora_get_role_permissions
memora_get_principal_roles
```

Confirmation-aware administration functions:

```text
memora_set_entry_access
memora_create_role
memora_update_role
memora_archive_role
memora_create_permission
memora_update_permission
memora_archive_permission
memora_replace_role_permissions
memora_replace_principal_roles
memora_replace_user_groups
```

Entry access is limited to direct user/group ACL. Roles and permissions are administered separately as RBAC. `memora_archive_role` and `memora_archive_permission` are treated as destructive and require `allow_destructive=true` in the tool/resource configuration.

### MemoraProfileAgentTool

Profile and view/filter profile tool.

Read functions:

```text
memora_get_active_profile
memora_get_profiles
```

Confirmation-aware profile mutation functions:

```text
memora_create_profile
memora_update_profile
memora_archive_profile
memora_set_active_profile
```

`memora_archive_profile` is a soft archive operation. It requires confirmation but not the destructive capability.

### MemoraFileAgentTool

File entry and physical content tool.

Read functions:

```text
memora_get_file
memora_get_file_content
```

Confirmation-aware file mutation functions:

```text
memora_create_file
memora_replace_file
memora_delete_file
```

`memora_delete_file` is destructive and requires `allow_destructive=true` in addition to file write permission.

### MemoraUserDataAgentTool

User-specific entry data tool.

Read functions:

```text
memora_get_user_data
memora_get_user_data_value
```

Confirmation-aware user-data mutation functions:

```text
memora_set_user_data
memora_remove_user_data
memora_read_profiles
memora_create_profile
memora_update_profile
memora_set_active_profile
```

## Confirmation workflow

Write calls are two-step by default.

First call:

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
  "data": {
    "plan": {},
    "arguments": {
      "confirm": true
    }
  }
}
```

The chatbot should show the plan to the user. The user may correct it. Only after explicit approval should the chatbot call the tool again with the same reviewed payload and `confirm=true`.

This workflow is designed for MissionBay/MCP environments that support offering a tool action for review before execution.

## MCP resources

The read-only packages expose resources such as:

```text
memora://entry/{id}
memora://entry/{id}/summary
memora://entry/{id}/metadata
memora://entry/{id}/relations
memora://entry/{id}/activity
memora://structure
memora://types
memora://type/{type}
memora://modules
memora://scopes
memora://tags
memora://roles
memora://role/{role_id}
memora://entry/{entry_id}/access
memora://file/{file_id}
memora://file/{file_id}/content
memora://entry/{entry_id}/userdata
memora://entry/{entry_id}/userdata/{name}
memora://profile/active
memora://profile/active/{user_id}
memora://profiles
memora://user/{user_id}/profiles
```

All resources return JSON content. Resources are read-only and never mutate state.

## Prompts

Prompt providers guide models through safe tool sequences.

Read and structure prompts:

```text
memora_find_entry
memora_read_entry
memora_analyze_entry_context
memora_discover_structure
memora_describe_type
```

Write and activity prompts:

```text
memora_create_entry
memora_update_entry
memora_read_activity
memora_add_comment
```

Access and role prompts:

```text
memora_explain_access
memora_manage_entry_access
memora_manage_roles
memora_assign_roles
memora_read_file
memora_create_file
memora_replace_file
memora_delete_file
memora_read_user_data
memora_set_user_data
memora_remove_user_data
memora_read_profiles
memora_create_profile
memora_update_profile
memora_set_active_profile
```

## Safety model

MissionBayMemora does not bypass Memora or ResourceFoundation access checks. Entity visibility and entry-level access remain enforced by the active service implementation.

The plugin additionally provides tool-surface policy through:

```text
MissionBayMemora\Service\MemoraAgentAccessPolicy
```

Capabilities:

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

`require_confirmation` defaults to true.

`allow_destructive` defaults to false.

For external MCP endpoints, use a restrictive resource configuration. For internal chatbots, broader write capabilities can be enabled while still requiring confirmation.

## Installation

Copy the `MissionBayMemora` directory into the BASE3 plugin directory:

```text
plugin/MissionBayMemora
```

The default `PluginClassMap` scans:

```text
plugin/<PluginName>/src
```

No database migration is required by this package.

## Check dependencies

The plugin implements `ICheck` and reports missing ResourceFoundation service bindings. In a manager/runtime split, ensure the manager side binds these interfaces to ResourceFoundation proxies before using the tools.

Required services for the full toolset:

```text
IEntityDataService
IEntityFileService
IEntityRelationService
IEntityMetadataService
IEntityTagService
IEntityStructureService
IEntityActivityService
IEntityUserDataService
IEntityAccessService
IEntityProfileService
```


## Current open work

The active open-work list is maintained in:

```text
MissionBayMemora/docs/open-work.md
```

After package 08, profile tooling is complete. The main remaining generic work is capability/allowlist integration, optional large-file upload helpers, integration tests, and optional schema-aware validation helpers. Domain-specific CRM tools should remain outside MissionBayMemora core.

---

## Package 09: Semantic XRM and Knowledge Graph Tools

Package 09 makes Memora/XRM easier for chat agents to understand and use. The plugin now exposes semantics explicitly instead of relying on the model to infer everything from generic CRUD tools.

The relevant XRM model is:

```text
Entry
  has technical type     -> contact, product, project, task, address, date, file, link, note, ...
  has domain module      -> crmproduct, dancephotographydancer, dancephotographyshooting, ...
  has tags               -> classification, state, workflow markers
  has alloc relations    -> knowledge graph connections to other entries
```

This is important because many practical meanings are combinations, not standalone types. For example, a software module can be represented as a CRM product, while a dance photography dancer is a contact with dance-photography module/tag semantics. A photoshooting is project-like, but its knowledge lives in the relation graph: dancers, location, event, galleries, photos and related notes are connected through allocs.

### New semantic tools

```text
memora_get_xrm_semantic_model
memora_explain_xrm_semantics
memora_plan_domain_entry
memora_define_xrm_structure
```

`memora_define_xrm_structure` is confirmation-aware and can create/update scopes, modules and tag assignments.

### New knowledge-graph tools

```text
memora_get_knowledge_graph
memora_plan_required_relations
memora_connect_entries
memora_disconnect_entries
memora_replace_entry_relations
```

Graph mutations are confirmation-aware. `memora_replace_entry_relations` additionally requires destructive allowance in the tool configuration.

### New domain-entry tools

```text
memora_find_domain_entries
memora_create_domain_entry
memora_update_domain_entry_semantics
```

These tools are practical wrappers around the generic entity service. They resolve semantic profiles such as `crmproduct`, `dancephotographydancer` and `dancephotographyshooting` into type/module/tag plans before reading or writing.

### New resources

```text
memora://semantic-model
memora://semantic-model/{scope}
memora://domain-profiles
memora://domain-profile/{name}
memora://relation-patterns
memora://entry/{entry_id}/graph
```

### New documentation

```text
docs/semantic-xrm-model.md
docs/knowledge-graph-tools.md
docs/domain-entry-tools.md
```

### Current open work

See `docs/open-work.md`. MCP allowlist integration is not part of this plugin; the MissionBay main plugin owns MCP endpoint configuration and tool-list exposure.

---

## Patch 10: editable domain profiles in Memora/XRM

Domain profiles are no longer treated as hard-coded PHP data. MissionBayMemora now reads and writes semantic profiles through BASE3 `IMemora/XRM`.

Settings group:

```text
memora_sysmodule / sysscopemodule / sysmoduletag
```

Each profile is one Memora/XRM dataset. The dataset name is the semantic kind key, for example:

```text
crmproduct
dancephotographyshooting
dancephotographydancer
```

The semantic layer still explains Memora/XRM concepts to agents:

- type = technical data shape
- module = domain-specific meaning
- scope = larger domain area
- tags = classification, state and semantic markers
- allocs/relations = knowledge graph links between entries
- domain profiles = editable Memora/XRM records that map user intent to type/module/tags/relations

New tool class:

```text
MissionBayMemora\Resource\MemoraDomainProfileAgentTool
```

New tools:

```text
memora_get_domain_profiles
memora_get_domain_profile
memora_plan_domain_profile
memora_save_domain_profile
memora_remove_domain_profile
```

`memora_save_domain_profile` and `memora_remove_domain_profile` are confirmation-aware. The chatbot should present the proposed Memora/XRM change to the user first and execute only after explicit approval with `confirm=true`.

MissionBayMemora does not configure the MCP tool list. MissionBay remains responsible for MCP server behavior and tool allowlists. This plugin only provides well-described MissionBay tools, resources and prompts.
