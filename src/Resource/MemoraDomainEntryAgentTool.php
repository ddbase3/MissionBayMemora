<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Resource;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentPromptProvider;
use MissionBay\Api\IAgentTool;
use MissionBay\Resource\AbstractAgentResource;
use MissionBayMemora\Service\MemoraAgentAccessPolicy;
use MissionBayMemora\Service\MemoraAgentEntryFormatter;
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use MissionBayMemora\Service\MemoraXrmSemanticModel;
use ResourceFoundation\Api\IEntityDataService;

/**
 * Practical domain-entry tools built on top of the generic Memora entry API.
 *
 * This class deliberately remains generic: it exposes Memora-derived domain profiles such as
 * crmproduct or dancephotographyshooting without hard-coding project-only
 * business services. The actual persistence still goes through IEntityDataService.
 */
class MemoraDomainEntryAgentTool extends AbstractAgentResource implements IAgentTool, IAgentPromptProvider {

        private const TOOL_FIND = 'memora_find_domain_entries';
        private const TOOL_CREATE = 'memora_create_domain_entry';
        private const TOOL_UPDATE_SEMANTICS = 'memora_update_domain_entry_semantics';

        private const MAX_LIMIT = 50;
        private const MAX_FETCH = 250;

        public function __construct(
                private readonly IEntityDataService $entityDataService,
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
                return 'memoradomainentryagenttool';
        }

