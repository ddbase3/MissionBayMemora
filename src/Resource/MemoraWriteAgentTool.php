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
use ResourceFoundation\Api\IEntityDataService;

/**
 * Controlled Memora/XRM write tool.
 *
 * Every mutating operation is confirmation-aware. By default the first call
 * returns a proposed change plan. The caller must repeat the same call with
 * confirm=true after the user explicitly approved the plan.
 */
class MemoraWriteAgentTool extends AbstractAgentResource implements IAgentTool, IAgentPromptProvider {

        private const TOOL_CREATE_ENTRY = 'memora_create_entry';
        private const TOOL_UPDATE_ENTRY = 'memora_update_entry';

        private const ENTRY_INCLUDES = [
                'data',
                'metadata',
                'tags',
                'relations',
                'access',
                'all'
        ];

        public function __construct(
                private readonly IEntityDataService $entityDataService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentEntryFormatter $formatter,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorawriteagenttool';
        }

        public function getDescription(): string {
                return 'Provides confirmation-aware Memora/XRM entry creation and update tools.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Create Entry',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'write', 'confirmation'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_CREATE_ENTRY,
                                        'description' => 'Prepare or execute creation of a Memora/XRM entry. First call without confirm or with confirm=false to get a review plan. Execute only after user approval by repeating the call with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'type' => [
                                                                'type' => 'string',
                                                                'description' => 'Required Memora type alias.'
                                                        ],
                                                        'module' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional module alias used by Memora module resolution.'
                                                        ],
                                                        'name' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional display name. If omitted, data.name may be used by the backend.'
                                                        ],
                                                        'data' => [
                                                                'type' => 'object',
                                                                'description' => 'Typed entity data.'
                                                        ],
                                                        'metadata' => [
                                                                'type' => 'object',
                                                                'description' => 'Entry-wide metadata values.'
                                                        ],
                                                        'tags' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Tags assigned to the new entry.'
                                                        ],
                                                        'relations' => [
                                                                'type' => 'array',
                                                                'items' => ['description' => 'Related entry id'],
                                                                'description' => 'Related entry ids. Mapped to Memora allocs.'
                                                        ],
                                                        'useraccess' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'object'],
                                                                'description' => 'Optional direct user access rows.'
                                                        ],
                                                        'groupaccess' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'object'],
                                                                'description' => 'Optional group access rows.'
                                                        ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::ENTRY_INCLUDES
                                                                ],
                                                                'description' => 'Optional aspects to load after successful creation.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute the creation. Default false returns only a proposed change plan.'
                                                        ]
                                                ],
                                                'required' => ['type']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Update Entry',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'entry', 'update', 'write', 'confirmation'],
                                'priority' => 69,
                                'function' => [
                                        'name' => self::TOOL_UPDATE_ENTRY,
                                        'description' => 'Prepare or execute an update for one Memora/XRM entry. First call without confirm or with confirm=false to get a review plan. Execute only after user approval by repeating the call with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [
                                                                'description' => 'Required entry id to update.'
                                                        ],
                                                        'patch' => [
                                                                'type' => 'object',
                                                                'description' => 'Native Memora update patch. Supported keys include set, setname, setdata, unsetdata, setmetadata, unsetmetadata, addtags, removetags, replacetags, addallocs, removeallocs, replaceallocs, adduseraccess, removeuseraccess, replaceuseraccess, addgroupaccess, removegroupaccess, replacegroupaccess.'
                                                        ],
                                                        'set' => [
                                                                'type' => 'object',
                                                                'description' => 'Convenience base-entry field patch.'
                                                        ],
                                                        'setname' => [
                                                                'type' => 'string',
                                                                'description' => 'Convenience entry name update.'
                                                        ],
                                                        'setdata' => [
                                                                'type' => 'object',
                                                                'description' => 'Convenience typed-data fields to set.'
                                                        ],
                                                        'unsetdata' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Convenience typed-data fields to remove.'
                                                        ],
                                                        'setmetadata' => [
                                                                'type' => 'object',
                                                                'description' => 'Convenience metadata values to set.'
                                                        ],
                                                        'unsetmetadata' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Convenience metadata names to remove.'
                                                        ],
                                                        'addtags' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Tags to add.'
                                                        ],
                                                        'removetags' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Tags to remove.'
                                                        ],
                                                        'replacetags' => [
                                                                'type' => 'array',
                                                                'items' => ['type' => 'string'],
                                                                'description' => 'Complete replacement tag list.'
                                                        ],
                                                        'addrelations' => [
                                                                'type' => 'array',
                                                                'items' => ['description' => 'Related entry id'],
                                                                'description' => 'Relation ids to add. Mapped to addallocs.'
                                                        ],
                                                        'removerelations' => [
                                                                'type' => 'array',
                                                                'items' => ['description' => 'Related entry id'],
                                                                'description' => 'Relation ids to remove. Mapped to removeallocs.'
                                                        ],
                                                        'replacerelations' => [
                                                                'type' => 'array',
                                                                'items' => ['description' => 'Related entry id'],
                                                                'description' => 'Complete relation replacement. Mapped to replaceallocs.'
                                                        ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::ENTRY_INCLUDES
                                                                ],
                                                                'description' => 'Optional aspects to load after successful update.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute the update. Default false returns only a proposed change plan.'
                                                        ]
                                                ],
                                                'required' => ['entry_id']
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
                                self::TOOL_CREATE_ENTRY => $this->createEntry($arguments, $context),
                                self::TOOL_UPDATE_ENTRY => $this->updateEntry($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora write tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_create_entry',
                                'title' => 'Create a Memora Entry With Confirmation',
                                'description' => 'Guide the model to prepare a Memora entry creation, show the proposed payload to the user, and wait for confirmation before executing.',
                                'arguments' => [
                                        [ 'name' => 'type', 'description' => 'Optional known type alias.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_update_entry',
                                'title' => 'Update a Memora Entry With Confirmation',
                                'description' => 'Guide the model to prepare an update patch, show the proposed change to the user, and wait for confirmation before executing.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_create_entry') {
                        $type = $this->normalizer->normalizeString($arguments['type'] ?? '');
                        $lines = [
                                'Prepare a new Memora/XRM entry creation using memora_create_entry.',
                                'First call memora_get_structure or memora_describe_type if the type or expected fields are unclear.',
                                'Call memora_create_entry with confirm=false first and present the returned plan to the user.',
                                'Do not call memora_create_entry with confirm=true until the user explicitly approves the proposed payload.'
                        ];

                        if ($type !== '') {
                                $lines[] = 'Preferred type: ' . $type;
                        }

                        return $this->resultBuilder->prompt('Create a Memora entry with user confirmation.', implode("\n", $lines));
                }

                if ($name === 'memora_update_entry') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Prepare a Memora/XRM entry update using memora_update_entry.',
                                'Load the current entry first when needed so the user can compare old and new values.',
                                'Call memora_update_entry with confirm=false first and present the returned plan to the user.',
                                'Do not call memora_update_entry with confirm=true until the user explicitly approves the proposed patch.'
                        ];

                        if ($entryId !== '') {
                                $lines[] = 'Preferred entry id: ' . $entryId;
                        }

                        return $this->resultBuilder->prompt('Update a Memora entry with user confirmation.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createEntry(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ENTRY_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_ENTRY, 'capability_denied', 'Entry creation is not allowed for this tool configuration.');
                }

                $entry = $this->buildCreatePayload($arguments);
                if ($entry === []) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_ENTRY, 'invalid_payload', 'Creation requires at least a non-empty type.');
                }

                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_CREATE_ENTRY,
                                'Entry creation requires user confirmation before execution.',
                                [
                                        'operation' => 'create_entry',
                                        'entry' => $entry
                                ],
                                $this->normalizeConfirmationArguments($arguments, $entry)
                        );
                }

                $id = $this->entityDataService->createEntry($entry);
                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['data', 'metadata', 'tags', 'relations', 'access'], self::ENTRY_INCLUDES);
                $entryAfterCreate = $this->entityDataService->getEntry($id, $this->createEntryLoadOptions($include));

                return $this->resultBuilder->success(
                        self::TOOL_CREATE_ENTRY,
                        'Entry created.',
                        [
                                'id' => $id,
                                'entry' => $entryAfterCreate !== null ? $this->formatter->formatEntry($entryAfterCreate, $include) : null
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function updateEntry(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ENTRY_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ENTRY, 'capability_denied', 'Entry update is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ENTRY, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $patch = $this->buildUpdatePatch($arguments);
                if ($patch === []) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ENTRY, 'empty_patch', 'The update did not contain any supported patch operation.');
                }

                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_UPDATE_ENTRY,
                                'Entry update requires user confirmation before execution.',
                                [
                                        'operation' => 'update_entry',
                                        'entry_id' => $entryId,
                                        'patch' => $patch
                                ],
                                $this->normalizeConfirmationArguments($arguments, $patch)
                        );
                }

                $id = $this->entityDataService->updateEntry($entryId, $patch);
                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['data', 'metadata', 'tags', 'relations', 'access'], self::ENTRY_INCLUDES);
                $entryAfterUpdate = $this->entityDataService->getEntry($id, $this->createEntryLoadOptions($include));

                return $this->resultBuilder->success(
                        self::TOOL_UPDATE_ENTRY,
                        'Entry updated.',
                        [
                                'id' => $id,
                                'entry' => $entryAfterUpdate !== null ? $this->formatter->formatEntry($entryAfterUpdate, $include) : null
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildCreatePayload(array $arguments): array {
                $type = $this->normalizer->normalizeString($arguments['type'] ?? '');
                if ($type === '') {
                        return [];
                }

                $entry = [
                        'type' => $type
                ];

                $module = $this->normalizer->normalizeString($arguments['module'] ?? '');
                if ($module !== '') {
                        $entry['module'] = $module;
                }

                $name = $this->normalizer->normalizeString($arguments['name'] ?? '');
                if ($name !== '') {
                        $entry['name'] = $name;
                }

                foreach (['data', 'metadata', 'useraccess', 'groupaccess'] as $key) {
                        if (isset($arguments[$key]) && is_array($arguments[$key]) && $arguments[$key] !== []) {
                                $entry[$key] = $arguments[$key];
                        }
                }

                $tags = $this->normalizer->normalizeStringList($arguments['tags'] ?? null);
                if ($tags !== []) {
                        $entry['tags'] = $tags;
                }

                $relations = $this->normalizer->normalizeIdList($arguments['relations'] ?? null);
                if ($relations !== []) {
                        $entry['allocs'] = $relations;
                }

                return $entry;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildUpdatePatch(array $arguments): array {
                $patch = $this->normalizer->normalizeArray($arguments['patch'] ?? []);

                foreach (['set', 'setdata', 'setmetadata'] as $key) {
                        if (isset($arguments[$key]) && is_array($arguments[$key]) && $arguments[$key] !== []) {
                                $patch[$key] = $arguments[$key];
                        }
                }

                $setName = $this->normalizer->normalizeString($arguments['setname'] ?? '');
                if ($setName !== '') {
                        $patch['setname'] = $setName;
                }

                foreach (['unsetdata', 'unsetmetadata', 'addtags', 'removetags', 'replacetags'] as $key) {
                        if (array_key_exists($key, $arguments)) {
                                $values = $this->normalizer->normalizeStringList($arguments[$key] ?? null);
                                if ($values !== [] || str_starts_with($key, 'replace')) {
                                        $patch[$key] = $values;
                                }
                        }
                }

                $relationMap = [
                        'addrelations' => 'addallocs',
                        'removerelations' => 'removeallocs',
                        'replacerelations' => 'replaceallocs'
                ];

                foreach ($relationMap as $source => $target) {
                        if (array_key_exists($source, $arguments)) {
                                $values = $this->normalizer->normalizeIdList($arguments[$source] ?? null);
                                if ($values !== [] || $source === 'replacerelations') {
                                        $patch[$target] = $values;
                                }
                        }
                }

                return $patch;
        }

        /**
         * @param array<string,mixed> $arguments
         * @param array<string,mixed> $payload
         * @return array<string,mixed>
         */
        private function normalizeConfirmationArguments(array $arguments, array $payload): array {
                $out = $arguments;
                $out['confirm'] = true;

                if (isset($out['patch']) && is_array($out['patch'])) {
                        $out['patch'] = $payload;
                }

                return $out;
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
}
