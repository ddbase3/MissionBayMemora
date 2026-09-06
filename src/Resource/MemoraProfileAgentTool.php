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
use ResourceFoundation\Api\IEntityProfileService;

/**
 * Memora/XRM profile tool.
 *
 * Provides access to user-specific entity profiles and confirmation-aware
 * profile mutations through the generic ResourceFoundation profile service.
 */
class MemoraProfileAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_ACTIVE_PROFILE = 'memora_get_active_profile';
        private const TOOL_GET_PROFILES = 'memora_get_profiles';
        private const TOOL_CREATE_PROFILE = 'memora_create_profile';
        private const TOOL_UPDATE_PROFILE = 'memora_update_profile';
        private const TOOL_ARCHIVE_PROFILE = 'memora_archive_profile';
        private const TOOL_SET_ACTIVE_PROFILE = 'memora_set_active_profile';

        public function __construct(
                private readonly IEntityProfileService $profileService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memoraprofileagenttool';
        }

        public function getDescription(): string {
                return 'Provides Memora/XRM profile tools for reading and managing user-specific entity profiles.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Active Profile',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'readonly'],
                                'priority' => 72,
                                'function' => [
                                        'name' => self::TOOL_GET_ACTIVE_PROFILE,
                                        'description' => 'Read the active Memora/XRM profile for the current or specified user.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ]
                                                ],
                                                'required' => []
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Profiles',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_PROFILES,
                                        'description' => 'Read Memora/XRM profiles for the current or specified user.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'user_id' => [ 'description' => 'Optional user id. Omit for the current user when supported by the backend.' ],
                                                        'include_archived' => [ 'type' => 'boolean', 'description' => 'Whether archived profiles should be included. Default false.' ],
                                                        'limit' => [ 'type' => 'integer', 'description' => 'Maximum number of profiles to return. Default: 25. Hard maximum: 100.' ],
                                                        'offset' => [ 'type' => 'integer', 'description' => 'Pagination offset.' ]
                                                ],
                                                'required' => []
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Create Profile',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'write', 'confirmation'],
                                'priority' => 52,
                                'function' => [
                                        'name' => self::TOOL_CREATE_PROFILE,
                                        'description' => 'Prepare or execute creating a Memora/XRM profile. First call without confirm or with confirm=false to get a review plan. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'user_id' => [ 'type' => 'integer', 'description' => 'User id that owns the new profile.' ],
                                                        'profile' => [ 'type' => 'object', 'description' => 'Profile payload. Common keys: name, profile, standard, protected, active, archive.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['user_id', 'profile']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Update Profile',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'write', 'confirmation'],
                                'priority' => 50,
                                'function' => [
                                        'name' => self::TOOL_UPDATE_PROFILE,
                                        'description' => 'Prepare or execute updating one Memora/XRM profile. Requires user confirmation before mutation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'profile_id' => [ 'description' => 'Required profile id.' ],
                                                        'patch' => [ 'type' => 'object', 'description' => 'Patch payload. Supported keys: name, profile, standard, protected, active, archive.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['profile_id', 'patch']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Archive Profile',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'write', 'confirmation'],
                                'priority' => 48,
                                'function' => [
                                        'name' => self::TOOL_ARCHIVE_PROFILE,
                                        'description' => 'Prepare or execute archiving one Memora/XRM profile. This is a soft archive operation and requires confirmation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'profile_id' => [ 'description' => 'Required profile id.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['profile_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Set Active Profile',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'profile', 'write', 'confirmation'],
                                'priority' => 46,
                                'function' => [
                                        'name' => self::TOOL_SET_ACTIVE_PROFILE,
                                        'description' => 'Prepare or execute making one profile active for a user. Requires confirmation before mutation.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'user_id' => [ 'type' => 'integer', 'description' => 'Required user id.' ],
                                                        'profile_id' => [ 'type' => 'integer', 'description' => 'Required profile id.' ],
                                                        'confirm' => [ 'type' => 'boolean', 'description' => 'Must be true after user confirmation to execute.' ]
                                                ],
                                                'required' => ['user_id', 'profile_id']
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
                                self::TOOL_GET_ACTIVE_PROFILE => $this->getActiveProfile($arguments, $context),
                                self::TOOL_GET_PROFILES => $this->getProfiles($arguments, $context),
                                self::TOOL_CREATE_PROFILE => $this->createProfile($arguments, $context),
                                self::TOOL_UPDATE_PROFILE => $this->updateProfile($arguments, $context),
                                self::TOOL_ARCHIVE_PROFILE => $this->archiveProfile($arguments, $context),
                                self::TOOL_SET_ACTIVE_PROFILE => $this->setActiveProfile($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora profile tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uri' => 'memora://profile/active',
                                'name' => 'memora-active-profile',
                                'title' => 'Memora Active Profile',
                                'description' => 'Reads the active profile for the current user when supported by the backend.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://profile/active/{user_id}',
                                'name' => 'memora-active-profile-user-template',
                                'title' => 'Memora Active Profile for User',
                                'description' => 'Reads the active profile for a specific user id.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://profiles',
                                'name' => 'memora-profiles',
                                'title' => 'Memora Profiles',
                                'description' => 'Lists profiles for the current user when supported by the backend.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://user/{user_id}/profiles',
                                'name' => 'memora-user-profiles-template',
                                'title' => 'Memora User Profiles',
                                'description' => 'Lists profiles for a specific user id.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                if ($uri === 'memora://profile/active') {
                        return $this->resultBuilder->resource($uri, [
                                'profile' => $this->profileService->getActiveProfile(null)
                        ]);
                }

                $activePrefix = 'memora://profile/active/';
                if (str_starts_with($uri, $activePrefix)) {
                        $userId = $this->normalizeIntId(rawurldecode(substr($uri, strlen($activePrefix))));
                        if ($userId === null) {
                                return $this->resultBuilder->resource($uri, [
                                        'ok' => false,
                                        'code' => 'invalid_user_id',
                                        'message' => 'Invalid user id in resource URI.'
                                ]);
                        }

                        return $this->resultBuilder->resource($uri, [
                                'user_id' => $userId,
                                'profile' => $this->profileService->getActiveProfile($userId)
                        ]);
                }

                if ($uri === 'memora://profiles') {
                        return $this->resultBuilder->resource($uri, [
                                'profiles' => $this->profileService->getProfiles(null, false)
                        ]);
                }

                $prefix = 'memora://user/';
                $suffix = '/profiles';
                if (str_starts_with($uri, $prefix) && str_ends_with($uri, $suffix)) {
                        $middle = substr($uri, strlen($prefix), -strlen($suffix));
                        $userId = $this->normalizeIntId(rawurldecode($middle));
                        if ($userId === null) {
                                return $this->resultBuilder->resource($uri, [
                                        'ok' => false,
                                        'code' => 'invalid_user_id',
                                        'message' => 'Invalid user id in resource URI.'
                                ]);
                        }

                        return $this->resultBuilder->resource($uri, [
                                'user_id' => $userId,
                                'profiles' => $this->profileService->getProfiles($userId, false)
                        ]);
                }

                return null;
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_read_profiles',
                                'title' => 'Read Memora Profiles',
                                'description' => 'Guide the model to inspect active and available Memora/XRM profiles.',
                                'arguments' => [
                                        [ 'name' => 'user_id', 'description' => 'Optional user id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_create_profile',
                                'title' => 'Create a Memora Profile With Confirmation',
                                'description' => 'Guide the model to prepare a new profile, show it to the user, and wait for confirmation before creating it.',
                                'arguments' => [
                                        [ 'name' => 'user_id', 'description' => 'Optional target user id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_update_profile',
                                'title' => 'Update a Memora Profile With Confirmation',
                                'description' => 'Guide the model to prepare a profile update and wait for confirmation before executing it.',
                                'arguments' => [
                                        [ 'name' => 'profile_id', 'description' => 'Optional profile id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_set_active_profile',
                                'title' => 'Set Active Memora Profile With Confirmation',
                                'description' => 'Guide the model to offer an active-profile switch and wait for user confirmation.',
                                'arguments' => [
                                        [ 'name' => 'user_id', 'description' => 'Optional user id.', 'required' => false ],
                                        [ 'name' => 'profile_id', 'description' => 'Optional profile id.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_read_profiles') {
                        $userId = $this->normalizer->normalizeString($arguments['user_id'] ?? '');
                        $lines = [
                                'Use memora_get_active_profile to inspect the active profile first.',
                                'Use memora_get_profiles when the user needs to compare or choose from available profiles.',
                                'Do not mutate profiles from this prompt.'
                        ];

                        if ($userId !== '') {
                                $lines[] = 'Preferred user id: ' . $userId;
                        }

                        return $this->resultBuilder->prompt('Read Memora profiles.', implode("\n", $lines));
                }

                if ($name === 'memora_create_profile') {
                        $userId = $this->normalizer->normalizeString($arguments['user_id'] ?? '');
                        $lines = [
                                'Prepare a new Memora/XRM profile using memora_create_profile.',
                                'Call memora_create_profile with confirm=false first and present the profile name and profile payload to the user.',
                                'Do not call memora_create_profile with confirm=true until the user explicitly approves the proposed profile.'
                        ];

                        if ($userId !== '') {
                                $lines[] = 'Target user id: ' . $userId;
                        }

                        return $this->resultBuilder->prompt('Create a Memora profile with confirmation.', implode("\n", $lines));
                }

                if ($name === 'memora_update_profile') {
                        $profileId = $this->normalizer->normalizeString($arguments['profile_id'] ?? '');
                        $lines = [
                                'Prepare a Memora/XRM profile update using memora_update_profile.',
                                'Call memora_update_profile with confirm=false first and show the exact patch to the user.',
                                'Execute only after explicit approval with confirm=true.'
                        ];

                        if ($profileId !== '') {
                                $lines[] = 'Preferred profile id: ' . $profileId;
                        }

                        return $this->resultBuilder->prompt('Update a Memora profile with confirmation.', implode("\n", $lines));
                }

                if ($name === 'memora_set_active_profile') {
                        $userId = $this->normalizer->normalizeString($arguments['user_id'] ?? '');
                        $profileId = $this->normalizer->normalizeString($arguments['profile_id'] ?? '');
                        $lines = [
                                'Prepare an active-profile switch using memora_set_active_profile.',
                                'Call memora_set_active_profile with confirm=false first and show which user and profile will be affected.',
                                'Execute only after explicit user confirmation with confirm=true.'
                        ];

                        if ($userId !== '') {
                                $lines[] = 'Preferred user id: ' . $userId;
                        }
                        if ($profileId !== '') {
                                $lines[] = 'Preferred profile id: ' . $profileId;
                        }

                        return $this->resultBuilder->prompt('Set an active Memora profile with confirmation.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getActiveProfile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ACTIVE_PROFILE, 'capability_denied', 'Profile read tools are not allowed for this resource configuration.');
                }

                $userId = $this->optionalUserId($arguments['user_id'] ?? null);
                $profile = $this->profileService->getActiveProfile($userId);

                return $this->resultBuilder->success(
                        self::TOOL_GET_ACTIVE_PROFILE,
                        $profile ? 'Active profile loaded.' : 'No active profile found.',
                        [
                                'user_id' => $userId,
                                'profile' => $profile
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getProfiles(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_PROFILES, 'capability_denied', 'Profile read tools are not allowed for this resource configuration.');
                }

                $userId = $this->optionalUserId($arguments['user_id'] ?? null);
                $includeArchived = $this->normalizer->normalizeBool($arguments['include_archived'] ?? false, false);
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, 100);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);

                $profiles = $this->profileService->getProfiles($userId, $includeArchived);
                $total = count($profiles);
                $items = array_slice($profiles, $offset, $limit);

                return $this->resultBuilder->success(
                        self::TOOL_GET_PROFILES,
                        $total > 0 ? 'Profiles loaded.' : 'No profiles found.',
                        [
                                'user_id' => $userId,
                                'include_archived' => $includeArchived
                        ],
                        $items,
                        $this->resultBuilder->paging($offset, $limit, $total, count($items), ($offset + $limit) < $total)
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createProfile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_PROFILE, 'capability_denied', 'Profile write tools are not allowed for this resource configuration.');
                }

                $userId = $this->requiredIntId($arguments['user_id'] ?? null, self::TOOL_CREATE_PROFILE, 'user_id');
                if (is_array($userId)) {
                        return $userId;
                }

                $profile = $this->normalizeProfilePayload($arguments['profile'] ?? [], true);
                if ($profile === []) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_PROFILE, 'invalid_profile', 'A new profile requires at least a non-empty name.');
                }

                $plan = [
                        'action' => 'create_profile',
                        'user_id' => $userId,
                        'profile' => $profile
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_CREATE_PROFILE, 'Review the proposed profile before creating it.', $plan, $arguments);
                }

                $profileId = $this->profileService->createProfile($userId, $profile);

                return $this->resultBuilder->success(
                        self::TOOL_CREATE_PROFILE,
                        'Profile created.',
                        [
                                'profile_id' => $profileId,
                                'user_id' => $userId,
                                'profile' => $profile
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function updateProfile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_PROFILE, 'capability_denied', 'Profile write tools are not allowed for this resource configuration.');
                }

                $profileId = $this->requiredId($arguments['profile_id'] ?? null, self::TOOL_UPDATE_PROFILE, 'profile_id');
                if (is_array($profileId)) {
                        return $profileId;
                }

                $patch = $this->normalizeProfilePayload($arguments['patch'] ?? [], false);
                if ($patch === []) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_PROFILE, 'invalid_patch', 'The profile patch is empty or contains no supported fields.');
                }

                $plan = [
                        'action' => 'update_profile',
                        'profile_id' => $profileId,
                        'patch' => $patch
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_UPDATE_PROFILE, 'Review the proposed profile update before applying it.', $plan, $arguments);
                }

                $this->profileService->updateProfile($profileId, $patch);

                return $this->resultBuilder->success(
                        self::TOOL_UPDATE_PROFILE,
                        'Profile updated.',
                        [
                                'profile_id' => $profileId,
                                'patch' => $patch
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function archiveProfile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_ARCHIVE_PROFILE, 'capability_denied', 'Profile write tools are not allowed for this resource configuration.');
                }

                $profileId = $this->requiredId($arguments['profile_id'] ?? null, self::TOOL_ARCHIVE_PROFILE, 'profile_id');
                if (is_array($profileId)) {
                        return $profileId;
                }

                $plan = [
                        'action' => 'archive_profile',
                        'profile_id' => $profileId
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_ARCHIVE_PROFILE, 'Review the proposed profile archive before applying it.', $plan, $arguments);
                }

                $this->profileService->archiveProfile($profileId);

                return $this->resultBuilder->success(
                        self::TOOL_ARCHIVE_PROFILE,
                        'Profile archived.',
                        [
                                'profile_id' => $profileId
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function setActiveProfile(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_PROFILE_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_SET_ACTIVE_PROFILE, 'capability_denied', 'Profile write tools are not allowed for this resource configuration.');
                }

                $userId = $this->requiredIntId($arguments['user_id'] ?? null, self::TOOL_SET_ACTIVE_PROFILE, 'user_id');
                if (is_array($userId)) {
                        return $userId;
                }

                $profileId = $this->requiredIntId($arguments['profile_id'] ?? null, self::TOOL_SET_ACTIVE_PROFILE, 'profile_id');
                if (is_array($profileId)) {
                        return $profileId;
                }

                $plan = [
                        'action' => 'set_active_profile',
                        'user_id' => $userId,
                        'profile_id' => $profileId
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_SET_ACTIVE_PROFILE, 'Review the proposed active-profile switch before applying it.', $plan, $arguments);
                }

                $this->profileService->setActiveProfile($userId, $profileId);

                return $this->resultBuilder->success(
                        self::TOOL_SET_ACTIVE_PROFILE,
                        'Active profile updated.',
                        [
                                'user_id' => $userId,
                                'profile_id' => $profileId
                        ]
                );
        }

        private function optionalUserId(mixed $value): ?int {
                if ($value === null || $value === '') {
                        return null;
                }

                return $this->normalizeIntId($value);
        }

        private function normalizeIntId(mixed $value): ?int {
                if (is_int($value) && $value > 0) {
                        return $value;
                }

                if (is_string($value)) {
                        $value = trim($value);
                        if (ctype_digit($value) && (int)$value > 0) {
                                return (int)$value;
                        }
                }

                if (is_float($value) && $value > 0) {
                        return (int)$value;
                }

                return null;
        }

        /**
         * @return int|array<string,mixed>
         */
        private function requiredIntId(mixed $value, string $tool, string $field): int|array {
                $id = $this->normalizeIntId($value);
                if ($id === null) {
                        return $this->resultBuilder->error($tool, 'invalid_' . $field, 'Missing or invalid required field: ' . $field . '.');
                }

                return $id;
        }

        private function normalizeId(mixed $value): int|string|null {
                if (is_int($value) && $value > 0) {
                        return $value;
                }

                if (is_string($value)) {
                        $value = trim($value);
                        if ($value === '') {
                                return null;
                        }

                        return ctype_digit($value) ? (int)$value : $value;
                }

                return null;
        }

        /**
         * @return int|string|array<string,mixed>
         */
        private function requiredId(mixed $value, string $tool, string $field): int|string|array {
                $id = $this->normalizeId($value);
                if ($id === null) {
                        return $this->resultBuilder->error($tool, 'invalid_' . $field, 'Missing or invalid required field: ' . $field . '.');
                }

                return $id;
        }

        /**
         * @return array<string,mixed>
         */
        private function normalizeProfilePayload(mixed $value, bool $requireName): array {
                $value = $this->normalizer->normalizeArray($value);
                $out = [];

                if (array_key_exists('name', $value)) {
                        $name = $this->normalizer->normalizeString($value['name']);
                        if ($name !== '') {
                                $out['name'] = $name;
                        }
                }

                if (array_key_exists('profile', $value)) {
                        if (is_array($value['profile'])) {
                                $json = json_encode($value['profile'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                $out['profile'] = is_string($json) ? $json : '{}';
                        } else {
                                $out['profile'] = $this->normalizer->normalizeString($value['profile']);
                        }
                }

                foreach (['standard', 'protected', 'active', 'archive'] as $field) {
                        if (array_key_exists($field, $value)) {
                                $out[$field] = $this->normalizer->normalizeBool($value[$field], false);
                        }
                }

                if ($requireName && !isset($out['name'])) {
                        return [];
                }

                return $out;
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
