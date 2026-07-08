# MissionBayMemora Prompts

Prompts provide suggested workflows for models and MCP clients. They do not execute work by themselves.

## memora_find_entry

Use this when a user asks for an entry but does not provide a clear id.

Workflow:

```text
1. Call memora_search_entries with a small limit.
2. If exactly one candidate is likely, call memora_get_entry.
3. If several candidates match, summarize candidates or ask the user to choose.
```

## memora_read_entry

Use this when a user provides an entry id or an already selected candidate.

Workflow:

```text
1. Call memora_get_entry.
2. Include data, metadata, tags, relations and access if a complete view is needed.
3. Use memora_get_entry_context when surrounding activity or relation context matters.
```

## memora_analyze_entry_context

Use this when a user wants a summary, diagnosis, explanation or next-step suggestion around one entry.

Workflow:

```text
1. Call memora_get_entry_context.
2. Summarize the entry, relevant relations, metadata, tags and recent activity.
3. Do not mutate data unless the user explicitly asks for a change and write tools are available.
```

## memora_discover_structure

Use this before choosing type, module, scope or tag filters.

## memora_describe_type

Use this before creating or updating entries of a type.
