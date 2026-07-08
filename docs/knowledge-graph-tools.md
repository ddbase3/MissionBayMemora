# Knowledge Graph Tools

Memora/XRM knowledge spans across entries through alloc relations. Tags and modules explain what an entry is; relations explain how entries belong together.

A concrete example is a dance photography shooting. The shooting itself is a project-like entry, but the knowledge is incomplete without relations to related dancers, locations, events, galleries, photos, links, and notes.

## `memora_get_knowledge_graph`

Loads a graph around one root entry.

```json
{
  "entry_id": 1000,
  "depth": 1,
  "include_entries": true,
  "limit": 25
}
```

The result contains:

- `nodes`: entry summaries plus inferred semantic kind where possible
- `edges`: alloc relation links
- `graph_guidance`: reminders for agents on how to interpret XRM relations

Depth should usually stay at `1` or `2`. Higher depth can quickly become noisy.

## `memora_plan_required_relations`

Explains which relation types are normally expected for a semantic kind.

Example:

```json
{
  "kind": "dancephotography_shooting"
}
```

The result describes that a shooting should normally connect to a dancer/contact and location/address, with optional event/date, gallery/link, and photo/file relations.

## Confirmation-aware graph mutations

### `memora_connect_entries`

Adds alloc relations.

```json
{
  "entry_id": 1000,
  "peer_ids": [123, 456],
  "relation_meaning": "Connect shooting to dancer and location",
  "confirm": false
}
```

### `memora_disconnect_entries`

Removes alloc relations.

### `memora_replace_entry_relations`

Replaces all relations of one entry. This is stronger than adding/removing individual links and requires destructive allowance in the tool configuration.

## Agent behavior

Agents should:

1. inspect current graph before proposing changes,
2. explain why a relation should be added or removed,
3. present the returned confirmation plan to the user,
4. execute only after explicit approval with `confirm=true`.
