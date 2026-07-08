# Deprecated: domain preset settings

Patch 11 removes the SettingsStore-backed domain preset direction.

Reason: Memora/XRM already stores the semantic structures directly through types, modules, scopes, tags, module-tag assignments and scope-module assignments. Duplicating that model in `ISettingsStore` would create a second semantic source of truth.

Use instead:

- `memora_get_xrm_semantic_model`
- `memora_explain_xrm_semantics`
- `memora_plan_domain_entry`
- `memora://domain-profiles`
- `memora://domain-profile/{module}`

Delete the old SettingsStore preset tool files listed in `DELETE_FILES.txt`.
