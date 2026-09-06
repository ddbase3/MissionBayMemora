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
use ResourceFoundation\Api\IEntityAccessService;

/**
 * Memora/XRM access and RBAC administration tool.
 *
 * Entry access stays attached directly to users and groups. Roles,
 * permissions, and role-permission assignments are administered separately.
 * Mutating functions are confirmation-aware and should be executed only after
 * the model presented the proposed change to the user and received approval.
 */
class MemoraAccessAgentTool extends AbstractAgentResource implements IAgentTool, IAgentResourceProvider, IAgentPromptProvider {

        private const TOOL_GET_ENTRY_ACCESS = 'memora_get_entry_access';
        private const TOOL_GET_ROLES = 'memora_get_roles';
        private const TOOL_GET_ROLE = 'memora_get_role';
        private const TOOL_GET_PERMISSIONS = 'memora_get_permissions';
        private const TOOL_GET_PERMISSION = 'memora_get_permission';
        private const TOOL_GET_ROLE_PERMISSIONS = 'memora_get_role_permissions';
        private const TOOL_GET_PRINCIPAL_ROLES = 'memora_get_principal_roles';
        private const TOOL_SET_ENTRY_ACCESS = 'memora_set_entry_access';
        private const TOOL_CREATE_ROLE = 'memora_create_role';
        private const TOOL_UPDATE_ROLE = 'memora_update_role';
        private const TOOL_ARCHIVE_ROLE = 'memora_archive_role';
        private const TOOL_CREATE_PERMISSION = 'memora_create_permission';
        private const TOOL_UPDATE_PERMISSION = 'memora_update_permission';
        private const TOOL_ARCHIVE_PERMISSION = 'memora_archive_permission';
        private const TOOL_REPLACE_ROLE_PERMISSIONS = 'memora_replace_role_permissions';
        private const TOOL_REPLACE_PRINCIPAL_ROLES = 'memora_replace_principal_roles';
        private const TOOL_REPLACE_USER_GROUPS = 'memora_replace_user_groups';

        private const MAX_LIMIT = 100;

        private const PRINCIPAL_TYPES = [
                'user',
                'group'
        ];

        public function __construct(
                private readonly IEntityAccessService $accessService,
                private readonly MemoraAgentInputNormalizer $normalizer,
                private readonly MemoraAgentResultBuilder $resultBuilder,
                private readonly MemoraAgentAccessPolicy $accessPolicy,
                ?string $id = null
        ) {
                parent::__construct($id);
        }

        public static function getName(): string {
                return 'memoraaccessagenttool';
        }

        public function getDescription(): string {
                return 'Provides Memora/XRM entry access, role, permission, and membership tools with confirmation-aware administration operations.';
        }

