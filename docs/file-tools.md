# MissionBayMemora File Tools

Package 07 adds file tools for MissionBayMemora.

The tools use `ResourceFoundation\Api\IEntityFileService` and never access Memora tables or file storage directly.

## Functions

```text
memora_get_file
memora_get_file_content
memora_create_file
memora_replace_file
memora_delete_file
```

## Read functions

### memora_get_file

Loads one file entry by `file_id`.

By default, the tool returns the file entity only. It does not include physical content unless `include_content=true` is supplied.

Useful arguments:

```json
{
  "file_id": 123,
  "include_content": false,
  "encoding": "base64",
  "max_length": 50000
}
```

### memora_get_file_content

Loads physical file content for one file entry.

Default content encoding is `base64`, because this is safe for binary transport through MCP and chat model tool results.

```json
{
  "file_id": 123,
  "encoding": "base64",
  "max_length": 50000
}
```

The result contains:

```json
{
  "content": {
    "encoding": "base64",
    "value": "...",
    "truncated": false,
    "length": 1234,
    "returned_length": 1234
  }
}
```

## Write functions

File write functions are confirmation-aware.

A first call without `confirm=true` returns `confirmation_required` and does not mutate storage.

### memora_create_file

Creates a file entry and stores physical content.

Required payload:

```json
{
  "filename": "document.pdf",
  "content_base64": "..."
}
```

Optional fields:

```text
mime
size
name
description
content
preview
options
```

`options` is passed to the ResourceFoundation file service and may contain backend-supported options such as tags, metadata, allocs, useraccess or groupaccess.

### memora_replace_file

Replaces physical content and file metadata for an existing file entry.

Required payload:

```json
{
  "file_id": 123,
  "content_base64": "..."
}
```

### memora_delete_file

Deletes a file entry and optionally the physical file.

This is destructive. The resource configuration must allow both:

```text
allow_file_write = true
allow_destructive = true
```

The tool also requires confirmation.

## MCP resources

```text
memora://file/{file_id}
memora://file/{file_id}/content
```

Resources are read-only and return JSON tool-result envelopes.
