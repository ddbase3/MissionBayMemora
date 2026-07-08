# Semantic XRM Model Tools

MissionBayMemora exposes Memora/XRM as a semantic entity system rather than only a generic CRUD backend.

Memora entries should be understood through four layers:

1. **Type**: the technical data shape, such as `contact`, `product`, `project`, `task`, `address`, `date`, `file`, `link`, or `note`.
2. **Module**: the domain-specific meaning, such as `crmproduct`, `dancephotographydancer`, or `dancephotographyshooting`.
3. **Tags**: classification, state, workflow flags, and cross-cutting semantics.
4. **Relations / allocs**: links between entries. These links form the XRM knowledge graph.

## Read-only semantic tools

### `memora_get_xrm_semantic_model`

Returns an agent-friendly model containing concepts, types, scopes, modules, tags, built-in domain presets, and relation patterns.

Typical usage:

```json
{
  "scope": "dancephotography",
  "include": ["all"]
}
```

Use this before creating or changing entries when the right type, module, tags, or relation pattern is not obvious.

### `memora_explain_xrm_semantics`

Maps a phrase, module, type, or tag set to a semantic meaning.

Examples:

```json
{
  "input": "software module"
}
```

```json
{
  "input": "dance photo model"
}
```

The tool may resolve these to presets such as `software_module` or `dancephotography_dancer`.

### `memora_plan_domain_entry`

Builds a proposed payload without writing. It is the safest bridge from a natural-language request to a Memora entry shape.

Example:

```json
{
  "intent": "create a new ballet photoshooting",
  "kind": "dancephotography_shooting",
  "name": "Ballet shooting Berlin",
  "relations": [123, 456]
}
```

The result explains the selected type, module, required tags, suggested tags, and expected relations.

## Structure write tool

### `memora_define_xrm_structure`

This is a confirmation-aware tool for structure/catalog changes. It can:

- create scopes
- create modules
- update modules
- assign modules to scopes
- describe tags
- assign tags to scopes
- assign tags to modules
- remove module/tag assignments

Every call should first return a `confirmation_required` plan. The tool should only be executed with `confirm=true` after the user approves the exact change.

## Built-in semantic presets

The plugin includes built-in presets for common XRM concepts:

- `software_module`: a CRM product representing a software module/plugin/service package
- `crm_contact`
- `crm_project`
- `crm_task`
- `dancephotography_dancer`
- `dancephotography_location`
- `dancephotography_event`
- `dancephotography_shooting`
- `dancephotography_gallery`
- `dancephotography_photo`
- `note`

These presets are guidance for agents. They do not replace runtime discovery. If the live XRM structure differs, runtime services remain authoritative.