        public function getToolDefinitions(): array {
                return [
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Entry Access',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'readonly'],
                                'priority' => 65,
                                'function' => [
                                        'name' => self::TOOL_GET_ENTRY_ACCESS,
                                        'description' => 'Read direct user and group access grants for one Memora/XRM entry.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [
                                                                'description' => 'Required entry id.'
                                                        ]
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Roles',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'roles', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_ROLES,
                                        'description' => 'List Memora/XRM roles. Roles may include their assigned permissions in the returned rows.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'scope' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional permission scope filter. Matches assigned role permissions, not role fields.'
                                                        ],
                                                        'permission' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional permission name filter. Matches assigned role permissions, not role fields.'
                                                        ],
                                                        'query' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional text filter matched against role fields and assigned permission fields.'
                                                        ],
                                                        'include_archived' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Whether archived roles should be returned. Default false.'
                                                        ],
                                                        'limit' => [
                                                                'type' => 'integer',
                                                                'description' => 'Maximum roles to return. Default: 25. Hard maximum: 100.'
                                                        ],
                                                        'offset' => [
                                                                'type' => 'integer',
                                                                'description' => 'Pagination offset after filtering.'
                                                        ]
                                                ],
                                                'required' => []
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Role',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'role', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_ROLE,
                                        'description' => 'Read one Memora/XRM role by id.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'role_id' => [
                                                                'description' => 'Required role id.'
                                                        ]
                                                ],
                                                'required' => ['role_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Permissions',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'permissions', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_PERMISSIONS,
                                        'description' => 'List Memora/XRM permissions with optional scope, permission and text filters.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'scope' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional permission scope filter, for example entry.'
                                                        ],
                                                        'permission' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional permission name filter, for example admin.'
                                                        ],
                                                        'query' => [
                                                                'type' => 'string',
                                                                'description' => 'Optional text filter matched against permission scope, permission, label and info.'
                                                        ],
                                                        'include_archived' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Whether archived permissions should be returned. Default false.'
                                                        ],
                                                        'limit' => [
                                                                'type' => 'integer',
                                                                'description' => 'Maximum permissions to return. Default: 25. Hard maximum: 100.'
                                                        ],
                                                        'offset' => [
                                                                'type' => 'integer',
                                                                'description' => 'Pagination offset after filtering.'
                                                        ]
                                                ],
                                                'required' => []
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Permission',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'permission', 'readonly'],
                                'priority' => 70,
                                'function' => [
                                        'name' => self::TOOL_GET_PERMISSION,
                                        'description' => 'Read one Memora/XRM permission by id.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'permission_id' => [
                                                                'description' => 'Required permission id.'
                                                        ]
                                                ],
                                                'required' => ['permission_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Role Permissions',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'roles', 'permissions', 'readonly'],
                                'priority' => 65,
                                'function' => [
                                        'name' => self::TOOL_GET_ROLE_PERMISSIONS,
                                        'description' => 'Read permissions assigned to one role.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'role_id' => [
                                                                'description' => 'Required role id.'
                                                        ]
                                                ],
                                                'required' => ['role_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Get Principal Roles',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'roles', 'membership', 'readonly'],
                                'priority' => 65,
                                'function' => [
                                        'name' => self::TOOL_GET_PRINCIPAL_ROLES,
                                        'description' => 'Read roles assigned to one user or group. For users, effective roles and group ids can also be returned.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'principal_type' => [
                                                                'type' => 'string',
                                                                'enum' => self::PRINCIPAL_TYPES,
                                                                'description' => 'Principal type: user or group.'
                                                        ],
                                                        'principal_id' => [
                                                                'description' => 'Required user id or group id.'
                                                        ],
                                                        'include_effective' => [
                                                                'type' => 'boolean',
                                                                'description' => 'For user principals, include roles inherited through groups. Default true.'
                                                        ],
                                                        'include_groups' => [
                                                                'type' => 'boolean',
                                                                'description' => 'For user principals, include direct group ids. Default true.'
                                                        ]
                                                ],
                                                'required' => ['principal_type', 'principal_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Set Entry Access',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'write', 'confirmation'],
                                'priority' => 45,
                                'function' => [
                                        'name' => self::TOOL_SET_ENTRY_ACCESS,
                                        'description' => 'Prepare or execute replacing entry user and/or group access grants. First call with confirm=false to get a review plan. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'entry_id' => [
                                                                'description' => 'Required entry id.'
                                                        ],
                                                        'users' => [
                                                                'type' => 'array',
                                                                'description' => 'Optional complete replacement list for direct user access. Items may be user ids or rows with user_id and mode.'
                                                        ],
                                                        'groups' => [
                                                                'type' => 'array',
                                                                'description' => 'Optional complete replacement list for group access. Items may be group ids or rows with group_id and mode.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute. Default false returns only a proposed access plan.'
                                                        ]
                                                ],
                                                'required' => ['entry_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Create Role',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'role', 'admin', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_CREATE_ROLE,
                                        'description' => 'Prepare or execute creation of a Memora/XRM role. Requires name. Permission ids may be assigned during creation. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => $this->roleSchemaProperties() + [
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['name']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Update Role',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'role', 'admin', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_UPDATE_ROLE,
                                        'description' => 'Prepare or execute a partial role update. Permission ids may be replaced as part of the update. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'role_id' => [
                                                                'description' => 'Required role id.'
                                                        ]
                                                ] + $this->roleSchemaProperties() + [
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['role_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Archive Role',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'role', 'admin', 'destructive', 'confirmation'],
                                'priority' => 30,
                                'function' => [
                                        'name' => self::TOOL_ARCHIVE_ROLE,
                                        'description' => 'Prepare or execute archiving one role. This is treated as a destructive administration action and should be exposed only to trusted clients.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'role_id' => [
                                                                'description' => 'Required role id.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['role_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Create Permission',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'permission', 'admin', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_CREATE_PERMISSION,
                                        'description' => 'Prepare or execute creation of a Memora/XRM permission. Requires scope and permission. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => $this->permissionSchemaProperties() + [
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['scope', 'permission']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Update Permission',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'permission', 'admin', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_UPDATE_PERMISSION,
                                        'description' => 'Prepare or execute a partial permission update. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'permission_id' => [
                                                                'description' => 'Required permission id.'
                                                        ]
                                                ] + $this->permissionSchemaProperties() + [
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['permission_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Archive Permission',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'permission', 'admin', 'destructive', 'confirmation'],
                                'priority' => 30,
                                'function' => [
                                        'name' => self::TOOL_ARCHIVE_PERMISSION,
                                        'description' => 'Prepare or execute archiving one permission. This is treated as a destructive administration action and should be exposed only to trusted clients.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'permission_id' => [
                                                                'description' => 'Required permission id.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['permission_id']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Replace Role Permissions',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'roles', 'permissions', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_REPLACE_ROLE_PERMISSIONS,
                                        'description' => 'Prepare or execute replacing all permissions assigned to one role. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'role_id' => [
                                                                'description' => 'Required role id.'
                                                        ],
                                                        'permission_ids' => [
                                                                'type' => 'array',
                                                                'description' => 'Complete replacement list of permission ids.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['role_id', 'permission_ids']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Replace Principal Roles',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'roles', 'membership', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_REPLACE_PRINCIPAL_ROLES,
                                        'description' => 'Prepare or execute replacing all roles assigned directly to one user or group. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'principal_type' => [
                                                                'type' => 'string',
                                                                'enum' => self::PRINCIPAL_TYPES,
                                                                'description' => 'Principal type: user or group.'
                                                        ],
                                                        'principal_id' => [
                                                                'description' => 'Required user id or group id.'
                                                        ],
                                                        'role_ids' => [
                                                                'type' => 'array',
                                                                'description' => 'Complete replacement list of role ids.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['principal_type', 'principal_id', 'role_ids']
                                        ]
                                ]
                        ],
                        [
                                'type' => 'function',
                                'label' => 'Memora Replace User Groups',
                                'category' => 'memora',
                                'tags' => ['memora', 'xrm', 'access', 'groups', 'membership', 'write', 'confirmation'],
                                'priority' => 40,
                                'function' => [
                                        'name' => self::TOOL_REPLACE_USER_GROUPS,
                                        'description' => 'Prepare or execute replacing all group memberships for one user. Execute only after user approval with confirm=true.',
                                        'parameters' => [
                                                'type' => 'object',
                                                'properties' => [
                                                        'user_id' => [
                                                                'description' => 'Required user id.'
                                                        ],
                                                        'group_ids' => [
                                                                'type' => 'array',
                                                                'description' => 'Complete replacement list of group ids.'
                                                        ],
                                                        'confirm' => [
                                                                'type' => 'boolean',
                                                                'description' => 'Must be true after user confirmation to execute.'
                                                        ]
                                                ],
                                                'required' => ['user_id', 'group_ids']
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
                                self::TOOL_GET_ENTRY_ACCESS => $this->getEntryAccess($arguments, $context),
                                self::TOOL_GET_ROLES => $this->getRoles($arguments, $context),
                                self::TOOL_GET_ROLE => $this->getRole($arguments, $context),
                                self::TOOL_GET_PERMISSIONS => $this->getPermissions($arguments, $context),
                                self::TOOL_GET_PERMISSION => $this->getPermission($arguments, $context),
                                self::TOOL_GET_ROLE_PERMISSIONS => $this->getRolePermissions($arguments, $context),
                                self::TOOL_GET_PRINCIPAL_ROLES => $this->getPrincipalRoles($arguments, $context),
                                self::TOOL_SET_ENTRY_ACCESS => $this->setEntryAccess($arguments, $context),
                                self::TOOL_CREATE_ROLE => $this->createRole($arguments, $context),
                                self::TOOL_UPDATE_ROLE => $this->updateRole($arguments, $context),
                                self::TOOL_ARCHIVE_ROLE => $this->archiveRole($arguments, $context),
                                self::TOOL_CREATE_PERMISSION => $this->createPermission($arguments, $context),
                                self::TOOL_UPDATE_PERMISSION => $this->updatePermission($arguments, $context),
                                self::TOOL_ARCHIVE_PERMISSION => $this->archivePermission($arguments, $context),
                                self::TOOL_REPLACE_ROLE_PERMISSIONS => $this->replaceRolePermissions($arguments, $context),
                                self::TOOL_REPLACE_PRINCIPAL_ROLES => $this->replacePrincipalRoles($arguments, $context),
                                self::TOOL_REPLACE_USER_GROUPS => $this->replaceUserGroups($arguments, $context),
                                default => throw new \InvalidArgumentException('Unsupported tool: ' . $name)
                        };
                } catch (\Throwable $e) {
                        return $this->resultBuilder->error(
                                $name,
                                'tool_error',
                                'The Memora access tool failed to process the request.',
                                [],
                                ['error' => $e->getMessage()]
                        );
                }
        }

        public function getResourceDefinitions(IAgentContext $context): array {
                return [
                        [
                                'uri' => 'memora://roles',
                                'name' => 'memora-roles',
                                'title' => 'Memora Roles',
                                'description' => 'Lists Memora/XRM roles visible to the current runtime.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://role/{role_id}',
                                'name' => 'memora-role-template',
                                'title' => 'Memora Role',
                                'description' => 'Reads one Memora/XRM role by id.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uri' => 'memora://permissions',
                                'name' => 'memora-permissions',
                                'title' => 'Memora Permissions',
                                'description' => 'Lists Memora/XRM permissions visible to the current runtime.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://permission/{permission_id}',
                                'name' => 'memora-permission-template',
                                'title' => 'Memora Permission',
                                'description' => 'Reads one Memora/XRM permission by id.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://role/{role_id}/permissions',
                                'name' => 'memora-role-permissions-template',
                                'title' => 'Memora Role Permissions',
                                'description' => 'Reads permissions assigned to one Memora/XRM role.',
                                'mimeType' => 'application/json'
                        ],
                        [
                                'uriTemplate' => 'memora://entry/{entry_id}/access',
                                'name' => 'memora-entry-access-template',
                                'title' => 'Memora Entry Access',
                                'description' => 'Reads direct user and group access grants for one Memora/XRM entry.',
                                'mimeType' => 'application/json'
                        ]
                ];
        }

        public function readResource(string $uri, IAgentContext $context): ?array {
                if ($uri === 'memora://roles') {
                        return $this->resultBuilder->resource($uri, $this->getRoles(['limit' => self::MAX_LIMIT], $context));
                }

                if ($uri === 'memora://permissions') {
                        return $this->resultBuilder->resource($uri, $this->getPermissions(['limit' => self::MAX_LIMIT], $context));
                }

                $rolePermissionPrefix = 'memora://role/';
                $rolePermissionSuffix = '/permissions';
                if (str_starts_with($uri, $rolePermissionPrefix) && str_ends_with($uri, $rolePermissionSuffix)) {
                        $roleId = rawurldecode(substr($uri, strlen($rolePermissionPrefix), -strlen($rolePermissionSuffix)));
                        return $this->resultBuilder->resource($uri, $this->getRolePermissions(['role_id' => $roleId], $context));
                }

                $rolePrefix = 'memora://role/';
                if (str_starts_with($uri, $rolePrefix)) {
                        $roleId = rawurldecode(substr($uri, strlen($rolePrefix)));
                        return $this->resultBuilder->resource($uri, $this->getRole(['role_id' => $roleId], $context));
                }

                $permissionPrefix = 'memora://permission/';
                if (str_starts_with($uri, $permissionPrefix)) {
                        $permissionId = rawurldecode(substr($uri, strlen($permissionPrefix)));
                        return $this->resultBuilder->resource($uri, $this->getPermission(['permission_id' => $permissionId], $context));
                }

                $entryPrefix = 'memora://entry/';
                $entrySuffix = '/access';
                if (str_starts_with($uri, $entryPrefix) && str_ends_with($uri, $entrySuffix)) {
                        $entryId = rawurldecode(substr($uri, strlen($entryPrefix), -strlen($entrySuffix)));
                        return $this->resultBuilder->resource($uri, $this->getEntryAccess(['entry_id' => $entryId], $context));
                }

                return null;
        }

        public function getPromptDefinitions(IAgentContext $context): array {
                return [
                        [
                                'name' => 'memora_explain_access',
                                'title' => 'Explain Memora Access',
                                'description' => 'Guide the model to inspect and explain direct entry access and effective user roles.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ],
                                        [ 'name' => 'user_id', 'description' => 'Optional user id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_manage_entry_access',
                                'title' => 'Manage Memora Entry Access With Confirmation',
                                'description' => 'Guide the model to prepare an entry access replacement and wait for user confirmation before executing.',
                                'arguments' => [
                                        [ 'name' => 'entry_id', 'description' => 'Optional entry id.', 'required' => false ]
                                ]
                        ],
                        [
                                'name' => 'memora_manage_roles',
                                'title' => 'Manage Memora Roles With Confirmation',
                                'description' => 'Guide the model to list, create, update or archive roles safely.',
                                'arguments' => []
                        ],
                        [
                                'name' => 'memora_manage_permissions',
                                'title' => 'Manage Memora Permissions With Confirmation',
                                'description' => 'Guide the model to list, create, update, archive or assign permissions safely.',
                                'arguments' => []
                        ],
                        [
                                'name' => 'memora_assign_roles',
                                'title' => 'Assign Memora Roles With Confirmation',
                                'description' => 'Guide the model to prepare direct user/group role assignment changes and wait for confirmation before executing.',
                                'arguments' => [
                                        [ 'name' => 'principal_type', 'description' => 'Optional principal type: user or group.', 'required' => false ],
                                        [ 'name' => 'principal_id', 'description' => 'Optional principal id.', 'required' => false ]
                                ]
                        ]
                ];
        }

        public function getPrompt(string $name, array $arguments, IAgentContext $context): ?array {
                $name = $this->normalizer->normalizeToken($name);

                if ($name === 'memora_explain_access') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $userId = $this->normalizer->normalizeString($arguments['user_id'] ?? '');
                        $lines = [
                                'Use memora_get_entry_access to inspect direct user and group entry grants.',
                                'Use memora_get_principal_roles with principal_type "user" when the user asks which roles a person has.',
                                'Use memora_get_role_permissions to inspect what a role grants.',
                                'Explain separately direct entry ACL, direct roles, inherited roles, and role permissions.'
                        ];
                        if ($entryId !== '') $lines[] = 'Preferred entry id: ' . $entryId;
                        if ($userId !== '') $lines[] = 'Preferred user id: ' . $userId;

                        return $this->resultBuilder->prompt('Explain Memora/XRM access.', implode("\n", $lines));
                }

                if ($name === 'memora_manage_entry_access') {
                        $entryId = $this->normalizer->normalizeString($arguments['entry_id'] ?? '');
                        $lines = [
                                'Use memora_get_entry_access first to inspect the current user and group grants.',
                                'Prepare the complete replacement lists for only the access categories the user wants to change.',
                                'Call memora_set_entry_access with confirm=false first and present the returned plan to the user.',
                                'Do not call memora_set_entry_access with confirm=true until the user explicitly approves the exact replacement plan.',
                                'Do not use roles as entry access subjects; roles are administered separately through permissions.'
                        ];
                        if ($entryId !== '') $lines[] = 'Preferred entry id: ' . $entryId;

                        return $this->resultBuilder->prompt('Manage Memora entry access with confirmation.', implode("\n", $lines));
                }

                if ($name === 'memora_manage_roles') {
                        return $this->resultBuilder->prompt(
                                'Manage Memora roles with confirmation.',
                                implode("\n", [
                                        'Use memora_get_roles before creating or changing roles so the user can compare existing names.',
                                        'Use memora_get_permissions before assigning permissions to a role.',
                                        'Use memora_create_role, memora_update_role, or memora_replace_role_permissions with confirm=false first.',
                                        'Present the role plan clearly. Execute with confirm=true only after explicit approval.',
                                        'Use memora_archive_role only for trusted administration workflows.'
                                ])
                        );
                }

                if ($name === 'memora_manage_permissions') {
                        return $this->resultBuilder->prompt(
                                'Manage Memora permissions with confirmation.',
                                implode("\n", [
                                        'Use memora_get_permissions before creating or changing permissions so the user can compare existing scope/permission pairs.',
                                        'Use memora_create_permission or memora_update_permission with confirm=false first.',
                                        'Use memora_replace_role_permissions with confirm=false when assigning permissions to a role.',
                                        'Present the permission plan clearly. Execute with confirm=true only after explicit approval.',
                                        'Use memora_archive_permission only for trusted administration workflows.'
                                ])
                        );
                }

                if ($name === 'memora_assign_roles') {
                        $principalType = $this->normalizer->normalizeToken((string)($arguments['principal_type'] ?? ''), self::PRINCIPAL_TYPES, '');
                        $principalId = $this->normalizer->normalizeString($arguments['principal_id'] ?? '');
                        $lines = [
                                'Use memora_get_roles to identify available roles.',
                                'Use memora_get_principal_roles to inspect current direct and effective role assignments.',
                                'Prepare the complete replacement role id list.',
                                'Call memora_replace_principal_roles with confirm=false first and present the change to the user.',
                                'Execute with confirm=true only after explicit user approval.'
                        ];
                        if ($principalType !== '') $lines[] = 'Preferred principal type: ' . $principalType;
                        if ($principalId !== '') $lines[] = 'Preferred principal id: ' . $principalId;

                        return $this->resultBuilder->prompt('Assign Memora roles with confirmation.', implode("\n", $lines));
                }

                return null;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getEntryAccess(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ENTRY_ACCESS, 'capability_denied', 'Access reading is not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_ENTRY_ACCESS, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_ENTRY_ACCESS,
                        'Entry access loaded.',
                        [
                                'entry_id' => $entryId,
                                'access' => $this->accessService->getEntryAccess($entryId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getRoles(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLES, 'capability_denied', 'Role reading is not allowed for this tool configuration.');
                }

                $includeArchived = $this->normalizer->normalizeBool($arguments['include_archived'] ?? false, false);
                $scope = $this->normalizer->normalizeToken((string)($arguments['scope'] ?? ''));
                $permission = $this->normalizer->normalizeToken((string)($arguments['permission'] ?? ''));
                $query = mb_strtolower($this->normalizer->normalizeString($arguments['query'] ?? ''));
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);

                $roles = $this->accessService->getRoles($includeArchived);
                $roles = array_values(array_filter($roles, function(array $role) use ($scope, $permission, $query): bool {
                        $permissions = is_array($role['permissions'] ?? null) ? $role['permissions'] : [];

                        if ($scope !== '' && !$this->roleHasPermissionField($permissions, 'scope', $scope)) {
                                return false;
                        }

                        if ($permission !== '' && !$this->roleHasPermissionField($permissions, 'permission', $permission)) {
                                return false;
                        }

                        if ($query === '') {
                                return true;
                        }

                        $haystackParts = [
                                (string)($role['name'] ?? ''),
                                (string)($role['label'] ?? ''),
                                (string)($role['info'] ?? '')
                        ];

                        foreach ($permissions as $permissionRow) {
                                if (!is_array($permissionRow)) continue;
                                $haystackParts[] = (string)($permissionRow['scope'] ?? '');
                                $haystackParts[] = (string)($permissionRow['permission'] ?? '');
                                $haystackParts[] = (string)($permissionRow['label'] ?? '');
                                $haystackParts[] = (string)($permissionRow['info'] ?? '');
                        }

                        return str_contains(mb_strtolower(implode(' ', $haystackParts)), $query);
                }));

                usort($roles, static function(array $a, array $b): int {
                        return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
                });

                $total = count($roles);
                $items = array_slice($roles, $offset, $limit);

                return $this->resultBuilder->success(
                        self::TOOL_GET_ROLES,
                        $items !== [] ? 'Roles loaded.' : 'No roles found.',
                        [
                                'filters' => [
                                        'scope' => $scope,
                                        'permission' => $permission,
                                        'query' => $query,
                                        'include_archived' => $includeArchived
                                ]
                        ],
                        $items,
                        $this->resultBuilder->paging($offset, $limit, $total, count($items), $total > ($offset + count($items)))
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getRole(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLE, 'capability_denied', 'Role reading is not allowed for this tool configuration.');
                }

                $roleId = $arguments['role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLE, 'missing_role_id', 'Missing required parameter: role_id.');
                }

                $role = $this->accessService->getRole($roleId);
                if ($role === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLE, 'role_not_found', 'Role not found.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_ROLE,
                        'Role loaded.',
                        [
                                'role_id' => $roleId,
                                'role' => $role
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getPermissions(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_PERMISSIONS, 'capability_denied', 'Permission reading is not allowed for this tool configuration.');
                }

                $includeArchived = $this->normalizer->normalizeBool($arguments['include_archived'] ?? false, false);
                $scope = $this->normalizer->normalizeToken((string)($arguments['scope'] ?? ''));
                $permission = $this->normalizer->normalizeToken((string)($arguments['permission'] ?? ''));
                $query = mb_strtolower($this->normalizer->normalizeString($arguments['query'] ?? ''));
                $limit = $this->normalizer->normalizeLimit($arguments['limit'] ?? 25, 25, self::MAX_LIMIT);
                $offset = $this->normalizer->normalizeOffset($arguments['offset'] ?? 0);

                $permissions = $this->accessService->getPermissions($includeArchived);
                $permissions = array_values(array_filter($permissions, function(array $row) use ($scope, $permission, $query): bool {
                        if ($scope !== '' && $this->normalizer->normalizeToken((string)($row['scope'] ?? '')) !== $scope) {
                                return false;
                        }

                        if ($permission !== '' && $this->normalizer->normalizeToken((string)($row['permission'] ?? '')) !== $permission) {
                                return false;
                        }

                        if ($query === '') {
                                return true;
                        }

                        $haystack = mb_strtolower(implode(' ', [
                                (string)($row['scope'] ?? ''),
                                (string)($row['permission'] ?? ''),
                                (string)($row['label'] ?? ''),
                                (string)($row['info'] ?? '')
                        ]));

                        return str_contains($haystack, $query);
                }));

                usort($permissions, static function(array $a, array $b): int {
                        $scopeCompare = strcmp((string)($a['scope'] ?? ''), (string)($b['scope'] ?? ''));
                        if ($scopeCompare !== 0) {
                                return $scopeCompare;
                        }

                        return strcmp((string)($a['permission'] ?? ''), (string)($b['permission'] ?? ''));
                });

                $total = count($permissions);
                $items = array_slice($permissions, $offset, $limit);

                return $this->resultBuilder->success(
                        self::TOOL_GET_PERMISSIONS,
                        $items !== [] ? 'Permissions loaded.' : 'No permissions found.',
                        [
                                'filters' => [
                                        'scope' => $scope,
                                        'permission' => $permission,
                                        'query' => $query,
                                        'include_archived' => $includeArchived
                                ]
                        ],
                        $items,
                        $this->resultBuilder->paging($offset, $limit, $total, count($items), $total > ($offset + count($items)))
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getPermission(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_PERMISSION, 'capability_denied', 'Permission reading is not allowed for this tool configuration.');
                }

                $permissionId = $arguments['permission_id'] ?? null;
                if ($permissionId === null || $permissionId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_PERMISSION, 'missing_permission_id', 'Missing required parameter: permission_id.');
                }

                $permission = $this->accessService->getPermission($permissionId);
                if ($permission === null) {
                        return $this->resultBuilder->error(self::TOOL_GET_PERMISSION, 'permission_not_found', 'Permission not found.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_PERMISSION,
                        'Permission loaded.',
                        [
                                'permission_id' => $permissionId,
                                'permission' => $permission
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getRolePermissions(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLE_PERMISSIONS, 'capability_denied', 'Role permission reading is not allowed for this tool configuration.');
                }

                $roleId = $arguments['role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                        return $this->resultBuilder->error(self::TOOL_GET_ROLE_PERMISSIONS, 'missing_role_id', 'Missing required parameter: role_id.');
                }

                return $this->resultBuilder->success(
                        self::TOOL_GET_ROLE_PERMISSIONS,
                        'Role permissions loaded.',
                        [
                                'role_id' => $roleId,
                                'permissions' => $this->accessService->getRolePermissions($roleId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function getPrincipalRoles(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_READ, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_GET_PRINCIPAL_ROLES, 'capability_denied', 'Principal role reading is not allowed for this tool configuration.');
                }

                $principal = $this->normalizePrincipal($arguments, self::TOOL_GET_PRINCIPAL_ROLES);
                if (isset($principal['error'])) {
                        return $principal['error'];
                }

                $type = $principal['type'];
                $id = $principal['id'];
                $data = [
                        'principal_type' => $type,
                        'principal_id' => $id
                ];

                if ($type === 'user') {
                        $data['roles'] = $this->accessService->getUserRoles($id);

                        if ($this->normalizer->normalizeBool($arguments['include_effective'] ?? true, true)) {
                                $data['effective_roles'] = $this->accessService->getEffectiveUserRoles($id);
                        }

                        if ($this->normalizer->normalizeBool($arguments['include_groups'] ?? true, true)) {
                                $data['group_ids'] = $this->accessService->getUserGroups($id);
                        }
                } else {
                        $data['roles'] = $this->accessService->getGroupRoles($id);
                }

                return $this->resultBuilder->success(self::TOOL_GET_PRINCIPAL_ROLES, 'Principal roles loaded.', $data);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function setEntryAccess(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ACCESS_WRITE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_SET_ENTRY_ACCESS, 'capability_denied', 'Entry access changes are not allowed for this tool configuration.');
                }

                $entryId = $arguments['entry_id'] ?? null;
                if ($entryId === null || $entryId === '') {
                        return $this->resultBuilder->error(self::TOOL_SET_ENTRY_ACCESS, 'missing_entry_id', 'Missing required parameter: entry_id.');
                }

                $plan = [
                        'operation' => 'set_entry_access',
                        'entry_id' => $entryId,
                        'replace' => []
                ];

                if (array_key_exists('users', $arguments)) {
                        $plan['replace']['users'] = $this->normalizeAccessRows($arguments['users'], 'user_id', true);
                }

                if (array_key_exists('groups', $arguments)) {
                        $plan['replace']['groups'] = $this->normalizeAccessRows($arguments['groups'], 'group_id', true);
                }

                if (array_key_exists('roles', $arguments)) {
                        return $this->resultBuilder->error(self::TOOL_SET_ENTRY_ACCESS, 'role_access_removed', 'Entry role access is no longer supported. Use roles and permissions for RBAC, and user/group access for entry ACL.');
                }

                if ($plan['replace'] === []) {
                        return $this->resultBuilder->error(self::TOOL_SET_ENTRY_ACCESS, 'empty_access_change', 'Provide users and/or groups to replace.');
                }

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_SET_ENTRY_ACCESS, 'Entry access replacement requires user confirmation before execution.', $plan, $arguments);
                }

                if (array_key_exists('users', $plan['replace'])) {
                        $this->accessService->replaceEntryUserAccess($entryId, $plan['replace']['users']);
                }

                if (array_key_exists('groups', $plan['replace'])) {
                        $this->accessService->replaceEntryGroupAccess($entryId, $plan['replace']['groups']);
                }

                return $this->resultBuilder->success(
                        self::TOOL_SET_ENTRY_ACCESS,
                        'Entry access replaced.',
                        [
                                'entry_id' => $entryId,
                                'access' => $this->accessService->getEntryAccess($entryId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createRole(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_ROLE, 'capability_denied', 'Role administration is not allowed for this tool configuration.');
                }

                $role = $this->normalizeRoleData($arguments, true);
                if ($role === []) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_ROLE, 'invalid_role', 'Role creation requires name.');
                }

                $plan = [
                        'operation' => 'create_role',
                        'role' => $role
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_CREATE_ROLE, 'Role creation requires user confirmation before execution.', $plan, $arguments);
                }

                $roleId = $this->accessService->createRole($role);

                return $this->resultBuilder->success(
                        self::TOOL_CREATE_ROLE,
                        'Role created.',
                        [
                                'role_id' => $roleId,
                                'role' => $this->accessService->getRole($roleId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function updateRole(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ROLE, 'capability_denied', 'Role administration is not allowed for this tool configuration.');
                }

                $roleId = $arguments['role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ROLE, 'missing_role_id', 'Missing required parameter: role_id.');
                }

                $patch = $this->normalizeRoleData($arguments, false);
                if ($patch === []) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_ROLE, 'empty_role_patch', 'Provide at least one supported role field to update.');
                }

                $plan = [
                        'operation' => 'update_role',
                        'role_id' => $roleId,
                        'patch' => $patch
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_UPDATE_ROLE, 'Role update requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->updateRole($roleId, $patch);

                return $this->resultBuilder->success(
                        self::TOOL_UPDATE_ROLE,
                        'Role updated.',
                        [
                                'role_id' => $roleId,
                                'role' => $this->accessService->getRole($roleId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function archiveRole(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)
                        || !$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DESTRUCTIVE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_ARCHIVE_ROLE, 'capability_denied', 'Role archiving is not allowed for this tool configuration.');
                }

                $roleId = $arguments['role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                        return $this->resultBuilder->error(self::TOOL_ARCHIVE_ROLE, 'missing_role_id', 'Missing required parameter: role_id.');
                }

                $plan = [
                        'operation' => 'archive_role',
                        'role_id' => $roleId,
                        'role_before' => $this->accessService->getRole($roleId)
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_ARCHIVE_ROLE, 'Role archiving requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->archiveRole($roleId);

                return $this->resultBuilder->success(self::TOOL_ARCHIVE_ROLE, 'Role archived.', ['role_id' => $roleId]);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function createPermission(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_PERMISSION, 'capability_denied', 'Permission administration is not allowed for this tool configuration.');
                }

                $permission = $this->normalizePermissionData($arguments, true);
                if ($permission === []) {
                        return $this->resultBuilder->error(self::TOOL_CREATE_PERMISSION, 'invalid_permission', 'Permission creation requires scope and permission.');
                }

                $plan = [
                        'operation' => 'create_permission',
                        'permission' => $permission
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_CREATE_PERMISSION, 'Permission creation requires user confirmation before execution.', $plan, $arguments);
                }

                $permissionId = $this->accessService->createPermission($permission);

                return $this->resultBuilder->success(
                        self::TOOL_CREATE_PERMISSION,
                        'Permission created.',
                        [
                                'permission_id' => $permissionId,
                                'permission' => $this->accessService->getPermission($permissionId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function updatePermission(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_PERMISSION, 'capability_denied', 'Permission administration is not allowed for this tool configuration.');
                }

                $permissionId = $arguments['permission_id'] ?? null;
                if ($permissionId === null || $permissionId === '') {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_PERMISSION, 'missing_permission_id', 'Missing required parameter: permission_id.');
                }

                $patch = $this->normalizePermissionData($arguments, false);
                if ($patch === []) {
                        return $this->resultBuilder->error(self::TOOL_UPDATE_PERMISSION, 'empty_permission_patch', 'Provide at least one supported permission field to update.');
                }

                $plan = [
                        'operation' => 'update_permission',
                        'permission_id' => $permissionId,
                        'patch' => $patch
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_UPDATE_PERMISSION, 'Permission update requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->updatePermission($permissionId, $patch);

                return $this->resultBuilder->success(
                        self::TOOL_UPDATE_PERMISSION,
                        'Permission updated.',
                        [
                                'permission_id' => $permissionId,
                                'permission' => $this->accessService->getPermission($permissionId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function archivePermission(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)
                        || !$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_DESTRUCTIVE, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_ARCHIVE_PERMISSION, 'capability_denied', 'Permission archiving is not allowed for this tool configuration.');
                }

                $permissionId = $arguments['permission_id'] ?? null;
                if ($permissionId === null || $permissionId === '') {
                        return $this->resultBuilder->error(self::TOOL_ARCHIVE_PERMISSION, 'missing_permission_id', 'Missing required parameter: permission_id.');
                }

                $plan = [
                        'operation' => 'archive_permission',
                        'permission_id' => $permissionId,
                        'permission_before' => $this->accessService->getPermission($permissionId)
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_ARCHIVE_PERMISSION, 'Permission archiving requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->archivePermission($permissionId);

                return $this->resultBuilder->success(self::TOOL_ARCHIVE_PERMISSION, 'Permission archived.', ['permission_id' => $permissionId]);
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function replaceRolePermissions(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_ROLE_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_ROLE_PERMISSIONS, 'capability_denied', 'Role permission changes are not allowed for this tool configuration.');
                }

                $roleId = $arguments['role_id'] ?? null;
                if ($roleId === null || $roleId === '') {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_ROLE_PERMISSIONS, 'missing_role_id', 'Missing required parameter: role_id.');
                }

                $permissionIds = $this->normalizer->normalizeIdList($arguments['permission_ids'] ?? []);
                $plan = [
                        'operation' => 'replace_role_permissions',
                        'role_id' => $roleId,
                        'permission_ids' => $permissionIds
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_REPLACE_ROLE_PERMISSIONS, 'Role permission replacement requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->replaceRolePermissions($roleId, $permissionIds);

                return $this->resultBuilder->success(
                        self::TOOL_REPLACE_ROLE_PERMISSIONS,
                        'Role permissions replaced.',
                        [
                                'role_id' => $roleId,
                                'permissions' => $this->accessService->getRolePermissions($roleId)
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function replacePrincipalRoles(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_MEMBERSHIP_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_PRINCIPAL_ROLES, 'capability_denied', 'Role assignment changes are not allowed for this tool configuration.');
                }

                $principal = $this->normalizePrincipal($arguments, self::TOOL_REPLACE_PRINCIPAL_ROLES);
                if (isset($principal['error'])) {
                        return $principal['error'];
                }

                $roleIds = $this->normalizer->normalizeIdList($arguments['role_ids'] ?? []);
                $plan = [
                        'operation' => 'replace_principal_roles',
                        'principal_type' => $principal['type'],
                        'principal_id' => $principal['id'],
                        'role_ids' => $roleIds
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_REPLACE_PRINCIPAL_ROLES, 'Principal role replacement requires user confirmation before execution.', $plan, $arguments);
                }

                if ($principal['type'] === 'user') {
                        $this->accessService->replaceUserRoles($principal['id'], $roleIds);
                        $roles = $this->accessService->getUserRoles($principal['id']);
                } else {
                        $this->accessService->replaceGroupRoles($principal['id'], $roleIds);
                        $roles = $this->accessService->getGroupRoles($principal['id']);
                }

                return $this->resultBuilder->success(
                        self::TOOL_REPLACE_PRINCIPAL_ROLES,
                        'Principal roles replaced.',
                        [
                                'principal_type' => $principal['type'],
                                'principal_id' => $principal['id'],
                                'roles' => $roles
                        ]
                );
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function replaceUserGroups(array $arguments, IAgentContext $context): array {
                if (!$this->accessPolicy->isAllowed(MemoraAgentAccessPolicy::CAPABILITY_MEMBERSHIP_ADMIN, $this->config, $context)) {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_USER_GROUPS, 'capability_denied', 'User group changes are not allowed for this tool configuration.');
                }

                $userId = $arguments['user_id'] ?? null;
                if ($userId === null || $userId === '') {
                        return $this->resultBuilder->error(self::TOOL_REPLACE_USER_GROUPS, 'missing_user_id', 'Missing required parameter: user_id.');
                }

                $groupIds = $this->normalizer->normalizeIdList($arguments['group_ids'] ?? []);
                $plan = [
                        'operation' => 'replace_user_groups',
                        'user_id' => $userId,
                        'group_ids' => $groupIds
                ];

                if ($this->needsConfirmation($arguments)) {
                        return $this->confirmation(self::TOOL_REPLACE_USER_GROUPS, 'User group replacement requires user confirmation before execution.', $plan, $arguments);
                }

                $this->accessService->replaceUserGroups($userId, $groupIds);

                return $this->resultBuilder->success(
                        self::TOOL_REPLACE_USER_GROUPS,
                        'User groups replaced.',
                        [
                                'user_id' => $userId,
                                'group_ids' => $this->accessService->getUserGroups($userId)
                        ]
                );
        }

        /**
         * @return array<string,array<string,mixed>>
         */
        private function roleSchemaProperties(): array {
                return [
                        'name' => [
                                'type' => 'string',
                                'description' => 'Stable technical role name.'
                        ],
                        'label' => [
                                'type' => 'string',
                                'description' => 'Optional human-readable label.'
                        ],
                        'info' => [
                                'type' => 'string',
                                'description' => 'Optional role description or notes.'
                        ],
                        'permission_ids' => [
                                'type' => 'array',
                                'description' => 'Optional complete permission id list to assign to the role.'
                        ],
                        'permissions' => [
                                'type' => 'array',
                                'description' => 'Optional permission rows to create or resolve while creating/updating the role.'
                        ],
                        'archive' => [
                                'type' => 'boolean',
                                'description' => 'Optional archive flag.'
                        ]
                ];
        }

        /**
         * @return array<string,array<string,mixed>>
         */
        private function permissionSchemaProperties(): array {
                return [
                        'scope' => [
                                'type' => 'string',
                                'description' => 'Permission scope, for example entry, user, group, role or system.'
                        ],
                        'permission' => [
                                'type' => 'string',
                                'description' => 'Permission name, for example admin, create, manage or assign.'
                        ],
                        'label' => [
                                'type' => 'string',
                                'description' => 'Optional human-readable label.'
                        ],
                        'info' => [
                                'type' => 'string',
                                'description' => 'Optional permission description or notes.'
                        ],
                        'archive' => [
                                'type' => 'boolean',
                                'description' => 'Optional archive flag.'
                        ]
                ];
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function normalizePrincipal(array $arguments, string $tool): array {
                $type = $this->normalizer->normalizeToken((string)($arguments['principal_type'] ?? ''), self::PRINCIPAL_TYPES, '');
                if ($type === '') {
                        return [
                                'error' => $this->resultBuilder->error($tool, 'invalid_principal_type', 'principal_type must be user or group.')
                        ];
                }

                $id = $arguments['principal_id'] ?? null;
                if ($id === null || $id === '') {
                        return [
                                'error' => $this->resultBuilder->error($tool, 'missing_principal_id', 'Missing required parameter: principal_id.')
                        ];
                }

                return [
                        'type' => $type,
                        'id' => $id
                ];
        }

        /**
         * @return array<int,array<string,mixed>>
         */
        private function normalizeAccessRows(mixed $value, string $idKey, bool $includeMode): array {
                if ($value === null || $value === '') {
                        return [];
                }

                if (!is_array($value)) {
                        $value = [$value];
                }

                $rows = [];
                foreach ($value as $item) {
                        if (is_array($item)) {
                                $id = $item[$idKey] ?? $item['id'] ?? null;
                                if ($id === null || $id === '') {
                                        continue;
                                }

                                $row = [
                                        $idKey => $this->normalizeId($id)
                                ];

                                if ($includeMode) {
                                        $mode = isset($item['mode']) ? trim((string)$item['mode']) : 'visitor';
                                        $row['mode'] = $mode !== '' ? $mode : 'visitor';
                                }

                                $rows[] = $row;
                                continue;
                        }

                        if ($item === null || $item === '') {
                                continue;
                        }

                        $row = [
                                $idKey => $this->normalizeId($item)
                        ];

                        if ($includeMode) {
                                $row['mode'] = 'visitor';
                        }

                        $rows[] = $row;
                }

                return $this->deduplicateRows($rows, $idKey, $includeMode);
        }

        private function normalizeId(mixed $value): int|string {
                if (is_int($value)) {
                        return $value;
                }

                $value = trim((string)$value);
                return ctype_digit($value) ? (int)$value : $value;
        }

        /**
         * @param array<int,array<string,mixed>> $rows
         * @return array<int,array<string,mixed>>
         */
        private function deduplicateRows(array $rows, string $idKey, bool $includeMode): array {
                $seen = [];
                $out = [];

                foreach ($rows as $row) {
                        $id = (string)($row[$idKey] ?? '');
                        $mode = $includeMode ? ':' . (string)($row['mode'] ?? '') : '';
                        $key = $id . $mode;
                        if ($id === '' || isset($seen[$key])) {
                                continue;
                        }

                        $seen[$key] = true;
                        $out[] = $row;
                }

                return $out;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function normalizeRoleData(array $arguments, bool $requireCore): array {
                $role = [];

                foreach (['name', 'label', 'info'] as $key) {
                        if (!array_key_exists($key, $arguments)) {
                                continue;
                        }

                        $value = $this->normalizer->normalizeString($arguments[$key] ?? '');
                        if ($value !== '') {
                                $role[$key] = $value;
                        }
                }

                if (array_key_exists('permission_ids', $arguments)) {
                        $role['permission_ids'] = $this->normalizer->normalizeIdList($arguments['permission_ids']);
                }

                if (array_key_exists('permissions', $arguments) && is_array($arguments['permissions'])) {
                        $role['permissions'] = $this->normalizePermissionRows($arguments['permissions']);
                }

                if (array_key_exists('archive', $arguments)) {
                        $role['archive'] = $this->normalizer->normalizeBool($arguments['archive'], false) ? 1 : 0;
                }

                if ($requireCore && (!isset($role['name']) || $role['name'] === '')) {
                        return [];
                }

                return $role;
        }

        /**
         * @param array<string,mixed> $arguments
         * @return array<string,mixed>
         */
        private function normalizePermissionData(array $arguments, bool $requireCore): array {
                $permission = [];

                foreach (['scope', 'permission', 'label', 'info'] as $key) {
                        if (!array_key_exists($key, $arguments)) {
                                continue;
                        }

                        $value = $key === 'scope' || $key === 'permission'
                                ? $this->normalizer->normalizeToken((string)($arguments[$key] ?? ''))
                                : $this->normalizer->normalizeString($arguments[$key] ?? '');
                        if ($value !== '') {
                                $permission[$key] = $value;
                        }
                }

                if (array_key_exists('archive', $arguments)) {
                        $permission['archive'] = $this->normalizer->normalizeBool($arguments['archive'], false) ? 1 : 0;
                }

                if ($requireCore) {
                        foreach (['scope', 'permission'] as $key) {
                                if (!isset($permission[$key]) || $permission[$key] === '') {
                                        return [];
                                }
                        }
                }

                return $permission;
        }

        /**
         * @param array<int,mixed> $rows
         * @return array<int,array<string,mixed>|int|string>
         */
        private function normalizePermissionRows(array $rows): array {
                $out = [];

                foreach ($rows as $row) {
                        if (is_int($row) || is_string($row)) {
                                $id = $this->normalizeId($row);
                                if ((string)$id !== '') {
                                        $out[] = $id;
                                }
                                continue;
                        }

                        if (!is_array($row)) {
                                continue;
                        }

                        $permission = [];
                        if (array_key_exists('id', $row)) {
                                $permission['id'] = $this->normalizeId($row['id']);
                        }

                        foreach (['scope', 'permission', 'label', 'info'] as $key) {
                                if (!array_key_exists($key, $row)) {
                                        continue;
                                }

                                $value = $key === 'scope' || $key === 'permission'
                                        ? $this->normalizer->normalizeToken((string)($row[$key] ?? ''))
                                        : $this->normalizer->normalizeString($row[$key] ?? '');
                                if ($value !== '') {
                                        $permission[$key] = $value;
                                }
                        }

                        if ($permission !== []) {
                                $out[] = $permission;
                        }
                }

                return $out;
        }

        /**
         * @param array<int,array<string,mixed>> $permissions
         */
        private function roleHasPermissionField(array $permissions, string $field, string $value): bool {
                foreach ($permissions as $permission) {
                        if (!is_array($permission)) continue;
                        if ($this->normalizer->normalizeToken((string)($permission[$field] ?? '')) === $value) {
                                return true;
                        }
                }

                return false;
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
