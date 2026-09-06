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
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use ResourceFoundation\Api\IEntityStructureService;
use ResourceFoundation\Api\IEntityTagService;

/**
 * Read-only structure discovery tool for Memora/XRM.
 */
class MemoraStructureAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_STRUCTURE = 'memora_get_structure';
        private const TOOL_DESCRIBE_TYPE = 'memora_describe_type';

        private const SECTIONS = [
                'all',
                'types',
                'modules',
                'scopes',
                'tags'
        ];

        private const MAX_LIMIT = 100;

        public function __construct(
                private readonly IEntityStructureService $structureService,
                private readonly IEntityTagService $tagService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorastructureagenttool';
        }

        public function getDescription(): string {
                return 'Provides read-only Memora/XRM structure discovery for types, modules, scopes and tags.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Structure',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'structure', 'readonly', 'discovery'],
                                'priority' => 80,
                                'function' => [
                                        'name' => self::TOOL_GET_STRUCTURE,
                                        'description' => 'Discover available Memora/XRM types, modules, scopes, and tags before reading or creating entries.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'section' => [
                                                                'type' => 'string',
                                                                'enum' => self::SECTIONS,
                                                                'description' => 'Structure section to return. Default: all.'
                                                        ],
                                                        'query' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional local text filter applied to returned rows.'
                                                        ],
                                                        'limit' => [
                                                                'type' => 'integer',
                                                                'description' => 'Maximum rows per section. Default: 50. Hard maximum: 100.'
                                                        ]
                                                ]
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Describe Type',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'type', 'structure', 'readonly'],
                                'priority' => 75,
                                'function' => [
                                        'name' => self::TOOL_DESCRIBE_TYPE,
                                        'description' => 'Read one Memora/XRM type definition by id or alias.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'type' => [
                                                                'description' => 'Type id or alias.'
                                                        ]
                                                ],
                                                'required' => ['type']
                                        ]
                                ]
                        ]
                ];
        }

        public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
                try {
                        return match ($name) {
                                self::TOOL_GET_STRUCTURE => $this->getStructure($arguments),
                                self::TOOL_DESCRIBE_TYPE => $this->describeType($arguments),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora structure tool failed to process the request.'
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uri' => 'memora://structure',
                                'name' => 'memora-structure',
                                'title' => 'Memora Structure',
                                'description' => 'Lists Memora/XRM type, module, scope and tag structure metadata.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://types',
                                'name' => 'memora-types',
                                'title' => 'Memora Types',
                                'description' => 'Lists Memora/XRM type definitions.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://type/{type}',
                                'name' => 'memora-type-template',
                                'title' => 'Memora Type',
                                'description' => 'Reads one Memora/XRM type definition.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://modules',
                                'name' => 'memora-modules',
                                'title' => 'Memora Modules',
                                'description' => 'Lists Memora/XRM modules.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://scopes',
                                'name' => 'memora-scopes',
                                'title' => 'Memora Scopes',
                                'description' => 'Lists Memora/XRM scopes.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://tags',
                                'name' => 'memora-tags',
                                'title' => 'Memora Tags',
                                'description' => 'Lists known Memora/XRM tags.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                if ($uri === 'memora://structure') {
                        return $this->resultBuilder->resource($uri, $this->getStructure(['section' => 'all', 'limit' => 100]));
                }

                if ($uri === 'memora://types') {
                        return $this->resultBuilder->resource($uri, $this->getStructure(['section' => 'types', 'limit' => 100]));
                }

                if ($uri === 'memora://modules') {
                        return $this->resultBuilder->resource($uri, $this->getStructure(['section' => 'modules', 'limit' => 100]));
                }

                if ($uri === 'memora://scopes') {
                        return $this->resultBuilder->resource($uri, $this->getStructure(['section' => 'scopes', 'limit' => 100]));
                }

                if ($uri === 'memora://tags') {
                        return $this->resultBuilder->resource($uri, $this->getStructure(['section' => 'tags', 'limit' => 100]));
                }

                $prefix = 'memora://type/';
                if (!str_starts_with($uri, $prefix)) {
                        return null;
                }

                $type = rawurldecode(substr($uri, strlen($prefix)));
                return $this->resultBuilder->resource($uri, $this->describeType(['type' => $type]));
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_discover_structure',
                                'title' => 'Discover Memora Structure',
                                'description' => 'Guide the model to inspect Memora/XRM types, modules, scopes and tags before choosing read or write operations.',
                                'arguments' => []
                        ],
                        [
                                'name' => 'memora_describe_type',
                                'title' => 'Describe a Memora Type',
                                'description' => 'Guide the model to load a type definition before creating or editing entries of that type.',
                                'arguments' => [
                                        [ 'name' => 'type', 'description' => 'Optional type id or alias.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_discover_structure') {
                        return $this->resultBuilder->prompt(
                                'Discover Memora/XRM structure.',
                                implode("\n", [
                                        'Use memora_get_structure before choosing type, module, scope or tag filters.',
                                        'Use section "types" when preparing entry creation or update.',
                                        'Use section "tags" when the user mentions categorization or filtering by labels.'
                                ])
                        );
                }

                if ($name === 'memora_describe_type') {
                        $type = $this->normalizer->normalizeString($arguments['type'] ?? '');
                        $lines = [
                                'Use memora_describe_type to inspect one Memora/XRM type before building entry payloads.',
                                'Prefer type aliases over numeric ids when the alias is known.'
                        ];
                        if ($type !== '') $lines[] = 'Type: ' . $type;

                        return $this->resultBuilder->prompt('Describe a Memora/XRM type.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getStructure(array $arguments): array {
                $section = $this->normalizer->normalizeToken((string)($arguments['section'] ?? 'all'), self::SECTIONS, 'all');
                $query = $this->normalizer->normalizeString($arguments['query'] ?? '');
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 50, 50, self::MAX_LIMIT);

                $data = [];

                if ($section === 'all' || $section === 'types') {
                        $data['types'] = $this->filterRows($this->structureService->getTypes(), $query, $limit);
                }

                if ($section === 'all' || $section === 'modules') {
                        $data['modules'] = $this->filterRows($this->structureService->getModules(), $query, $limit);
                }

                if ($section === 'all' || $section === 'scopes') {
                        $data['scopes'] = $this->filterRows($this->structureService->getScopes(), $query, $limit);
                }

                if ($section === 'all' || $section === 'tags') {
                        $data['tags'] = $this->filterRows($this->tagService->getTags(), $query, $limit);
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_STRUCTURE,
                        'Structure loaded.',
                        $data
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function describeType(array $arguments): array {
                $type = $arguments['type'] ?? null;
                if ($type === null || $type === '') {
                        return $this->resultBuilder->error(self::TOOL_DESCRIBE_TYPE, 'missing_type', 'Missing required parameter: type.');
                }

                $row = $this->structureService->getType(is_int($type) || ctype_digit((string)$type) ? (int)$type : (string)$type);
                if ($row === null) {
                        return $this->resultBuilder->error(self::TOOL_DESCRIBE_TYPE, 'type_not_found', 'Type not found.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_DESCRIBE_TYPE,
                        'Type loaded.',
                        ['type' => $row]
                );
        }

        /**
         * @param array<int,array<string,mixed>> $rows
         * @return array<int,array<string,mixed>>
         */
        private function filterRows(array $rows, string $query, int $limit): array {
                if ($query !== '') {
                        $needle = mb_strtolower($query);
                        $rows = array_values(array_filter($rows, static function(array $row) use ($needle): bool {
                                $haystack = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                                return str_contains($haystack, $needle);
                        }));
                }

                return array_slice($rows, 0, $limit);
        }
}
