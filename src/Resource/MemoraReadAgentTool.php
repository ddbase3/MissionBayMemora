<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Resource;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentResourceProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;
use MissionBayMemora\Service\MemoraAgentEntryFormatter;
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use ResourceFoundation\Api\IEntityActivityService;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityMetadataService;
use ResourceFoundation\Api\IEntityRelationService;
use ResourceFoundation\Api\IEntityTagService;
use ResourceFoundation\Api\IEntityUserDataService;

/**
 * Read-only Memora/XRM agent tool.
 *
 * Exposes entry search, entry detail loading, relation inspection, and
 * context-oriented lookup functions for MissionBay and MCP transports.
 */
class MemoraReadAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_SEARCH = 'memora_search_entries';
        private const TOOL_GET_ENTRY = 'memora_get_entry';
        private const TOOL_GET_RELATED = 'memora_get_related_entries';
        private const TOOL_GET_CONTEXT = 'memora_get_entry_context';

        private const MAX_LIMIT = 50;
        private const MAX_SEARCH_FETCH = 150;

        private const ENTRY_INCLUDES = [
                'data',
                'metadata',
                'tags',
                'relations',
                'access',
                'activity',
                'userdata',
                'all'
        ];

        public function __construct(
                private readonly IEntityDataService $entityDataService,
                private readonly IEntityRelationService $relationService,
                private readonly IEntityMetadataService $metadataService,
                private readonly IEntityTagService $tagService,
                private readonly IEntityActivityService $activityService,
                private readonly IEntityUserDataService $userDataService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentEntryFormatter $formatter,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorareadagenttool';
        }

        public function getDescription(): string {
                return 'Provides read-only Memora/XRM entity search, detail, relation, and context tools.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Search Entries',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'search', 'readonly'],
                                'priority' => 100,
                                'function' => [
                                        'name' => self::TOOL_SEARCH,
                                        'description' => 'Search visible Memora/XRM entries. Use this before loading a detail entry when the identifier is unknown.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'query' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional free-text filter applied to returned entry summaries.'
                                                        ],
                                                        'type' => [
                                                                'description' => 'Optional Memora type alias or id. String aliases are preferred.'
                                                        ],
                                                        'module' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional module filter.'
                                                        ],
                                                        'tags' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Optional list of tags that must be assigned to the entry.'
                                                        ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::ENTRY_INCLUDES
                                                                ],
                                                                'description' => 'Optional detail aspects to include. Defaults to summary fields, tags, type and access.'
                                                        ],
                                                        'limit' => [
                                                                'type' => 'integer',
                                                                'description' => 'Maximum number of entries to return. Default: 10. Hard maximum: 50.'
                                                        ],
                                                        'offset' => [
                                                                'type' => 'integer',
                                                                'description' => 'Pagination offset.'
                                                        ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Entry',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'detail', 'readonly'],
                                'priority' => 95,
                                'function' => [
                                        'name' => self::TOOL_GET_ENTRY,
                                        'description' => 'Load one visible Memora/XRM entry by id. Use include to request data, metadata, tags, relations, activity or user data.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'id' => [
                                                                'description' => 'Entry id. Numeric ids are loaded directly. Non-numeric values are treated as uuid or name candidates.'
                                                        ],
                                                        'id_type' => [
                                                                'type' => 'string',
                                                                'enum' => ['id', 'uuid', 'auto'],
                                                                'description' => 'Identifier mode. Default: auto.'
                                                        ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::ENTRY_INCLUDES
                                                                ],
                                                                'description' => 'Optional aspects to include.'
                                                        ]
                                                ],
                                                'required' => ['id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Related Entries',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'relations', 'readonly'],
                                'priority' => 90,
                                'function' => [
                                        'name' => self::TOOL_GET_RELATED,
                                        'description' => 'List relation rows and optionally load related entry summaries for one entry.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => ['description' => 'Entry id'],
                                                        'include_entries' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Whether related entry summaries should be loaded.'
                                                        ],
                                                        'limit' => ['type' => 'integer'],
                                                        'offset' => ['type' => 'integer']
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Entry Context',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'context', 'readonly'],
                                'priority' => 85,
                                'function' => [
                                        'name' => self::TOOL_GET_CONTEXT,
                                        'description' => 'Load an agent-friendly context package around one entry, including detail, relations and recent activity.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => ['description' => 'Entry id'],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::ENTRY_INCLUDES
                                                                ]
                                                        ],
                                                        'limit' => ['type' => 'integer']
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ]
                ];
        }

        public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
                try {
                        return match ($name) {
                                self::TOOL_SEARCH => $this->searchEntries($arguments),
                                self::TOOL_GET_ENTRY => $this->getEntry($arguments),
                                self::TOOL_GET_RELATED => $this->getRelatedEntries($arguments),
                                self::TOOL_GET_CONTEXT => $this->getEntryContext($arguments),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora read tool failed to process the request.'
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uriTemplate' => 'memora://entry/{id}',
                                'name' => 'memora-entry-template',
                                'title' => 'Memora Entry',
                                'description' => 'Reads a visible Memora/XRM entry by numeric id.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{id}/summary',
                                'name' => 'memora-entry-summary-template',
                                'title' => 'Memora Entry Summary',
                                'description' => 'Reads a compact visible entry summary.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{id}/metadata',
                                'name' => 'memora-entry-metadata-template',
                                'title' => 'Memora Entry Metadata',
                                'description' => 'Reads metadata for a visible entry.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{id}/relations',
                                'name' => 'memora-entry-relations-template',
                                'title' => 'Memora Entry Relations',
                                'description' => 'Reads relations for a visible entry.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{id}/activity',
                                'name' => 'memora-entry-activity-template',
                                'title' => 'Memora Entry Activity',
                                'description' => 'Reads logs and comments for a visible entry.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                $prefix = 'memora://entry/';
                if (!str_starts_with($uri, $prefix)) {
                        return null;
                }

                $path = rawurldecode(substr($uri, strlen($prefix)));
                $parts = explode('/', trim($path, '/'));
                $id = $parts[0] ?? '';
                $aspect = $parts[1] ?? 'detail';

                if ($id === '') {
                        return $this->resultBuilder->resource($uri, [
                                'ok' => false,
                                'code' => 'missing_entry_id',
                                'message' => 'Missing entry id.'
                        ]);
                }

                $arguments = [
                        'id' => $id,
                        'include' => match ($aspect) {
                                'summary' => [],
                                'metadata' => ['metadata'],
                                'relations' => ['relations'],
                                'activity' => ['activity'],
                                default => ['data', 'metadata', 'tags', 'relations', 'access']
                        }
                ];

                if ($aspect === 'relations') {
                        $result = $this->getRelatedEntries([
                                'entry_id' => $id,
                                'include_entries' => true,
                                'limit' => 25
                        ]);
                } elseif ($aspect === 'activity') {
                        $result = $this->getEntryContext([
                                'entry_id' => $id,
                                'include' => ['activity'],
                                'limit' => 25
                        ]);
                } else {
                        $result = $this->getEntry($arguments);
                }

                return $this->resultBuilder->resource($uri, $result);
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_find_entry',
                                'title' => 'Find a Memora Entry',
                                'description' => 'Guide the model to search Memora/XRM entries and then load the best matching detail entry.',
                                'arguments' => [
                                        [ 'name' => 'query', 'description' => 'Optional search text.', 'required' => false ],
                                        [ 'name' => 'type', 'description' => 'Optional type alias.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_read_entry',
                                'title' => 'Read a Memora Entry',
                                'description' => 'Guide the model to load one Memora/XRM entry with useful context.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Entry id to read.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_analyze_entry_context',
                                'title' => 'Analyze Memora Entry Context',
                                'description' => 'Guide the model to inspect an entry together with relations and activity.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Entry id to inspect.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_find_entry') {
                        $query = $this->normalizer->normalizeString($arguments['query'] ?? '');
                        $type = $this->normalizer->normalizeString($arguments['type'] ?? '');

                        $lines = [
                                'Use memora_search_entries to find visible Memora/XRM entries.',
                                'Start with a small limit. If multiple entries match, prefer a summary answer or ask the user to choose.',
                                'Use memora_get_entry only after you have a likely id.'
                        ];
                        if ($query !== '') $lines[] = 'Search query: ' . $query;
                        if ($type !== '') $lines[] = 'Preferred type: ' . $type;

                        return $this->resultBuilder->prompt('Find a Memora/XRM entry.', implode("\n", $lines));
                }

                if ($name === 'memora_read_entry') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Use memora_get_entry to load the entry detail.',
                                'Include data, metadata, tags, relations and access when the user asks for a complete view.',
                                'Use memora_get_entry_context when the user needs surrounding context.'
                        ];
                        if ($entryId !== '') $lines[] = 'Entry id: ' . $entryId;

                        return $this->resultBuilder->prompt('Read a Memora/XRM entry.', implode("\n", $lines));
                }

                if ($name === 'memora_analyze_entry_context') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Use memora_get_entry_context for context analysis.',
                                'Summarize the entry, relevant relations, metadata, tags and recent activity.',
                                'Do not use write tools unless the user explicitly asks for a change.'
                        ];
                        if ($entryId !== '') $lines[] = 'Entry id: ' . $entryId;

                        return $this->resultBuilder->prompt('Analyze Memora/XRM context.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function searchEntries(array $arguments): array {
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 10, 10, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);
                $query = $this->normalizer->normalizeString($arguments['query'] ?? '');
                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['tags', 'access'], self::ENTRY_INCLUDES);

                $options = $this->createEntryLoadOptions($include);
                $options['limitcount'] = $query === '' ? $limit : min(self::MAX_SEARCH_FETCH, max($limit + $offset, $limit * 5));
                $options['limitoffset'] = $query === '' ? $offset : 0;

                $type = $this->normalizer->normalizeString($arguments['type'] ?? '');
                if ($type !== '') {
                        $options['type'] = $type;
                }

                $module = $this->normalizer->normalizeString($arguments['module'] ?? '');
                if ($module !== '') {
                        $options['module'] = $module;
                }

                $tags = $this->normalizer->normalizeStringList($arguments['tags'] ?? null);
                if ($tags !== []) {
                        $options['tag'] = $tags;
                }

                $entries = $this->entityDataService->getEntries($options);
                $totalBeforeSlice = count($entries);

                if ($query !== '') {
                        $entries = array_values(array_filter($entries, fn(array $entry): bool => $this->formatter->matchesQuery($entry, $query)));
                        $totalBeforeSlice = count($entries);
                        $entries = array_slice($entries, $offset, $limit);
                }

                $items = $this->formatter->formatSummaries($entries);

                return $this->resultBuilder->success(
                        self::TOOL_SEARCH,
                        count($items) > 0 ? 'Entries found.' : 'No matching entries found.',
                        [],
                        $items,
                        $this->resultBuilder->paging($offset, $limit, $totalBeforeSlice, count($items), $query !== '' && $totalBeforeSlice >= self::MAX_SEARCH_FETCH)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getEntry(array $arguments): array {
                $id = $arguments['id'] ?? null;
                if ($id === null || $id === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_ENTRY, 'missing_entry_id', 'Missing required parameter: id.');
                }

                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['data', 'metadata', 'tags', 'relations', 'access'], self::ENTRY_INCLUDES);
                $entry = $this->resolveEntry($id, $include);

                if ($entry === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_ENTRY, 'entry_not_found', 'Entry not found or not visible.');
                }

                $formatted = $this->formatter->formatEntry($entry, $include);
                $this->addExternalAspects($formatted, $entry['id'] ?? $id, $include);

                return $this->resultBuilder->success(
                        self::TOOL_GET_ENTRY,
                        'Entry loaded.',
                        ['entry' => $formatted]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getRelatedEntries(array $arguments): array {
                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_RELATED, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);
                $includeEntries = $this->normalizer->normalizeBool($arguments['include_entries'] ?? false);

                $relations = $this->relationService->getRelations($entryId);
                $total = count($relations);
                $relations = array_slice($relations, $offset, $limit);

                $data = [
                        'entry_id' => $entryId,
                        'relations' => $relations
                ];

                if ($includeEntries) {
                        $peerIds = $this->relationService->getRelationIds($entryId);
                        $peerIds = array_slice($peerIds, $offset, $limit);
                        $entries = [];
                        foreach ($peerIds as $peerId) {
                                $entry = $this->entityDataService->getEntry($peerId, $this->createEntryLoadOptions(['tags', 'access']));
                                if ($entry !== null) {
                                        $entries[] = $this->formatter->formatSummary($entry);
                                }
                        }
                        $data['entries'] = $entries;
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_RELATED,
                        $total > 0 ? 'Relations loaded.' : 'No relations found.',
                        $data,
                        [],
                        $this->resultBuilder->paging($offset, $limit, $total, count($relations))
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getEntryContext(array $arguments): array {
                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_CONTEXT, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 10, 10, self::MAX_LIMIT);
                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['data', 'metadata', 'tags', 'relations', 'access', 'activity'], self::ENTRY_INCLUDES);
                $entry = $this->entityDataService->getEntry($entryId, $this->createEntryLoadOptions($include));

                if ($entry === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_CONTEXT, 'entry_not_found', 'Entry not found or not visible.');
                }

                $formatted = $this->formatter->formatEntry($entry, $include);
                $this->addExternalAspects($formatted, $entryId, $include, $limit);

                return $this->resultBuilder->success(
                        self::TOOL_GET_CONTEXT,
                        'Entry context loaded.',
                        [
                                'entry' => $formatted
                        ]
                );
        }

        /**
         * @param array<int,string> $include
         * @return array<string,mixed>
         */
        private function createEntryLoadOptions(array $include): array {
                $options = [
                        'loadname' => true,
                        'loadtype' => true,
                        'loadaccess' => true
                ];

                if ($this->include($include, 'data')) $options['loaddata'] = true;
                if ($this->include($include, 'metadata')) $options['loadmetadata'] = true;
                if ($this->include($include, 'tags')) $options['loadtags'] = true;
                if ($this->include($include, 'relations')) {
                        $options['loadallocs'] = true;
                        $options['loadallocuuids'] = true;
                }

                return $options;
        }

        /**
         * @param array<int,string> $include
         */
        private function include(array $include, string $name): bool {
                return in_array('all', $include, true) || in_array($name, $include, true);
        }

        /**
         * @param array<int,string> $include
         * @return array<string,mixed>|null
         */
        private function resolveEntry(mixed $id, array $include): ?array {
                if (is_int($id) || ctype_digit((string)$id)) {
                        return $this->entityDataService->getEntry((int)$id, $this->createEntryLoadOptions($include));
                }

                $needle = trim((string)$id);
                if ($needle === '') {
                        return null;
                }

                $entries = $this->entityDataService->getEntries($this->createEntryLoadOptions($include) + [
                        'limitcount' => 100
                ]);

                foreach ($entries as $entry) {
                        if ((string)($entry['uuid'] ?? '') === $needle || (string)($entry['name'] ?? '') === $needle) {
                                return $entry;
                        }
                }

                return null;
        }

        /**
         * @param array<string,mixed> $formatted
         * @param array<int,string> $include
         */
        private function addExternalAspects(array &$formatted, int|string $entryId, array $include, int $limit = 10): void {
                if ($this->include($include, 'metadata') && !array_key_exists('metadata', $formatted)) {
                        $formatted['metadata'] = $this->metadataService->getMetadata($entryId);
                }

                if ($this->include($include, 'tags') && !array_key_exists('tags', $formatted)) {
                        $formatted['tags'] = $this->tagService->getEntryTags($entryId);
                }

                if ($this->include($include, 'relations') && !array_key_exists('relations', $formatted)) {
                        $formatted['relations'] = $this->relationService->getRelations($entryId);
                }

                if ($this->include($include, 'activity')) {
                        $formatted['activity'] = [
                                'logs' => $this->activityService->getLogs($entryId, ['limit' => $limit]),
                                'comments' => $this->activityService->getComments($entryId, ['limit' => $limit])
                        ];
                }

                if ($this->include($include, 'userdata')) {
                        $formatted['userdata'] = $this->userDataService->getUserData($entryId);
                }
        }
}
