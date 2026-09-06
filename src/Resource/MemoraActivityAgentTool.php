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
use MissionBayMemora\Service\MemoraAgentInputNormalizer;
use MissionBayMemora\Service\MemoraAgentResultBuilder;
use ResourceFoundation\Api\IEntityActivityService;

/**
 * Memora/XRM activity tool.
 *
 * Provides read access to logs and comments and confirmation-aware comment
 * creation for agent workflows.
 */
class MemoraActivityAgentTool extends AbstractAgentResource implements IAgentTool, IAgentPromptProvider {

        private const TOOL_GET_ACTIVITY = 'memora_get_activity';
        private const TOOL_ADD_COMMENT = 'memora_add_comment';

        private const MAX_LIMIT = 100;

        private const INCLUDE_VALUES = [
                'logs',
                'comments',
                'all'
        ];

        public function __construct(
                private readonly IEntityActivityService $activityService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memoraactivityagenttool';
        }

        public function getDescription(): string {
                return 'Provides Memora/XRM log and comment tools, including confirmation-aware comment creation.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Activity',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'activity', 'comments', 'logs', 'readonly'],
                                'priority' => 80,
                                'function' => [
                                        'name' => self::TOOL_GET_ACTIVITY,
                                        'description' => 'Read Memora/XRM logs and comments for one entry.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [
                                                                'description' => 'Required entry id.'
                                                        ],
                                                        'include' => [
                                                                'type' => 'array',
                                                                'items' => [
                                                                        'type' => 'string',
                                                                        'enum' => self::INCLUDE_VALUES
                                                                ],
                                                                'description' => 'Activity parts to load. Default: logs and comments.'
                                                        ],
                                                        'limit' => [
                                                                'type' => 'integer',
                                                                'description' => 'Maximum rows per activity part. Default: 25. Hard maximum: 100.'
                                                        ],
                                                        'offset' => [
                                                                'type' => 'integer',
                                                                'description' => 'Pagination offset passed to backends that support it.'
                                                        ]
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Add Comment',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'activity', 'comment', 'write', 'confirmation'],
                                'priority' => 60,
                                'function' => [
                                        'name' => self::TOOL_ADD_COMMENT,
                                        'description' => 'Prepare or execute adding a comment to one Memora/XRM entry. First call without confirm or with confirm=false to get a review plan. Execute only after user approval by repeating the call with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [
                                                                'description' => 'Required entry id.'
                                                        ],
                                                        'comment' => [
                                                                'type' => 'string',
                                                                'description' => 'Comment text to add.'
                                                        ],
                                                        'parent_id' => [
                                                                'description' => 'Optional parent comment id for threaded comments.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute. Default false returns only a proposed comment plan.'
                                                        ]
                                                ],
                                                'required' => ['entry_id', 'comment']
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
                                self::TOOL_GET_ACTIVITY => $this->getActivity($arguments, $context),
                                self::TOOL_ADD_COMMENT => $this->addComment($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora activity tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_read_activity',
                                'title' => 'Read Memora Activity',
                                'description' => 'Guide the model to inspect logs and comments for one Memora/XRM entry.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_add_comment',
                                'title' => 'Add a Memora Comment With Confirmation',
                                'description' => 'Guide the model to prepare a comment, show it to the user, and wait for confirmation before adding it.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_read_activity') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Use memora_get_activity to inspect comments and logs for one Memora/XRM entry.',
                                'Use a small limit first unless the user explicitly asks for a longer history.'
                        ];

                        if ($entryId !== '') {
                                $lines[] = 'Preferred entry id: ' . $entryId;
                        }

                        return $this->resultBuilder->prompt('Read Memora activity.', implode("\n", $lines));
                }

                if ($name === 'memora_add_comment') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Prepare a comment for one Memora/XRM entry using memora_add_comment.',
                                'Call memora_add_comment with confirm=false first and present the exact comment text to the user.',
                                'Do not call memora_add_comment with confirm=true until the user explicitly approves the comment.'
                        ];

                        if ($entryId !== '') {
                                $lines[] = 'Preferred entry id: ' . $entryId;
                        }

                        return $this->resultBuilder->prompt('Add a Memora comment with user confirmation.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getActivity(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACTIVITY_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ACTIVITY, 'capability_denied', 'Activity reading is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_ACTIVITY, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $include = $this->normalizer->normalizeIncludes($arguments['include'] ?? null, ['logs', 'comments'], self::INCLUDE_VALUES);
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);

                $options = [
                        'limit' => $limit,
                        'offset' => $offset
                ];

                $data = [
                        'entry_id' => $entryId
                ];

                $returned = 0;
                $total = 0;

                if ($this->include($include, 'logs')) {
                        $logs = $this->activityService->getLogs($entryId, $options);
                        $total += count($logs);
                        $logs = array_slice($logs, 0, $limit);
                        $returned += count($logs);
                        $data['logs'] = $logs;
                }

                if ($this->include($include, 'comments')) {
                        $comments = $this->activityService->getComments($entryId, $options);
                        $total += count($comments);
                        $comments = array_slice($comments, 0, $limit);
                        $returned += count($comments);
                        $data['comments'] = $comments;
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_ACTIVITY,
                        $returned > 0 ? 'Activity loaded.' : 'No activity found.',
                        $data,
                        [],
                        $this->resultBuilder->paging($offset, $limit, $total, $returned)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function addComment(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACTIVITY_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_ADD_COMMENT, 'capability_denied', 'Comment creation is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_ADD_COMMENT, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $comment = $this->normalizer->normalizeString($arguments['comment'] ?? '');
                if ($comment === '') {
                        return $this->resultBuilder->error(self::TOOL_ADD_COMMENT, 'missing_comment', 'Missing required parameter: comment.');
                }

                $parentId = $this->normalizeOptionalInt($arguments['parent_id'] ?? null);
                $confirm = $this->normalizer->normalizeBool($arguments['confirm'] ?? false, false);

                $plan = [
                        'operation' => 'add_comment',
                        'entry_id' => $entryId,
                        'comment' => $comment,
                        'parent_id' => $parentId
                ];

                if ($this->accessPolicy->requiresConfirmation($this->config) && !$confirm) {
                        $confirmationArguments = $arguments;
                        $confirmationArguments['confirm'] = true;

                        return $this->resultBuilder->confirmationRequired(
                                self::TOOL_ADD_COMMENT,
                                'Adding a comment requires user confirmation before execution.',
                                $plan,
                                $confirmationArguments
                        );
                }

                $commentId = $this->activityService->addComment($entryId, $comment, $parentId);

                return $this->resultBuilder->success(
                        self::TOOL_ADD_COMMENT,
                        'Comment added.',
                        [
                                'entry_id' => $entryId,
                                'comment_id' => $commentId
                        ]
                );
        }

        private function normalizeOptionalInt(mixed $value): ?int {
                if ($value === null || $value === '') {
                        return null;
                }

                if (is_int($value)) {
                        return $value > 0 ? $value : null;
                }

                $value = trim((string)$value);
                if ($value === '' || !ctype_digit($value)) {
                        return null;
                }

                $value = (int)$value;
                return $value > 0 ? $value : null;
        }

        /**
         * @param array<int,string> $include
         */
        private function include(array $include, string $name): bool {
                return in_array('all', $include, true) || in_array($name, $include, true);
        }
}
