# Domain Entry Tools

Domain-entry tools are practical wrappers around generic Memora entry CRUD. They help agents create and search entries using semantic presets instead of forcing the model to remember raw type/module/tag combinations.

## `memora_find_domain_entries`

Finds entries by semantic kind.

Example:

```json
{
  "kind": "dancephotography_dancer",
  "query": "Anna",
  "limit": 10
}
```

This maps the kind to type/module/tags before searching.

## `memora_create_domain_entry`

Creates a domain entry through a confirmation workflow.

Example:

```json
{
  "kind": "software_module",
  "name": "MissionBayMemora",
  "data": {
    "description": "Agent and MCP tool layer for Memora/XRM"
  },
  "confirm": false
}
```

The first call returns a plan. The chatbot should show the plan to the user and wait for corrections or approval. Only the approved second call with `confirm=true` writes the entry.

## `memora_update_domain_entry_semantics`

Updates semantic aspects of an existing entry:

- name
- metadata
- add/remove/replace tags
- add/remove/replace relations

This tool should be used when the meaning, classification, or graph connections of an entry change.

## Photoshooting example

A dance photography photoshooting is modeled as a project-like entry:

- kind: `dancephotography_shooting`
- type: `project`
- module: `dancephotographyshooting`
- required tags: `dancephotography`, `dancephotographyproject`

It should normally be connected through alloc relations to:

- one or more dancers: `contact` / `dancephotographydancer`
- a location: `address` / `dancephotographylocation`
- optionally an event/date: `date` / `dancephotographyevent`
- optionally galleries, photos, social links, notes, and planning records

The exact relation set depends on the concrete workflow. The graph tools help the agent inspect and propose those links.
