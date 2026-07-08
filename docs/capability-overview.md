# Capability overview after patch 11

MissionBayMemora exposes tools only. MissionBay decides which tools are exposed through MCP tool lists.

The important semantic change in patch 11 is that domain profiles are derived from Memora/XRM structure services. No additional capability is needed for SettingsStore domain profile management because that storage is no longer part of the plugin.

Relevant capability groups:

- `semantic_read` for reading XRM concepts, modules, scopes, tags and derived domain profiles
- `semantic_write` for semantic write helpers
- `structure_write` for changing Memora structure records such as scopes, modules and tag assignments
- `domain_read` and `domain_write` for practical domain entry tools
- `graph_read` and `graph_write` for alloc/knowledge graph tools

Write tools remain confirmation-aware by default.