        public function getDescription(): string {
                return 'Provides practical semantic domain-entry tools for Memora/XRM, based on type, module, tags and relations.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Find Domain Entries',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'domain', 'entry', 'semantic', 'readonly'],
                                'priority' => 83,
                                'function' => [
                                        'name' => self::TOOL_FIND,
                                        'description' => 'Find entries by semantic domain kind. Examples: crmproduct, crmproject, dancephotographydancer, dancephotographyshooting. This maps the kind to type/module/tags before searching.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'kind' => [ 'type' => 'string', 'description' => 'Memora module/domain profile key or user phrase.' ],
                                                        'query' => [ 'type' => 'string', 'description' => 'Optional text filter.' ],
                                                        'scope' => [ 'type' => 'string' ],
                                                        'module' => [ 'type' => 'string' ],
                                                        'type' => [ 'type' => 'string' ],
                                                        'tags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'limit' => [ 'type' => 'integer' ],
                                                        'offset' => [ 'type' => 'integer' ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Create Domain Entry',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'domain', 'entry', 'write', 'semantic', 'confirmation'],
                                'priority' => 61,
                                'function' => [
                                        'name' => self::TOOL_CREATE,
                                        'description' => 'Prepare or execute creating a semantic domain entry. It first resolves type/module/tags from intent/kind, includes relations/allocs, and requires confirmation by default.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'intent' => [ 'type' => 'string' ],
                                                        'kind' => [ 'type' => 'string', 'description' => 'Memora module/domain profile key, e.g. crmproduct or dancephotographyshooting.' ],
                                                        'scope' => [ 'type' => 'string' ],
                                                        'module' => [ 'type' => 'string' ],
                                                        'type' => [ 'type' => 'string' ],
                                                        'name' => [ 'type' => 'string' ],
                                                        'data' => [ 'type' => 'object' ],
                                                        'metadata' => [ 'type' => 'object' ],
                                                        'tags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'relations' => [ 'type' => 'array', 'items' => [ 'description' => 'Related entry id' ] ],
                                                        'confirm' => [ 'type' => 'boolean' ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Update Domain Entry Semantics',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'domain', 'entry', 'semantic', 'tags', 'relations', 'write', 'confirmation'],
                                'priority' => 60,
                                'function' => [
                                        'name' => self::TOOL_UPDATE_SEMANTICS,
                                        'description' => 'Prepare or execute semantic changes on an entry: name, metadata, tags and relations/allocs. Use this when the meaning/classification/connections of an entry should change. Requires confirmation by default.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Entry id.' ],
                                                        'setname' => [ 'type' => 'string' ],
                                                        'setmetadata' => [ 'type' => 'object' ],
                                                        'addtags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'removetags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'replacetags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'addrelations' => [ 'type' => 'array', 'items' => [ 'description' => 'Related entry id' ] ],
                                                        'removerelations' => [ 'type' => 'array', 'items' => [ 'description' => 'Related entry id' ] ],
                                                        'replacerelations' => [ 'type' => 'array', 'items' => [ 'description' => 'Related entry id' ] ],
                                                        'semantic_reason' => [ 'type' => 'string' ],
                                                        'confirm' => [ 'type' => 'boolean' ]
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
                                self::TOOL_FIND => $this->findDomainEntries($arguments),
                                self::TOOL_CREATE => $this->createDomainEntry($arguments, $context),
                                self::TOOL_UPDATE_SEMANTICS => $this->updateDomainEntrySemantics($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error($name, 'tool_error', 'The Memora domain-entry tool failed to process the request.', [], [
                                'exception' => $e::class,
                                'message' => $e->getMessage()
                        ]);
                }
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_create_domain_entry',
                                'title' => 'Create a Memora Domain Entry',
                                'description' => 'Guide the model to create a semantic Memora entry with confirmation.',
                                'arguments' => [[ 'name' => 'kind', 'description' => 'Memora module/domain profile key.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_update_domain_semantics',
                                'title' => 'Update Memora Domain Semantics',
                                'description' => 'Guide the model to update tags and relations safely.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Entry id.', 'required' => false ]]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_create_domain_entry') {
                        $kind = $this->normalizer->normalizeString($arguments['kind'] ?? '');
                        $lines = [
                                'Use memora_get_xrm_semantic_model or memora_explain_xrm_semantics first when the domain meaning is unclear.',
                                'Use memora_plan_domain_entry to prepare type/module/tags from Memora structure and propose alloc relations.',
                                'For actual creation, call memora_create_domain_entry with confirm=false first, show the plan, then wait for explicit user approval before confirm=true.',
                                'For photoshootings, ask for or connect dancer/contact and location/address when available.'
                        ];
                        if ($kind !== '') $lines[] = 'Preferred semantic kind: ' . $kind;
                        return $this->resultBuilder->prompt('Create a Memora domain entry.', implode("\n", $lines));
                }

                if ($name === 'memora_update_domain_semantics') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Use memora_get_entry and memora_get_knowledge_graph first so the user can see current meaning and connections.',
                                'Use memora_update_domain_entry_semantics with confirm=false to propose tag/relation changes.',
                                'Execute only after explicit user approval with confirm=true.'
                        ];
                        if ($entryId !== '') $lines[] = 'Entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Update Memora semantic entry state.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function findDomainEntries(array $arguments): array {
                $plan = $this->semanticModel->planEntry($arguments);
                $entry = $plan['entry'] ?? [];

                $options = [
                        'loadname' => true,
                        'loadtype' => true,
                        'loadtags' => true,
                        'loadaccess' => true,
                        'limitcount' => self::MAX_FETCH
                ];

                if (!empty($entry['type'])) {
                        $options['type'] = $entry['type'];
                }
                if (!empty($entry['module'])) {
                        $options['module'] = $entry['module'];
                }
                if (!empty($entry['tags'])) {
                        $options['tag'] = $entry['tags'];
                }

                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 10, 10, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);
                $query = $this->normalizer->normalizeString($arguments['query'] ?? '');
                $entries = $this->entityDataService->getEntries($options);

                if ($query !== '') {
                        $entries = array_values(array_filter($entries, fn(array $entry): bool => $this->formatter->matchesQuery($entry, $query)));
                }

                $total = count($entries);
                $entries = array_slice($entries, $offset, $limit);

                return $this->resultBuilder->success(
                        self::TOOL_FIND,
                        count($entries) > 0 ? 'Domain entries found.' : 'No matching domain entries found.',
                        [
                                'semantic_plan' => $plan,
                                'search_options' => $options
                        ],
                        $this->formatter->formatSummaries($entries),
                        $this->resultBuilder->paging($offset, $limit, $total, count($entries), $total >= self::MAX_FETCH)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createDomainEntry(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DOMAIN_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE, 'capability_denied', 'Domain entry creation is not allowed for this tool configuration.');
                }

                $plan = $this->semanticModel->planEntry($arguments);
                $entry = $plan['entry'] ?? [];
                if (!is_array($entry) || empty($entry['type'])) {
                        return $this->resultBuilder->error(self::TOOL_CREATE, 'invalid_domain_plan', 'Domain entry creation requires at least a resolved type.', [], ['plan' => $plan]);
                }

                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_CREATE,
                                'Domain entry creation requires user confirmation before execution.',
                                [
                                        'operation' => 'create_domain_entry',
                                        'plan' => $plan
                                ],
                                $arguments + ['confirm' => true]
                        );
                }

                $id = $this->entityDataService->createEntry($entry);
                $created = $this->entityDataService->getEntry($id, [
                        'loadname' => true,
                        'loadtype' => true,
                        'loaddata' => true,
                        'loadmetadata' => true,
                        'loadtags' => true,
                        'loadallocs' => true,
                        'loadaccess' => true
                ]);

                return $this->resultBuilder->success(
                        self::TOOL_CREATE,
                        'Domain entry created.',
                        [
                                'id' => $id,
                                'semantic_plan' => $plan,
                                'entry' => $created !== null ? $this->formatter->formatEntry($created, ['data', 'metadata', 'tags', 'relations', 'access']) : null
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function updateDomainEntrySemantics(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DOMAIN_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_SEMANTICS, 'capability_denied', 'Domain entry semantic updates are not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_SEMANTICS, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $patch = $this->buildSemanticPatch($arguments);
                if ($patch === []) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_SEMANTICS, 'empty_patch', 'No semantic patch operation was provided.');
                }

                $plan = [
                        'operation' => 'update_domain_entry_semantics',
                        'entry_id' => $entryId,
                        'semantic_reason' => $this->normalizer->normalizeString($arguments['semantic_reason'] ?? ''),
                        'patch' => $patch,
                        'note' => 'Tags classify/state an entry. Relations/allocs connect the entry into the XRM knowledge graph.'
                ];

                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_UPDATE_SEMANTICS,
                                'Domain entry semantic update requires user confirmation before execution.',
                                $plan,
                                $arguments + ['confirm' => true]
                        );
                }

                $id = $this->entityDataService->updateEntry($entryId, $patch);
                $updated = $this->entityDataService->getEntry($id, [
                        'loadname' => true,
                        'loadtype' => true,
                        'loadmetadata' => true,
                        'loadtags' => true,
                        'loadallocs' => true,
                        'loadaccess' => true
                ]);

                return $this->resultBuilder->success(
                        self::TOOL_UPDATE_SEMANTICS,
                        'Domain entry semantics updated.',
                        [
                                'id' => $id,
                                'patch' => $patch,
                                'entry' => $updated !== null ? $this->formatter->formatEntry($updated, ['metadata', 'tags', 'relations', 'access']) : null
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildSemanticPatch(array $arguments): array {
                $patch = [];

                $name = $this->normalizer->normalizeString($arguments['setname'] ?? '');
                if ($name !== '') {
                        $patch['setname'] = $name;
                }

                if (isset($arguments['setmetadata']) && is_array($arguments['setmetadata']) && $arguments['setmetadata'] !== []) {
                        $patch['setmetadata'] = $arguments['setmetadata'];
                }

                foreach ([
                        'addtags' => 'addtags',
                        'removetags' => 'removetags',
                        'replacetags' => 'replacetags'
                ] as $argumentKey => $patchKey) {
                        $values = $this->normalizer->normalizeStringList($arguments[$argumentKey] ?? null);
                        if ($values !== []) {
                                $patch[$patchKey] = $values;
                        }
                }

                foreach ([
                        'addrelations' => 'addallocs',
                        'removerelations' => 'removeallocs',
                        'replacerelations' => 'replaceallocs'
                ] as $argumentKey => $patchKey) {
                        $values = $this->normalizer->normalizeIdList($arguments[$argumentKey] ?? null);
                        if ($values !== []) {
                                $patch[$patchKey] = $values;
                        }
                }

                return $patch;
        }
}
