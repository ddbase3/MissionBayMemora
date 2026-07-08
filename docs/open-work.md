# Open work after patch 11

Done:

- 04 read-only entry and structure tools
- 05 write and activity tools
- 06 access, role and membership tools
- 07 file and user-data tools
- 08 profile tools and capability overview
- 09 semantic XRM model, domain-entry tools and alloc/knowledge-graph tools
- 10 removed hard-coded PHP presets into editable concept layer
- 11 corrected semantic source of truth back to Memora/XRM structure services and removed SettingsStore domain profile storage

Still open:

1. End-to-end tests against direct XRM, Base3XrmWebsite microservices and Manager/Proxy operation.
2. Optional schema-/type-aware validation before create/update.
3. Large file uploads outside ordinary JSON tool calls.
4. Additional practical domain workflows after real chatbot usage shows repeated patterns.
5. Optional separate domain plugins only when generic Memora tools become too broad.
6. External MCP client examples belong in MissionBay documentation, not in MissionBayMemora.
