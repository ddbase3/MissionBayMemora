# MissionBayMemora Resources

MissionBayMemora exposes read-only JSON resources for MCP transports and internal MissionBay consumers.

## Entry resources

```text
memora://entry/{id}
memora://entry/{id}/summary
memora://entry/{id}/metadata
memora://entry/{id}/relations
memora://entry/{id}/activity
```

Entry resources call the same ResourceFoundation services as the callable tools and therefore keep the same backend access behavior.

## Structure resources

```text
memora://structure
memora://types
memora://type/{type}
memora://modules
memora://scopes
memora://tags
```

Structure resources are useful for tool clients that support MCP resource browsing before function calls.

## Response format

Resources return MissionBay/MCP-style content arrays:

```json
{
  "contents": [
    {
      "uri": "memora://types",
      "mimeType": "application/json",
      "text": "{...}"
    }
  ]
}
```
