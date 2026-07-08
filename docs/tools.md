# MissionBayMemora Tools

## memora_search_entries

Searches visible entries through `IEntityDataService::getEntries()`.

Parameters:

```json
{
  "query": "optional local text filter",
  "type": "optional type alias or id",
  "module": "optional module filter",
  "tags": ["optional", "required", "tags"],
  "include": ["data", "metadata", "tags", "relations", "access", "activity", "userdata"],
  "limit": 10,
  "offset": 0
}
```

The search uses native Memora filters where available: type, module, tag, limit and offset. The free-text `query` value is applied locally to returned rows because the ResourceFoundation entity service does not define a generic full-text search contract.

## memora_get_entry

Loads one visible entry.

Parameters:

```json
{
  "id": 123,
  "id_type": "auto",
  "include": ["data", "metadata", "tags", "relations", "access"]
}
```

Numeric ids are loaded directly. Non-numeric ids are treated as uuid or name candidates and resolved from a bounded visible entry list.

## memora_get_related_entries

Lists relation rows for one entry and optionally loads related entry summaries.

Parameters:

```json
{
  "entry_id": 123,
  "include_entries": true,
  "limit": 25,
  "offset": 0
}
```

Consumes `IEntityRelationService` and, when requested, `IEntityDataService`.

## memora_get_entry_context

Builds an agent-oriented context package around one entry.

Parameters:

```json
{
  "entry_id": 123,
  "include": ["data", "metadata", "tags", "relations", "access", "activity"],
  "limit": 10
}
```

This is the preferred tool for internal chatbot workflows where the assistant needs to understand a task, note, project, contact or another XRM object in context.

## memora_get_structure

Returns type, module, scope and tag structure data.

Parameters:

```json
{
  "section": "all",
  "query": "optional text filter",
  "limit": 50
}
```

Allowed sections:

```text
all
types
modules
scopes
tags
```

## memora_describe_type

Loads one type definition by id or alias.

Parameters:

```json
{
  "type": "task"
}
```

Use this before creating or updating typed entries.
