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
use MissionBayMemora\Service\MemoraAgentAccessPolicy;
use MissionBayMemora\Service\MemoraAgentEntryFormatter;
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use MissionBayMemora\Service\MemoraXrmSemanticModel;
use ResourceFoundation\Api\IEntityDataService;
use ResourceFoundation\Api\IEntityRelationService;

/**
 * Agent-facing Memora knowledge graph tool.
 *
 * Memora allocs/relations connect entries into a knowledge graph. This tool
 * makes those graph links explicit and provides confirmation-aware mutation
 * functions for adding, removing, or replacing relations.
 */
class MemoraGraphAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_GRAPH = 'memora_get_knowledge_graph';
        private const TOOL_CONNECT = 'memora_connect_entries';
        private const TOOL_DISCONNECT = 'memora_disconnect_entries';
        private const TOOL_REPLACE = 'memora_replace_entry_relations';
        private const TOOL_PLAN_REQUIRED = 'memora_plan_required_relations';

        private const MAX_DEPTH = 3;
        private const MAX_NODES = 75;
        private const MAX_RELATIONS = 200;

        public function __construct(
                private readonly IEntityDataService $entityDataService,
                private readonly IEntityRelationService $relationService,
                private readonly MemoraXrmSemanticModel $semanticModel,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentEntryFormatter $formatter,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memoragraphagenttool';
        }

        public function getDescription(): string {
                return 'Reads and changes Memora alloc relations as an agent-facing XRM knowledge graph.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Knowledge Graph',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'graph', 'relations', 'allocs', 'readonly'],
                                'priority' => 82,
                                'function' => [
                                        'name' => self::TOOL_GET_GRAPH,
                                        'description' => 'Load an entry-centered Memora knowledge graph. Relations/allocs are the main way XRM knowledge spans across entries.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Root entry id.' ],
                                                        'depth' => [ 'type' => 'integer', 'description' => 'Graph depth. Default 1, maximum 3.' ],
                                                        'include_entries' => [ 'type' => 'boolean', 'description' => 'Load compact summaries for graph nodes. Default true.' ],
                                                        'limit' => [ 'type' => 'integer', 'description' => 'Maximum graph nodes. Default 25, maximum 75.' ]
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Connect Entries',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'graph', 'relations', 'allocs', 'write', 'confirmation'],
                                'priority' => 64,
                                'function' => [
                                        'name' => self::TOOL_CONNECT,
                                        'description' => 'Prepare or execute adding alloc relations from one entry to one or more peer entries. Use this to connect knowledge, for example a photoshooting to its dancer and location. Requires confirmation by default.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Source/root entry id.' ],
                                                        'peer_ids' => [ 'type' => 'array', 'items' => [ 'description' => 'Peer entry id' ] ],
                                                        'relation_meaning' => [ 'type' => 'string', 'description' => 'Optional human explanation of why these entries should be connected.' ],
                                                        'confirm' => [ 'type' => 'boolean' ]
                                                ],
                                                'required' => ['entry_id', 'peer_ids']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Disconnect Entries',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'graph', 'relations', 'allocs', 'write', 'confirmation'],
                                'priority' => 63,
                                'function' => [
                                        'name' => self::TOOL_DISCONNECT,
                                        'description' => 'Prepare or execute removing alloc relations from one entry to one or more peer entries. Requires confirmation by default.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Source/root entry id.' ],
                                                        'peer_ids' => [ 'type' => 'array', 'items' => [ 'description' => 'Peer entry id' ] ],
                                                        'relation_meaning' => [ 'type' => 'string' ],
                                                        'confirm' => [ 'type' => 'boolean' ]
                                                ],
                                                'required' => ['entry_id', 'peer_ids']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Replace Entry Relations',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'graph', 'relations', 'allocs', 'write', 'confirmation'],
                                'priority' => 62,
                                'function' => [
                                        'name' => self::TOOL_REPLACE,
                                        'description' => 'Prepare or execute replacing all alloc relations of one entry. This is stronger than connect/disconnect and requires confirmation; configure destructive allowance for external use if needed.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Entry id.' ],
                                                        'peer_ids' => [ 'type' => 'array', 'items' => [ 'description' => 'Complete new peer id list' ] ],
                                                        'relation_meaning' => [ 'type' => 'string' ],
                                                        'confirm' => [ 'type' => 'boolean' ],
                                                        'allow_destructive' => [ 'type' => 'boolean', 'description' => 'Must be true when tool configuration requires destructive acknowledgement.' ]
                                                ],
                                                'required' => ['entry_id', 'peer_ids']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Plan Required Relations',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'graph', 'relations', 'semantic', 'readonly'],
                                'priority' => 81,
                                'function' => [
                                        'name' => self::TOOL_PLAN_REQUIRED,
                                        'description' => 'Explain which relations are normally expected for a semantic kind. Example: a dance photography shooting usually connects to a dancer/contact, a location/address and optionally an event/date.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'kind' => [ 'type' => 'string', 'description' => 'Memora module/domain profile key, e.g. dancephotographyshooting.' ],
                                                        'entry_id' => [ 'description' => 'Optional entry id to inspect existing relations.' ],
                                                        'include_graph' => [ 'type' => 'boolean' ]
                                                ]
                                        ]
                                ]
                        ]
                ];
        }

        public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
                try {
                        return match ($name) {
                                self::TOOL_GET_GRAPH => $this->getGraph($arguments),
                                self::TOOL_CONNECT => $this->connectEntries($arguments, $context),
                                self::TOOL_DISCONNECT => $this->disconnectEntries($arguments, $context),
                                self::TOOL_REPLACE => $this->replaceEntryRelations($arguments, $context),
                                self::TOOL_PLAN_REQUIRED => $this->planRequiredRelations($arguments),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error($name, 'tool_error', 'The Memora graph tool failed to process the request.', [], [
                                'exception' => $e::class,
                                'message' => $e->getMessage()
                        ]);
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [[
                        'uriTemplate' => 'memora://entry/{entry_id}/graph',
                        'name' => 'memora-entry-graph-template',
                        'title' => 'Memora Entry Knowledge Graph',
                        'description' => 'Reads the alloc relation graph around one entry.',
                        'mimeType' => 'application/json'
                ]];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                $prefix = 'memora://entry/';
                $suffix = '/graph';
                if (!str_starts_with($uri, $prefix) || !str_ends_with($uri, $suffix)) {
                        return null;
                }

                $entryId = rawurldecode(substr($uri, strlen($prefix), -strlen($suffix)));
                return $this->resultBuilder->resource($uri, $this->getGraph([
                        'entry_id' => $entryId,
                        'depth' => 1,
                        'include_entries' => true,
                        'limit' => 25
                ]));
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_inspect_knowledge_graph',
                                'title' => 'Inspect Memora Knowledge Graph',
                                'description' => 'Guide the model to inspect relations/allocs around an entry.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Entry id.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_connect_knowledge',
                                'title' => 'Connect Memora Knowledge',
                                'description' => 'Guide the model to safely propose relation changes with confirmation.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Entry id.', 'required' => false ]]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);
                $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');

                if ($name === 'memora_inspect_knowledge_graph') {
                        $lines = [
                                'Use memora_get_knowledge_graph to inspect the relation/alloc graph around an entry.',
                                'Treat alloc relations as XRM knowledge links, not just UI associations.',
                                'For domain entries, compare existing relations with memora_plan_required_relations.'
                        ];
                        if ($entryId !== '') $lines[] = 'Entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Inspect Memora knowledge graph.', implode("\n", $lines));
                }

                if ($name === 'memora_connect_knowledge') {
                        $lines = [
                                'Use memora_connect_entries or memora_disconnect_entries to propose relation changes.',
                                'Always present the proposed graph change to the user first.',
                                'Execute only after explicit confirmation with confirm=true.'
                        ];
                        if ($entryId !== '') $lines[] = 'Entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Connect Memora knowledge with confirmation.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getGraph(array $arguments): array {
                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_GRAPH, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $depth = max(1, min(self::MAX_DEPTH, (int)($arguments['depth'] ?? 1)));
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, self::MAX_NODES);
                $includeEntries = $this->normalizer->normalizeBool($arguments['include_entries'] ?? true, true);

                $graph = $this->buildGraph($entryId, $depth, $limit, $includeEntries);

                return $this->resultBuilder->success(
                        self::TOOL_GET_GRAPH,
                        'Knowledge graph loaded.',
                        $graph,
                        [],
                        $this->resultBuilder->paging(0, $limit, count($graph['nodes']), count($graph['nodes']), $graph['truncated'] ?? false)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function connectEntries(array $arguments, IAgentContext $context): array {
                return $this->mutateRelations(self::TOOL_CONNECT, 'connect_entries', $arguments, $context, function (int|string $entryId, array $peerIds): void {
                        $this->relationService->addRelations($entryId, $peerIds);
                });
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function disconnectEntries(array $arguments, IAgentContext $context): array {
                return $this->mutateRelations(self::TOOL_DISCONNECT, 'disconnect_entries', $arguments, $context, function (int|string $entryId, array $peerIds): void {
                        $this->relationService->removeRelations($entryId, $peerIds);
                });
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function replaceEntryRelations(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DESTRUCTIVE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REPLACE, 'destructive_not_allowed', 'Replacing all relations requires destructive allowance in the tool configuration.');
                }

                return $this->mutateRelations(self::TOOL_REPLACE, 'replace_entry_relations', $arguments, $context, function (int|string $entryId, array $peerIds): void {
                        $this->relationService->replaceRelations($entryId, $peerIds);
                });
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function planRequiredRelations(array $arguments): array {
                $kind = $this->normalizer->normalizeToken((string)($arguments['kind'] ?? ''));
                $entryId = $arguments['entry_id'] ?? null;

                if ($kind === '' && $entryId !== null && $entryId !== '') {
                        $entry = $this->entityDataService->getEntry($entryId, $this->entryOptions());
                        $kind = $this->inferKindFromEntry($entry ?? []);
                }

                $patterns = $this->semanticModel->getRelationPatterns($kind !== '' ? $kind : null);
                $data = [
                        'kind' => $kind !== '' ? $kind : null,
                        'expected_relations' => array_values(array_map(static fn(array $pattern): array => $pattern['relation'] ?? $pattern, $patterns))
                ];

                if ($entryId !== null && $entryId !== '') {
                        $data['entry_id'] = $entryId;
                        $data['existing_relation_ids'] = $this->relationService->getRelationIds($entryId);
                        if ($this->normalizer->normalizeBool($arguments['include_graph'] ?? false, false)) {
                                $data['graph'] = $this->buildGraph($entryId, 1, 25, true);
                        }
                }

                return $this->resultBuilder->success(
                        self::TOOL_PLAN_REQUIRED,
                        'Required relation plan prepared.',
                        $data
                );
        }

        /**
         * @param callable(int|string,array<int,int|string>):void $callback
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function mutateRelations(string $tool, string $operation, array $arguments, IAgentContext $context, callable $callback): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_GRAPH_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error($tool, 'capability_denied', 'Graph relation changes are not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error($tool, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $peerIds = $this->normalizer->normalizeIdList($arguments['peer_ids'] ?? null);
                if ($peerIds === []) {
                        return $this->resultBuilder->error($tool, 'missing_peer_ids', 'Missing required parameter: peer_ids.');
                }

                $plan = [
                        'operation' => $operation,
                        'entry_id' => $entryId,
                        'peer_ids' => $peerIds,
                        'relation_meaning' => $this->normalizer->normalizeString($arguments['relation_meaning'] ?? ''),
                        'note' => 'Relations/allocs connect Memora entries into the XRM knowledge graph.'
                ];

                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                $tool,
                                'Knowledge graph relation change requires user confirmation before execution.',
                                $plan,
                                $plan + ['confirm' => true]
                        );
                }

                $callback($entryId, $peerIds);

                return $this->resultBuilder->success(
                        $tool,
                        'Knowledge graph relation change executed.',
                        $plan + [
                                'relation_ids_after' => $this->relationService->getRelationIds($entryId)
                        ]
                );
        }

        /**
         * @return array<string,mixed>
         */
        private function buildGraph(int|string $rootId, int $depth, int $limit, bool $includeEntries): array {
                $nodes = [];
                $edges = [];
                $seen = [];
                $queue = [[ 'id' => $rootId, 'depth' => 0 ]];
                $truncated = false;

                while ($queue !== []) {
                        $current = array_shift($queue);
                        $id = $current['id'];
                        $currentDepth = (int)$current['depth'];
                        $key = (string)$id;

                        if (isset($seen[$key])) {
                                continue;
                        }
                        $seen[$key] = true;

                        if (count($nodes) >= $limit) {
                                $truncated = true;
                                break;
                        }

                        $node = [
                                'id' => $id,
                                'depth' => $currentDepth
                        ];

                        if ($includeEntries) {
                                $entry = $this->entityDataService->getEntry($id, $this->entryOptions());
                                if ($entry !== null) {
                                        $node['entry'] = $this->formatter->formatSummary($entry);
                                        $node['semantic_kind'] = $this->inferKindFromEntry($entry);
                                }
                        }

                        $nodes[$key] = $node;

                        if ($currentDepth >= $depth) {
                                continue;
                        }

                        $peerIds = $this->relationService->getRelationIds($id);
                        foreach ($peerIds as $peerId) {
                                if (count($edges) >= self::MAX_RELATIONS) {
                                        $truncated = true;
                                        break 2;
                                }

                                $edges[] = [
                                        'source' => $id,
                                        'target' => $peerId,
                                        'kind' => 'alloc',
                                        'meaning' => 'Memora relation / knowledge graph connection'
                                ];

                                if (!isset($seen[(string)$peerId])) {
                                        $queue[] = [ 'id' => $peerId, 'depth' => $currentDepth + 1 ];
                                }
                        }
                }

                return [
                        'root_id' => $rootId,
                        'depth' => $depth,
                        'nodes' => array_values($nodes),
                        'edges' => $edges,
                        'truncated' => $truncated,
                        'graph_guidance' => [
                                'Alloc relations are the main Memora mechanism for spanning knowledge across entries.',
                                'Use tags/modules to understand what a node is; use graph edges to understand why entries belong together.'
                        ]
                ];
        }

        /**
         * @return array<string,mixed>
         */
        private function entryOptions(): array {
                return [
                        'loadname' => true,
                        'loadtype' => true,
                        'loadtags' => true,
                        'loadaccess' => true
                ];
        }

        /**
         * @param array<string,mixed> $entry
         */
        private function inferKindFromEntry(array $entry): ?string {
                $module = $this->normalizer->normalizeToken((string)($entry['module'] ?? ''));
                $type = $this->normalizer->normalizeToken((string)($entry['type'] ?? ($entry['type_alias'] ?? '')));
                $tags = $this->normalizer->normalizeStringList($entry['tags'] ?? null);

                $explanation = $this->semanticModel->explain([
                        'module' => $module,
                        'type' => $type,
                        'tags' => $tags
                ]);

                return $explanation['matched_domain_profile'] ?? ($explanation['matched_preset'] ?? null);
        }
}
