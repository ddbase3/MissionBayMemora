# Confirmation Workflow

MissionBayMemora write tools are built around a two-step confirmation workflow.

This is meant for internal chatbots and MCP clients that support offering a planned change to the user before executing it.

## Step 1: prepare

The assistant calls a write tool without `confirm`, or with:

```json
{
  "confirm": false
}
```

The tool does not mutate state. It returns a structured result:

```json
{
  "ok": true,
  "status": "confirmation_required",
  "confirmation_required": true,
  "confirm_argument": "confirm",
  "message": "Entry update requires user confirmation before execution.",
  "data": {
    "plan": {
      "operation": "update_entry",
      "entry_id": 123,
      "patch": {}
    },
    "arguments": {
      "entry_id": 123,
      "patch": {},
      "confirm": true
    }
  }
}
```

The assistant should show the plan to the user.

## Step 2: correct or confirm

The user can correct the proposed payload. The assistant should then call the tool again with the corrected payload and `confirm=false`, unless the correction is already a clear final approval.

When the user explicitly approves, the assistant repeats the tool call with:

```json
{
  "confirm": true
}
```

## Important rule

A chatbot should not invent confirmation. It should wait for explicit user intent such as:

```text
Ja, ausführen.
Bestätigt.
Speichern.
Mach das so.
```

## Policy interaction

Confirmation is not the only safety mechanism.

The tool also checks `MemoraAgentAccessPolicy` capabilities. If a capability is denied, the tool returns `capability_denied` even when `confirm=true` is supplied.

## Default behavior

`require_confirmation` defaults to `true`.

A trusted internal flow may disable it through resource configuration, but this should not be used for external MCP exposure.
