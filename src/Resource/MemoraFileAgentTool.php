<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Resource;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;
use MissionBayMemora\Service\MemoraAgentAccessPolicy;
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use ResourceFoundation\Api\IEntityFileService;

/**
 * Memora/XRM file tool.
 *
 * Provides read access to file entries and confirmation-aware file mutations.
 */
class MemoraFileAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_FILE = 'memora_get_file';
        private const TOOL_GET_FILE_CONTENT = 'memora_get_file_content';
        private const TOOL_CREATE_FILE = 'memora_create_file';
        private const TOOL_REPLACE_FILE = 'memora_replace_file';
        private const TOOL_DELETE_FILE = 'memora_delete_file';

        private const MAX_CONTENT_LENGTH = 250000;
        private const DEFAULT_CONTENT_LENGTH = 50000;

        public function __construct(
                private readonly IEntityFileService $fileService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorafileagenttool';
        }

        public function getDescription(): string {
                return 'Provides Memora/XRM file read tools and confirmation-aware file mutation tools.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get File',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'file', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_FILE,
                                        'description' => 'Read one Memora/XRM file entry by id. Content is not returned unless include_content is true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'file_id' => [ 'description' => 'Required file entry id.' ],
                                                        'include_content' => [ 'type' => 'boolean', 'description' => 'Whether to include file content. Default false.' ],
                                                        'encoding' => [ 'type' => 'string', 'enum' => ['base64', 'raw'], 'description' => 'Content encoding when include_content is true. Default base64.' ],
                                                        'max_length' => [ 'type' => 'integer', 'description' => 'Maximum content string length returned. Default: 50000. Hard maximum: 250000.' ]
                                                ],
                                                'required' => ['file_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get File Content',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'file', 'content', 'readonly'],
                                'priority' => 65,
                                'function' => [
                                        'name' => self::TOOL_GET_FILE_CONTENT,
                                        'description' => 'Read physical content for one Memora/XRM file entry. Default encoding is base64.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'file_id' => [ 'description' => 'Required file entry id.' ],
                                                        'encoding' => [ 'type' => 'string', 'enum' => ['base64', 'raw'], 'description' => 'Returned content encoding. Default base64.' ],
                                                        'max_length' => [ 'type' => 'integer', 'description' => 'Maximum content string length returned. Default: 50000. Hard maximum: 250000.' ]
                                                ],
                                                'required' => ['file_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Create File',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'file', 'write', 'confirmation'],
                                'priority' => 45,
                                'function' => [
                                        'name' => self::TOOL_CREATE_FILE,
                                        'description' => 'Prepare or execute creating a Memora/XRM file entry. First call without confirm or with confirm=false to get a review plan. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'file' => [ 'type' => 'object', 'description' => 'Optional file payload object. Top-level fields override this object.' ],
                                                        'filename' => [ 'type' => 'string', 'description' => 'Original filename. Required when file.filename is missing.' ],
                                                        'content_base64' => [ 'type' => 'string', 'description' => 'Required base64 encoded physical content.' ],
                                                        'mime' => [ 'type' => 'string', 'description' => 'Optional MIME type.' ],
                                                        'size' => [ 'type' => 'integer', 'description' => 'Optional byte size.' ],
                                                        'name' => [ 'type' => 'string', 'description' => 'Optional entity name/title.' ],
                                                        'description' => [ 'type' => 'string', 'description' => 'Optional file description.' ],
                                                        'content' => [ 'type' => 'string', 'description' => 'Optional text/caption content.' ],
                                                        'preview' => [ 'type' => 'string', 'description' => 'Optional preview payload.' ],
                                                        'options' => [ 'type' => 'object', 'description' => 'Optional create options such as tags, metadata, allocs, useraccess or groupaccess.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute. Default false returns only a proposed create plan.' ]
                                                ],
                                                'required' => []
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Replace File',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'file', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_REPLACE_FILE,
                                        'description' => 'Prepare or execute replacing physical content and metadata of an existing Memora/XRM file entry. Requires confirmation before mutation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'file_id' => [ 'description' => 'Required file entry id.' ],
                                                        'file' => [ 'type' => 'object', 'description' => 'Optional file payload object. Top-level fields override this object.' ],
                                                        'filename' => [ 'type' => 'string', 'description' => 'Optional replacement filename.' ],
                                                        'content_base64' => [ 'type' => 'string', 'description' => 'Required base64 encoded replacement content.' ],
                                                        'mime' => [ 'type' => 'string', 'description' => 'Optional MIME type.' ],
                                                        'size' => [ 'type' => 'integer', 'description' => 'Optional byte size.' ],
                                                        'name' => [ 'type' => 'string', 'description' => 'Optional entity name/title.' ],
                                                        'description' => [ 'type' => 'string', 'description' => 'Optional file description.' ],
                                                        'content' => [ 'type' => 'string', 'description' => 'Optional text/caption content.' ],
                                                        'preview' => [ 'type' => 'string', 'description' => 'Optional preview payload.' ],
                                                        'options' => [ 'type' => 'object', 'description' => 'Optional replace options.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['file_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Delete File',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'file', 'delete', 'destructive', 'confirmation'],
                                'priority' => 25,
                                'function' => [
                                        'name' => self::TOOL_DELETE_FILE,
                                        'description' => 'Prepare or execute deleting a Memora/XRM file entry. This is destructive, requires allow_destructive=true in configuration, and requires confirmation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'file_id' => [ 'description' => 'Required file entry id.' ],
                                                        'deletephysical' => [ 'type' => 'boolean', 'description' => 'Whether to delete the physical file. Default true.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['file_id']
                                        ]
                                ]
                        ]
                ];
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        public function callTool(string $name, array $arguments, IAgentContext $context): array {
                try {
                        return match ($name) {
                                self::TOOL_GET_FILE => $this->getFile($arguments, $context),
                                self::TOOL_GET_FILE_CONTENT => $this->getFileContent($arguments, $context),
                                self::TOOL_CREATE_FILE => $this->createFile($arguments, $context),
                                self::TOOL_REPLACE_FILE => $this->replaceFile($arguments, $context),
                                self::TOOL_DELETE_FILE => $this->deleteFile($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora file tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uriTemplate' => 'memora://file/{file_id}',
                                'name' => 'memora-file-template',
                                'title' => 'Memora File',
                                'description' => 'Reads one Memora/XRM file entry without physical content.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://file/{file_id}/content',
                                'name' => 'memora-file-content-template',
                                'title' => 'Memora File Content',
                                'description' => 'Reads physical content for one Memora/XRM file entry as base64 JSON.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                $prefix = 'memora://file/';
                if (!str_starts_with($uri, $prefix)) {
                        return null;
                }

                $rest = rawurldecode(substr($uri, strlen($prefix)));
                if ($rest === '') {
                        return null;
                }

                if (str_ends_with($rest, '/content')) {
                        $fileId = substr($rest, 0, -strlen('/content'));
                        $result = $this->getFileContent(['file_id' => $fileId, 'encoding' => 'base64'], $context);
                        return $this->resultBuilder->resource($uri, $result);
                }

                $result = $this->getFile(['file_id' => $rest], $context);
                return $this->resultBuilder->resource($uri, $result);
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_read_file',
                                'title' => 'Read a Memora File',
                                'description' => 'Guide the model to inspect a file entry and only request physical content when needed.',
                                'arguments' => [[ 'name' => 'file_id', 'description' => 'Optional file entry id.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_create_file',
                                'title' => 'Create a Memora File With Confirmation',
                                'description' => 'Guide the model to prepare a file creation plan and wait for user confirmation before writing.',
                                'arguments' => []
                        ],
                        [
                                'name' => 'memora_replace_file',
                                'title' => 'Replace a Memora File With Confirmation',
                                'description' => 'Guide the model to prepare a replacement plan and wait for user confirmation before writing.',
                                'arguments' => [[ 'name' => 'file_id', 'description' => 'Optional file entry id.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_delete_file',
                                'title' => 'Delete a Memora File With Confirmation',
                                'description' => 'Guide the model to prepare a destructive delete plan and wait for explicit user confirmation.',
                                'arguments' => [[ 'name' => 'file_id', 'description' => 'Optional file entry id.', 'required' => false ]]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);
                $fileId = $this->normalizer->normalizeString($arguments['file_id'] ?? '');

                if ($name === 'memora_read_file') {
                        $lines = [
                                'Use memora_get_file to inspect a Memora/XRM file entry.',
                                'Only call memora_get_file_content when the user explicitly needs the physical content.',
                                'Prefer base64 content for binary-safe transport.'
                        ];
                        if ($fileId !== '') $lines[] = 'Known file id: ' . $fileId;
                        return $this->resultBuilder->prompt('Read a Memora/XRM file safely.', implode("
", $lines));
                }

                if ($name === 'memora_create_file') {
                        return $this->resultBuilder->prompt(
                                'Create a Memora/XRM file safely.',
                                'Prepare the file payload for memora_create_file. Call it first with confirm=false or without confirm, show the proposed file metadata to the user, and execute only after explicit approval with confirm=true.'
                        );
                }

                if ($name === 'memora_replace_file') {
                        $lines = [
                                'Prepare a memora_replace_file call for an existing file entry.',
                                'Call it first without confirm to get a review plan. Execute only after user approval with confirm=true.'
                        ];
                        if ($fileId !== '') $lines[] = 'Known file id: ' . $fileId;
                        return $this->resultBuilder->prompt('Replace a Memora/XRM file safely.', implode("
", $lines));
                }

                if ($name === 'memora_delete_file') {
                        $lines = [
                                'Prepare a memora_delete_file call only when the user explicitly asks to delete a file.',
                                'This is destructive. Show the delete plan and execute only after explicit user approval with confirm=true.'
                        ];
                        if ($fileId !== '') $lines[] = 'Known file id: ' . $fileId;
                        return $this->resultBuilder->prompt('Delete a Memora/XRM file safely.', implode("
", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getFile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_FILE_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE, 'capability_denied', 'File reading is not allowed for this tool configuration.');
                }

                $fileId = $arguments['file_id'] ?? $arguments['id'] ?? null;
                if ($fileId === null || $fileId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE, 'missing_file_id', 'Missing required parameter: file_id.');
                }

                $file = $this->fileService->getFile($fileId, $this->normalizer->normalizeArray($arguments['options'] ?? []));
                if ($file === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE, 'file_not_found', 'File entry not found.');
                }

                $data = [
                        'file_id' => $fileId,
                        'file' => $file
                ];

                if ($this->normalizer->normalizeBool($arguments['include_content'] ?? false, false)) {
                        $encoding = $this->normalizeEncoding($arguments['encoding'] ?? 'base64');
                        $content = $this->fileService->getFileContent($fileId, ['encoding' => $encoding]);
                        $data['content'] = $this->buildContentData($content, $encoding, $arguments);
                }

                return $this->resultBuilder->success(self::TOOL_GET_FILE, 'File entry loaded.', $data);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getFileContent(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_FILE_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE_CONTENT, 'capability_denied', 'File content reading is not allowed for this tool configuration.');
                }

                $fileId = $arguments['file_id'] ?? $arguments['id'] ?? null;
                if ($fileId === null || $fileId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE_CONTENT, 'missing_file_id', 'Missing required parameter: file_id.');
                }

                $encoding = $this->normalizeEncoding($arguments['encoding'] ?? 'base64');
                $content = $this->fileService->getFileContent($fileId, ['encoding' => $encoding]);
                if ($content === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_FILE_CONTENT, 'file_content_not_found', 'File content not found.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_FILE_CONTENT,
                        'File content loaded.',
                        [
                                'file_id' => $fileId,
                                'content' => $this->buildContentData($content, $encoding, $arguments)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createFile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_FILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_FILE, 'capability_denied', 'File creation is not allowed for this tool configuration.');
                }

                $file = $this->buildFilePayload($arguments, true);
                if ($file === []) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_FILE, 'invalid_file_payload', 'File creation requires filename and content_base64.');
                }

                $options = $this->buildOptions($arguments);
                $plan = [
                        'operation' => 'create_file',
                        'file' => $this->summarizeFilePayload($file),
                        'options' => $options
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_CREATE_FILE, 'File creation requires user confirmation before execution.', $plan, $arguments);
                }

                $created = $this->fileService->createFile($file, $options);
                return $this->resultBuilder->success(self::TOOL_CREATE_FILE, 'File created.', ['file' => $created]);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function replaceFile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_FILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_FILE, 'capability_denied', 'File replacement is not allowed for this tool configuration.');
                }

                $fileId = $arguments['file_id'] ?? $arguments['id'] ?? null;
                if ($fileId === null || $fileId === '') {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_FILE, 'missing_file_id', 'Missing required parameter: file_id.');
                }

                $file = $this->buildFilePayload($arguments, false);
                if (!isset($file['content_base64']) || trim((string)$file['content_base64']) === '') {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_FILE, 'invalid_file_payload', 'File replacement requires content_base64.');
                }

                $options = $this->buildOptions($arguments);
                $plan = [
                        'operation' => 'replace_file',
                        'file_id' => $fileId,
                        'file' => $this->summarizeFilePayload($file),
                        'options' => $options
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_REPLACE_FILE, 'File replacement requires user confirmation before execution.', $plan, $arguments);
                }

                $updated = $this->fileService->replaceFile($fileId, $file, $options);
                return $this->resultBuilder->success(self::TOOL_REPLACE_FILE, 'File replaced.', ['file_id' => $fileId, 'file' => $updated]);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function deleteFile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_FILE_WRITE, $this->config, $context)
                        || !$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DESTRUCTIVE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_DELETE_FILE, 'capability_denied', 'File deletion is not allowed for this tool configuration.');
                }

                $fileId = $arguments['file_id'] ?? $arguments['id'] ?? null;
                if ($fileId === null || $fileId === '') {
                        return $this->resultBuilder->error(self::TOOL_DELETE_FILE, 'missing_file_id', 'Missing required parameter: file_id.');
                }

                $deletePhysical = $this->normalizer->normalizeBool($arguments['deletephysical'] ?? true, true);
                $plan = [
                        'operation' => 'delete_file',
                        'file_id' => $fileId,
                        'deletephysical' => $deletePhysical,
                        'file_before' => $this->fileService->getFile($fileId)
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_DELETE_FILE, 'File deletion requires user confirmation before execution.', $plan, $arguments);
                }

                $ok = $this->fileService->deleteFile($fileId, ['deletephysical' => $deletePhysical]);
                return $this->resultBuilder->success(self::TOOL_DELETE_FILE, $ok ? 'File deleted.' : 'File deletion returned false.', ['file_id' => $fileId, 'deleted' => $ok]);
        }

        private function normalizeEncoding(mixed $encoding): string {
                $encoding = $this->normalizer->normalizeToken((string)$encoding, ['base64', 'raw'], 'base64');
                return $encoding !== '' ? $encoding : 'base64';
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildContentData(?string $content, string $encoding, array $arguments): array {
                if ($content === null) {
                        return [
                                'encoding' => $encoding,
                                'value' => null,
                                'truncated' => false,
                                'length' => 0
                        ];
                }

                $maxLength = $this->normalizer->normalizeLimit($arguments['max_length'] ?? self::DEFAULT_CONTENT_LENGTH, self::DEFAULT_CONTENT_LENGTH, self::MAX_CONTENT_LENGTH);
                $length = strlen($content);
                $truncated = $length > $maxLength;

                return [
                        'encoding' => $encoding,
                        'value' => $truncated ? substr($content, 0, $maxLength) : $content,
                        'truncated' => $truncated,
                        'length' => $length,
                        'returned_length' => $truncated ? $maxLength : $length
                ];
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildFilePayload(array $arguments, bool $requireFilename): array {
                $file = $this->normalizer->normalizeArray($arguments['file'] ?? []);

                foreach (['filename', 'content_base64', 'mime', 'name', 'description', 'content', 'preview'] as $key) {
                        if (array_key_exists($key, $arguments)) {
                                $value = $this->normalizer->normalizeString($arguments[$key] ?? '');
                                if ($value !== '') {
                                        $file[$key] = $value;
                                }
                        }
                }

                if (array_key_exists('size', $arguments)) {
                        $file['size'] = max(0, (int)$arguments['size']);
                }

                if ($requireFilename && (!isset($file['filename']) || trim((string)$file['filename']) === '')) {
                        return [];
                }

                if (!isset($file['content_base64']) || trim((string)$file['content_base64']) === '') {
                        return [];
                }

                return $file;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildOptions(array $arguments): array {
                $options = $this->normalizer->normalizeArray($arguments['options'] ?? []);

                foreach (['allocs', 'alloc', 'tags', 'tag', 'metadata', 'useraccess', 'groupaccess'] as $key) {
                        if (array_key_exists($key, $arguments)) {
                                $options[$key] = $arguments[$key];
                        }
                }

                return $options;
        }

        /**
         * @param array<string,mixed> $file
         * @return array<string,mixed>
         */
        private function summarizeFilePayload(array $file): array {
                $summary = $file;
                if (isset($summary['content_base64'])) {
                        $summary['content_base64_length'] = strlen((string)$summary['content_base64']);
                        unset($summary['content_base64']);
                }

                return $summary;
        }

        /**
         * @param array<string,mixed> $arguments
         */
        private function needsConfirmation(array $arguments): bool {
                if (!$this->accessPolicy->requiresConfirmation($this->config)) {
                        return false;
                }

                return !$this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
        }

        /**
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function confirmation(string $tool, string $message, array $plan, array $arguments): array {
                $confirmationArguments = $arguments;
                $confirmationArguments['confirm'] = true;

                return $this->resultBuilder->confirmationRequired($tool, $message, $plan, $confirmationArguments);
        }
}
