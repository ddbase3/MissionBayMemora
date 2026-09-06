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
use ResourceFoundation\Api\IEntityUserDataService;

/**
 * Memora/XRM user-data tool.
 *
 * Provides user-specific entity data access and confirmation-aware mutations.
 */
class MemoraUserDataAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_USERDATA = 'memora_get_user_data';
        private const TOOL_GET_USERDATA_VALUE = 'memora_get_user_data_value';
        private const TOOL_SET_USERDATA = 'memora_set_user_data';
        private const TOOL_REMOVE_USERDATA = 'memora_remove_user_data';

        public function __construct(
                private readonly IEntityUserDataService $userDataService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memorauserdataagenttool';
        }

        public function getDescription(): string {
                return 'Provides Memora/XRM user-specific entry data tools with confirmation-aware mutations.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get User Data',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'userdata', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_USERDATA,
                                        'description' => 'Read all user-specific data values for one Memora/XRM entry and user.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Required entry id.' ],
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ]
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get User Data Value',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'userdata', 'readonly'],
                                'priority' => 68,
                                'function' => [
                                        'name' => self::TOOL_GET_USERDATA_VALUE,
                                        'description' => 'Read one user-specific data value for one Memora/XRM entry and user.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Required entry id.' ],
                                                        'name' => [ 'type' => 'string', 'description' => 'Required user-data key.' ],
                                                        'default' => [ 'description' => 'Optional default value returned when the key is missing.' ],
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ]
                                                ],
                                                'required' => ['entry_id', 'name']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Set User Data',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'userdata', 'write', 'confirmation'],
                                'priority' => 50,
                                'function' => [
                                        'name' => self::TOOL_SET_USERDATA,
                                        'description' => 'Prepare or execute setting user-specific data values for one entry. First call without confirm or with confirm=false to get a review plan. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Required entry id.' ],
                                                        'data' => [ 'type' => 'object', 'description' => 'Key-value user data to set.' ],
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['entry_id', 'data']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Remove User Data',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'userdata', 'write', 'confirmation'],
                                'priority' => 45,
                                'function' => [
                                        'name' => self::TOOL_REMOVE_USERDATA,
                                        'description' => 'Prepare or execute removing user-specific data keys for one entry. Requires confirmation before mutation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [ 'description' => 'Required entry id.' ],
                                                        'names' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'User-data keys to remove.' ],
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['entry_id', 'names']
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
                                self::TOOL_GET_USERDATA => $this->getUserData($arguments, $context),
                                self::TOOL_GET_USERDATA_VALUE => $this->getUserDataValue($arguments, $context),
                                self::TOOL_SET_USERDATA => $this->setUserData($arguments, $context),
                                self::TOOL_REMOVE_USERDATA => $this->removeUserData($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora user-data tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uriTemplate' => 'memora://entry/{entry_id}/userdata',
                                'name' => 'memora-entry-userdata-template',
                                'title' => 'Memora Entry User Data',
                                'description' => 'Reads user-specific data for one Memora/XRM entry for the current user when supported by the backend.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{entry_id}/userdata/{name}',
                                'name' => 'memora-entry-userdata-value-template',
                                'title' => 'Memora Entry User Data Value',
                                'description' => 'Reads one user-specific data value for one Memora/XRM entry for the current user when supported by the backend.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                $prefix = 'memora://entry/';
                if (!str_starts_with($uri, $prefix) || !str_contains($uri, '/userdata')) {
                        return null;
                }

                $rest = rawurldecode(substr($uri, strlen($prefix)));
                $parts = explode('/userdata', $rest, 2);
                $entryId = $parts[0] ?? '';
                if ($entryId === '') {
                        return null;
                }

                $name = '';
                if (isset($parts[1]) && str_starts_with($parts[1], '/')) {
                        $name = substr($parts[1], 1);
                }

                $result = $name === ''
                        ? $this->getUserData(['entry_id' => $entryId], $context)
                        : $this->getUserDataValue(['entry_id' => $entryId, 'name' => $name], $context);

                return $this->resultBuilder->resource($uri, $result);
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_read_user_data',
                                'title' => 'Read Memora User Data',
                                'description' => 'Guide the model to inspect user-specific data for one entry.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_set_user_data',
                                'title' => 'Set Memora User Data With Confirmation',
                                'description' => 'Guide the model to prepare a user-data change and wait for user confirmation before writing.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]]
                        ],
                        [
                                'name' => 'memora_remove_user_data',
                                'title' => 'Remove Memora User Data With Confirmation',
                                'description' => 'Guide the model to prepare user-data key removal and wait for user confirmation before writing.',
                                'arguments' => [[ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);
                $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');

                if ($name === 'memora_read_user_data') {
                        $lines = [
                                'Use memora_get_user_data to inspect user-specific data for one entry.',
                                'Use memora_get_user_data_value when only one key is needed.'
                        ];
                        if ($entryId !== '') $lines[] = 'Known entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Read Memora/XRM user data.', implode("
", $lines));
                }

                if ($name === 'memora_set_user_data') {
                        $lines = [
                                'Prepare a memora_set_user_data call for personal or per-user entry state.',
                                'Call it first without confirm to get a review plan. Execute only after user approval with confirm=true.'
                        ];
                        if ($entryId !== '') $lines[] = 'Known entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Set Memora/XRM user data safely.', implode("
", $lines));
                }

                if ($name === 'memora_remove_user_data') {
                        $lines = [
                                'Prepare a memora_remove_user_data call only for keys the user explicitly wants removed.',
                                'Call it first without confirm to get a review plan. Execute only after user approval with confirm=true.'
                        ];
                        if ($entryId !== '') $lines[] = 'Known entry id: ' . $entryId;
                        return $this->resultBuilder->prompt('Remove Memora/XRM user data safely.', implode("
", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getUserData(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_USERDATA_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_USERDATA, 'capability_denied', 'User-data reading is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_USERDATA, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $userId = $this->normalizeOptionalUserId($arguments['user_id'] ?? null);

                return $this->resultBuilder->success(
                        self::TOOL_GET_USERDATA,
                        'User data loaded.',
                        [
                                'entry_id' => $entryId,
                                'user_id' => $userId,
                                'userdata' => $this->userDataService->getUserData($entryId, $userId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getUserDataValue(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_USERDATA_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_USERDATA_VALUE, 'capability_denied', 'User-data reading is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_USERDATA_VALUE, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $key = $this->normalizer->normalizeString($arguments['name'] ?? '');
                if ($key === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_USERDATA_VALUE, 'missing_name', 'Missing required parameter: name.');
                }

                $userId = $this->normalizeOptionalUserId($arguments['user_id'] ?? null);
                $default = $arguments['default'] ?? null;

                return $this->resultBuilder->success(
                        self::TOOL_GET_USERDATA_VALUE,
                        'User data value loaded.',
                        [
                                'entry_id' => $entryId,
                                'user_id' => $userId,
                                'name' => $key,
                                'value' => $this->userDataService->getUserDataValue($entryId, $key, $default, $userId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function setUserData(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_USERDATA_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_SET_USERDATA, 'capability_denied', 'User-data changes are not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_SET_USERDATA, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $data = $this->normalizer->normalizeArray($arguments['data'] ?? []);
                if ($data === []) {
                        return $this->resultBuilder->error(self::TOOL_SET_USERDATA, 'empty_userdata', 'Provide at least one user-data key to set.');
                }

                $userId = $this->normalizeOptionalUserId($arguments['user_id'] ?? null);
                $plan = [
                        'operation' => 'set_user_data',
                        'entry_id' => $entryId,
                        'user_id' => $userId,
                        'data' => $data
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_SET_USERDATA, 'User-data changes require user confirmation before execution.', $plan, $arguments);
                }

                $this->userDataService->setUserData($entryId, $data, $userId);

                return $this->resultBuilder->success(
                        self::TOOL_SET_USERDATA,
                        'User data updated.',
                        [
                                'entry_id' => $entryId,
                                'user_id' => $userId,
                                'userdata' => $this->userDataService->getUserData($entryId, $userId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function removeUserData(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_USERDATA_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REMOVE_USERDATA, 'capability_denied', 'User-data changes are not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_REMOVE_USERDATA, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $names = $this->normalizer->normalizeStringList($arguments['names'] ?? []);
                if ($names === []) {
                        return $this->resultBuilder->error(self::TOOL_REMOVE_USERDATA, 'empty_names', 'Provide at least one user-data key to remove.');
                }

                $userId = $this->normalizeOptionalUserId($arguments['user_id'] ?? null);
                $plan = [
                        'operation' => 'remove_user_data',
                        'entry_id' => $entryId,
                        'user_id' => $userId,
                        'names' => $names
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_REMOVE_USERDATA, 'User-data removal requires user confirmation before execution.', $plan, $arguments);
                }

                $this->userDataService->removeUserData($entryId, $names, $userId);

                return $this->resultBuilder->success(
                        self::TOOL_REMOVE_USERDATA,
                        'User data removed.',
                        [
                                'entry_id' => $entryId,
                                'user_id' => $userId,
                                'userdata' => $this->userDataService->getUserData($entryId, $userId)
                        ]
                );
        }

        private function normalizeOptionalUserId(mixed $value): ?int {
                if ($value === null || $value === '') {
                        return null;
                }

                return (int)$value;
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
