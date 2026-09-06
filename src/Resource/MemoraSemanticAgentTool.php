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
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use MissionBayMemora\Service\MemoraXrmSemanticModel;
use ResourceFoundation\Api\IEntityStructureService;
use ResourceFoundation\Api\IEntityTagService;

/**
 * Agent-facing semantic XRM model and structure management tool.
 */
class MemoraSemanticAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_MODEL = 'memora_get_xrm_semantic_model';
        private const TOOL_EXPLAIN = 'memora_explain_xrm_semantics';
        private const TOOL_PLAN_ENTRY = 'memora_plan_domain_entry';
        private const TOOL_DEFINE_STRUCTURE = 'memora_define_xrm_structure';

        private const MODEL_INCLUDES = [
                'types',
                'scopes',
                'modules',
                'tags',
                'domain_profiles',
                'module_tags',
                'scope_modules',
                'relations',
                'all'
        ];

        private const STRUCTURE_ACTIONS = [
                'create_scope',
                'create_module',
                'update_module',
                'assign_module_to_scope',
                'remove_module_from_scope',
                'describe_tag',
                'assign_tag_to_scope',
                'remove_tag_from_scope',
                'assign_tag_to_module',
                'remove_tag_from_module'
        ];

        public function __construct(
                private readonly IEntityStructureService $structureService,
                private readonly IEntityTagService $tagService,
                private readonly MemoraXrmSemanticModel $semanticModel,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorasemanticagenttool';
        }

        public function getDescription(): string {
                return 'Exposes Memora/XRM semantics: scopes, modules, types, tags, domain profiles and structure changes.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get XRM Semantic Model',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'semantic', 'structure', 'readonly', 'discovery'],
                                'priority' => 78,
                                'function' => [
                                        'name' => self::TOOL_GET_MODEL,
                                        'description' => 'Return an agent-friendly explanation of Memora/XRM semantics. Use this before creating or changing domain entries. It explains scopes, modules, technical types, tags, Memora-derived domain profiles, module tags, scope-module assignments and relation guidance.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'scope' => [ 'type' => 'string', 'description' => 'Optional scope such as crm or dancephotography.' ],
                                                        'module' => [ 'type' => 'string', 'description' => 'Optional module such as crmproduct or dancephotographyshooting.' ],
                                                        'query' => [ 'type' => 'string', 'description' => 'Optional local filter.' ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [ 'type' => 'string', 'enum' => self::MODEL_INCLUDES ],
                                                                'description' => 'Sections to include. Default: all.'
                                                        ],
                                                        'limit' => [ 'type' => 'integer', 'description' => 'Maximum rows per section. Default 50.' ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Explain XRM Semantics',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'semantic', 'explain', 'readonly'],
                                'priority' => 77,
                                'function' => [
                                        'name' => self::TOOL_EXPLAIN,
                                        'description' => 'Explain what a user intent, module, type or tag combination means in Memora/XRM. Use it to map phrases like "software module" or "dance photo model" to type/module/tags.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'input' => [ 'type' => 'string', 'description' => 'User phrase or intent to interpret.' ],
                                                        'scope' => [ 'type' => 'string' ],
                                                        'module' => [ 'type' => 'string' ],
                                                        'type' => [ 'type' => 'string' ],
                                                        'tags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Plan Domain Entry',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'semantic', 'entry', 'planning', 'readonly'],
                                'priority' => 76,
                                'function' => [
                                        'name' => self::TOOL_PLAN_ENTRY,
                                        'description' => 'Prepare an entry payload from semantic intent without writing. This helps the chatbot propose correct type/module/tags and alloc relations before using write tools.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'intent' => [ 'type' => 'string', 'description' => 'User intent, e.g. "create a dance photoshooting".' ],
                                                        'kind' => [ 'type' => 'string', 'description' => 'Optional domain profile/module key, e.g. crmproduct or dancephotographyshooting.' ],
                                                        'scope' => [ 'type' => 'string' ],
                                                        'module' => [ 'type' => 'string' ],
                                                        'type' => [ 'type' => 'string' ],
                                                        'name' => [ 'type' => 'string' ],
                                                        'data' => [ 'type' => 'object' ],
                                                        'metadata' => [ 'type' => 'object' ],
                                                        'tags' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                                                        'relations' => [ 'type' => 'array', 'items' => [ 'description' => 'Related entry id' ] ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Define XRM Structure',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'semantic', 'structure', 'write', 'confirmation'],
                                'priority' => 45,
                                'function' => [
                                        'name' => self::TOOL_DEFINE_STRUCTURE,
                                        'description' => 'Prepare or execute a structure/tag definition change such as creating a scope, creating/updating a module, describing a tag, or assigning tags/modules to scopes. First call without confirm to get a review plan; execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'action' => [ 'type' => 'string', 'enum' => self::STRUCTURE_ACTIONS ],
                                                        'scope' => [ 'type' => 'string' ],
                                                        'module' => [ 'type' => 'string' ],
                                                        'type_id' => [ 'type' => 'integer' ],
                                                        'type' => [ 'type' => 'string', 'description' => 'Optional type alias used to resolve type_id for create_module/update_module.' ],
                                                        'single' => [ 'type' => 'boolean' ],
                                                        'tag' => [ 'type' => 'string' ],
                                                        'description' => [ 'type' => 'string' ],
                                                        'confirm' => [ 'type' => 'boolean' ]
                                                ],
                                                'required' => ['action']
                                        ]
                                ]
                        ]
                ];
        }

        public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
                try {
                        return match ($name) {
                                self::TOOL_GET_MODEL => $this->getModel($arguments),
                                self::TOOL_EXPLAIN => $this->explain($arguments),
                                self::TOOL_PLAN_ENTRY => $this->planEntry($arguments),
                                self::TOOL_DEFINE_STRUCTURE => $this->defineStructure($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error($name, 'tool_error', 'The Memora semantic tool failed to process the request.', [], [
                                'exception' => $e::class,
                                'message' => $e->getMessage()
                        ]);
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uri' => 'memora://semantic-model',
                                'name' => 'memora-semantic-model',
                                'title' => 'Memora XRM Semantic Model',
                                'description' => 'Read the semantic structure of Memora/XRM: concepts, scopes, modules, types, tags, Memora-derived domain profiles, module tags, scope-module assignments and relation guidance.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://semantic-model/{scope}',
                                'name' => 'memora-semantic-model-scope-template',
                                'title' => 'Memora XRM Semantic Model by Scope',
                                'description' => 'Read semantic structure for one scope.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://domain-profiles',
                                'name' => 'memora-domain-profiles',
                                'title' => 'Memora Domain Profiles',
                                'description' => 'Read domain profiles derived from Memora modules, types, scopes and module tags.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://domain-profile/{module}',
                                'name' => 'memora-domain-profile-template',
                                'title' => 'Memora Domain Profile',
                                'description' => 'Read one domain profile derived from one Memora module.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://relation-patterns',
                                'name' => 'memora-relation-patterns',
                                'title' => 'Memora Relation Patterns',
                                'description' => 'Read relation guidance for semantic domain entries. Concrete relation meaning comes from Memora allocs and linked entries.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                if ($uri === 'memora://semantic-model') {
                        return $this->resultBuilder->resource($uri, $this->getModel(['include' => ['all']]));
                }

                if (str_starts_with($uri, 'memora://semantic-model/')) {
                        $scope = rawurldecode(substr($uri, strlen('memora://semantic-model/')));
                        return $this->resultBuilder->resource($uri, $this->getModel([
                                'scope' => $scope,
                                'include' => ['all']
                        ]));
                }


                if ($uri === 'memora://domain-profiles') {
                        return $this->resultBuilder->resource($uri, [
                                'ok' => true,
                                'source_of_truth' => 'memora',
                                'domain_profiles' => $this->semanticModel->getDomainProfiles()
                        ]);
                }

                if (str_starts_with($uri, 'memora://domain-profile/')) {
                        $module = rawurldecode(substr($uri, strlen('memora://domain-profile/')));
                        return $this->resultBuilder->resource($uri, [
                                'ok' => true,
                                'source_of_truth' => 'memora',
                                'domain_profile' => $this->semanticModel->getDomainProfile($module)
                        ]);
                }

                if ($uri === 'memora://relation-patterns') {
                        return $this->resultBuilder->resource($uri, [
                                'ok' => true,
                                'patterns' => $this->semanticModel->getRelationPatterns()
                        ]);
                }

                return null;
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_understand_xrm',
                                'title' => 'Understand Memora XRM',
                                'description' => 'Guide the model to inspect XRM semantics before answering or writing.',
                                'arguments' => [[ 'name' => 'scope', 'description' => 'Optional scope.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_plan_domain_entry',
                                'title' => 'Plan a Domain Entry',
                                'description' => 'Guide the model to plan correct type/module/tags/relations before creating an entry.',
                                'arguments' => [[ 'name' => 'intent', 'description' => 'User intent.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_define_structure',
                                'title' => 'Define XRM Structure',
                                'description' => 'Guide the model to safely propose XRM structure changes with confirmation.',
                                'arguments' => [[ 'name' => 'action', 'description' => 'Structure action.', 'required' => false ]]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_understand_xrm') {
                        $scope = $this->normalizer->normalizeString($arguments['scope'] ?? '');
                        $lines = [
                                'Use memora_get_xrm_semantic_model before deciding type/module/tags for a Memora entry.',
                                'Remember: type is the technical data shape, module is the domain meaning, tags classify and store state, relations/allocs connect knowledge, and domain profiles are derived from Memora/XRM itself.',
                                'Use memora_explain_xrm_semantics for ambiguous phrases such as "software module" or "dance photo model".'
                        ];
                        if ($scope !== '') $lines[] = 'Preferred scope: ' . $scope;
                        return $this->resultBuilder->prompt('Understand Memora/XRM semantics.', implode("\n", $lines));
                }

                if ($name === 'memora_plan_domain_entry') {
                        $intent = $this->normalizer->normalizeString($arguments['intent'] ?? '');
                        $lines = [
                                'Use memora_plan_domain_entry to propose type, module, tags, metadata and alloc relations from Memora structure before writing.',
                                'Present the plan to the user and ask for correction when required relations are missing.',
                                'After user approval, use memora_create_domain_entry or memora_create_entry with confirm=true.'
                        ];
                        if ($intent !== '') $lines[] = 'Intent: ' . $intent;
                        return $this->resultBuilder->prompt('Plan a Memora domain entry.', implode("\n", $lines));
                }

                if ($name === 'memora_define_structure') {
                        return $this->resultBuilder->prompt('Safely define Memora/XRM structure.', implode("\n", [
                                'Use memora_define_xrm_structure only for structure/catalog changes.',
                                'First call it with confirm=false and show the plan to the user.',
                                'Do not execute with confirm=true until the user explicitly approves the exact action.'
                        ]));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getModel(array $arguments): array {
                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['all'], self::MODEL_INCLUDES);
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 50, 50, 200);

                return $this->resultBuilder->success(
                        self::TOOL_GET_MODEL,
                        'XRM semantic model loaded.',
                        $this->semanticModel->getModel(
                                $this->normalizer->normalizeString($arguments['scope'] ?? '') ?: null,
                                $this->normalizer->normalizeString($arguments['module'] ?? '') ?: null,
                                $include,
                                $this->normalizer->normalizeString($arguments['query'] ?? ''),
                                $limit
                        )
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function explain(array $arguments): array {
                return $this->resultBuilder->success(
                        self::TOOL_EXPLAIN,
                        'XRM semantics explained.',
                        $this->semanticModel->explain($arguments)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function planEntry(array $arguments): array {
                return $this->resultBuilder->success(
                        self::TOOL_PLAN_ENTRY,
                        'Domain entry plan prepared.',
                        $this->semanticModel->planEntry($arguments)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function defineStructure(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_STRUCTURE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_DEFINE_STRUCTURE, 'capability_denied', 'XRM structure changes are not allowed for this tool configuration.');
                }

                $action = $this->normalizer->normalizeToken((string)($arguments['action'] ?? ''), self::STRUCTURE_ACTIONS);
                if ($action === '') {
                        return $this->resultBuilder->error(self::TOOL_DEFINE_STRUCTURE, 'invalid_action', 'Missing or unsupported structure action.');
                }

                $operation = $this->buildStructureOperation($action, $arguments);
                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);
                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_DEFINE_STRUCTURE,
                                'XRM structure change requires user confirmation before execution.',
                                $operation,
                                $operation + ['confirm' => true]
                        );
                }

                $this->executeStructureOperation($operation);

                return $this->resultBuilder->success(
                        self::TOOL_DEFINE_STRUCTURE,
                        'XRM structure change executed.',
                        ['operation' => $operation]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function buildStructureOperation(string $action, array $arguments): array {
                $scope = $this->normalizer->normalizeToken((string)($arguments['scope'] ?? ''));
                $module = $this->normalizer->normalizeToken((string)($arguments['module'] ?? ''));
                $tag = $this->normalizer->normalizeToken((string)($arguments['tag'] ?? ''));
                $description = $this->normalizer->normalizeString($arguments['description'] ?? '');
                $typeId = $this->resolveTypeId($arguments['type_id'] ?? null, $arguments['type'] ?? null);

                $operation = [
                        'action' => $action,
                        'scope' => $scope,
                        'module' => $module,
                        'tag' => $tag,
                        'description' => $description,
                        'type_id' => $typeId,
                        'single' => $this->normalizer->normalizeBool($arguments['single'] ?? true, true)
                ];

                return array_filter($operation, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        }

        /**
         * @param array<string,mixed> $operation
         */
        private function executeStructureOperation(array $operation): void {
                $action = (string)$operation['action'];

                match ($action) {
                        'create_scope' => $this->structureService->createScope([
                                'scope' => (string)($operation['scope'] ?? ''),
                                'description' => (string)($operation['description'] ?? '')
                        ]),
                        'create_module' => $this->structureService->createModule([
                                'module' => (string)($operation['module'] ?? ''),
                                'type_id' => (int)($operation['type_id'] ?? 0),
                                'single' => !empty($operation['single']),
                                'description' => (string)($operation['description'] ?? '')
                        ]),
                        'update_module' => $this->structureService->updateModule((string)($operation['module'] ?? ''), array_filter([
                                'type_id' => $operation['type_id'] ?? null,
                                'single' => $operation['single'] ?? null,
                                'description' => $operation['description'] ?? null
                        ], static fn(mixed $value): bool => $value !== null)),
                        'assign_module_to_scope' => $this->structureService->assignModuleToScope((string)($operation['module'] ?? ''), (string)($operation['scope'] ?? '')),
                        'remove_module_from_scope' => $this->structureService->removeModuleFromScope((string)($operation['module'] ?? ''), (string)($operation['scope'] ?? '')),
                        'describe_tag' => $this->tagService->describeTag((string)($operation['tag'] ?? ''), (string)($operation['description'] ?? '')),
                        'assign_tag_to_scope' => $this->tagService->assignTagToScope((string)($operation['tag'] ?? ''), (string)($operation['scope'] ?? '')),
                        'remove_tag_from_scope' => $this->tagService->removeTagFromScope((string)($operation['tag'] ?? ''), (string)($operation['scope'] ?? '')),
                        'assign_tag_to_module' => $this->tagService->assignTagToModule((string)($operation['tag'] ?? ''), (string)($operation['module'] ?? '')),
                        'remove_tag_from_module' => $this->tagService->removeTagFromModule((string)($operation['tag'] ?? ''), (string)($operation['module'] ?? '')),
                        default => throw new \InvalidArgumentException('Unsupported structure action: ' . $action)
                };
        }

        private function resolveTypeId(mixed $typeId, mixed $typeAlias): ?int {
                if (is_int($typeId) || ctype_digit((string)$typeId)) {
                        $id = (int)$typeId;
                        return $id > 0 ? $id : null;
                }

                $alias = $this->normalizer->normalizeToken((string)$typeAlias);
                if ($alias === '') {
                        return null;
                }

                $type = $this->structureService->getType($alias);
                if ($type === null) {
                        return null;
                }

                $id = (int)($type['id'] ?? 0);
                return $id > 0 ? $id : null;
        }
}
