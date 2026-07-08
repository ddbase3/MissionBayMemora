<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Service;

use MissionBay\Api\IAgentContext;

/**
 * Controls which MissionBayMemora tool capabilities may execute.
 *
 * This policy protects the tool surface. It does not replace Memora's own
 * entity access checks, which remain enforced by the ResourceFoundation
 * service implementations.
 */
class MemoraAgentAccessPolicy {

        public const CAPABILITY_ENTRY_WRITE = 'entry_write';
        public const CAPABILITY_ACTIVITY_READ = 'activity_read';
        public const CAPABILITY_ACTIVITY_WRITE = 'activity_write';
        public const CAPABILITY_ACCESS_READ = 'access_read';
        public const CAPABILITY_ACCESS_WRITE = 'access_write';
        public const CAPABILITY_ROLE_ADMIN = 'role_admin';
        public const CAPABILITY_MEMBERSHIP_ADMIN = 'membership_admin';
        public const CAPABILITY_FILE_READ = 'file_read';
        public const CAPABILITY_FILE_WRITE = 'file_write';
        public const CAPABILITY_USERDATA_READ = 'userdata_read';
        public const CAPABILITY_USERDATA_WRITE = 'userdata_write';
        public const CAPABILITY_PROFILE_READ = 'profile_read';
        public const CAPABILITY_PROFILE_WRITE = 'profile_write';
        public const CAPABILITY_SEMANTIC_READ = 'semantic_read';
        public const CAPABILITY_SEMANTIC_WRITE = 'semantic_write';
        public const CAPABILITY_STRUCTURE_WRITE = 'structure_write';
        public const CAPABILITY_GRAPH_READ = 'graph_read';
        public const CAPABILITY_GRAPH_WRITE = 'graph_write';
        public const CAPABILITY_DOMAIN_READ = 'domain_read';
        public const CAPABILITY_DOMAIN_WRITE = 'domain_write';
        public const CAPABILITY_DESTRUCTIVE = 'destructive';

        /**
         * @param array<string,mixed> $config Resource configuration
         */
        public function isAllowed(string $capability, array $config, IAgentContext $context): bool {
                if (array_key_exists('enabled', $config) && !$this->toBool($config['enabled'], true)) {
                        return false;
                }

                $denied = $this->normalizeList($config['denied_capabilities'] ?? []);
                if (in_array($capability, $denied, true)) {
                        return false;
                }

                $allowed = $this->normalizeList($config['allowed_capabilities'] ?? []);
                if ($allowed !== [] && !in_array($capability, $allowed, true)) {
                        return false;
                }

                if ($this->toBool($config['readonly'] ?? false, false)) {
                        return in_array($capability, [
                                self::CAPABILITY_ACTIVITY_READ,
                                self::CAPABILITY_ACCESS_READ,
                                self::CAPABILITY_FILE_READ,
                                self::CAPABILITY_USERDATA_READ,
                                self::CAPABILITY_PROFILE_READ,
                                self::CAPABILITY_SEMANTIC_READ,
                                self::CAPABILITY_GRAPH_READ,
                                self::CAPABILITY_DOMAIN_READ
                        ], true);
                }

                return match ($capability) {
                        self::CAPABILITY_ENTRY_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_entry_write'] ?? true, true),
                        self::CAPABILITY_ACTIVITY_READ => $this->toBool($config['allow_activity_read'] ?? true, true),
                        self::CAPABILITY_ACTIVITY_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_activity_write'] ?? true, true),
                        self::CAPABILITY_ACCESS_READ => $this->toBool($config['allow_access_read'] ?? true, true),
                        self::CAPABILITY_ACCESS_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_access_write'] ?? true, true),
                        self::CAPABILITY_ROLE_ADMIN => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_role_admin'] ?? true, true),
                        self::CAPABILITY_MEMBERSHIP_ADMIN => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_membership_admin'] ?? true, true),
                        self::CAPABILITY_FILE_READ => $this->toBool($config['allow_file_read'] ?? true, true),
                        self::CAPABILITY_FILE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_file_write'] ?? true, true),
                        self::CAPABILITY_USERDATA_READ => $this->toBool($config['allow_userdata_read'] ?? true, true),
                        self::CAPABILITY_USERDATA_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_userdata_write'] ?? true, true),
                        self::CAPABILITY_PROFILE_READ => $this->toBool($config['allow_profile_read'] ?? true, true),
                        self::CAPABILITY_PROFILE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_profile_write'] ?? true, true),
                        self::CAPABILITY_SEMANTIC_READ => $this->toBool($config['allow_semantic_read'] ?? true, true),
                        self::CAPABILITY_SEMANTIC_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_semantic_write'] ?? true, true),
                        self::CAPABILITY_STRUCTURE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_structure_write'] ?? true, true),
                        self::CAPABILITY_GRAPH_READ => $this->toBool($config['allow_graph_read'] ?? true, true),
                        self::CAPABILITY_GRAPH_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_graph_write'] ?? true, true),
                        self::CAPABILITY_DOMAIN_READ => $this->toBool($config['allow_domain_read'] ?? true, true),
                        self::CAPABILITY_DOMAIN_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_domain_write'] ?? true, true),
                        self::CAPABILITY_DESTRUCTIVE => $this->toBool($config['allow_destructive'] ?? false, false),
                        default => false
                };
        }

        /**
         * Whether a write tool should require explicit confirmation before mutation.
         *
         * @param array<string,mixed> $config Resource configuration
         */
        public function requiresConfirmation(array $config): bool {
                return $this->toBool($config['require_confirmation'] ?? true, true);
        }

        /**
         * @return array<string,bool>
         */
        public function describe(array $config = []): array {
                return [
                        self::CAPABILITY_ENTRY_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_entry_write'] ?? true, true),
                        self::CAPABILITY_ACTIVITY_READ => $this->toBool($config['allow_activity_read'] ?? true, true),
                        self::CAPABILITY_ACTIVITY_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_activity_write'] ?? true, true),
                        self::CAPABILITY_ACCESS_READ => $this->toBool($config['allow_access_read'] ?? true, true),
                        self::CAPABILITY_ACCESS_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_access_write'] ?? true, true),
                        self::CAPABILITY_ROLE_ADMIN => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_role_admin'] ?? true, true),
                        self::CAPABILITY_MEMBERSHIP_ADMIN => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_membership_admin'] ?? true, true),
                        self::CAPABILITY_FILE_READ => $this->toBool($config['allow_file_read'] ?? true, true),
                        self::CAPABILITY_FILE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_file_write'] ?? true, true),
                        self::CAPABILITY_USERDATA_READ => $this->toBool($config['allow_userdata_read'] ?? true, true),
                        self::CAPABILITY_USERDATA_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_userdata_write'] ?? true, true),
                        self::CAPABILITY_PROFILE_READ => $this->toBool($config['allow_profile_read'] ?? true, true),
                        self::CAPABILITY_PROFILE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_profile_write'] ?? true, true),
                        self::CAPABILITY_SEMANTIC_READ => $this->toBool($config['allow_semantic_read'] ?? true, true),
                        self::CAPABILITY_SEMANTIC_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_semantic_write'] ?? true, true),
                        self::CAPABILITY_STRUCTURE_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_structure_write'] ?? true, true),
                        self::CAPABILITY_GRAPH_READ => $this->toBool($config['allow_graph_read'] ?? true, true),
                        self::CAPABILITY_GRAPH_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_graph_write'] ?? true, true),
                        self::CAPABILITY_DOMAIN_READ => $this->toBool($config['allow_domain_read'] ?? true, true),
                        self::CAPABILITY_DOMAIN_WRITE => $this->toBool($config['allow_write'] ?? true, true)
                                && $this->toBool($config['allow_domain_write'] ?? true, true),
                        self::CAPABILITY_DESTRUCTIVE => $this->toBool($config['allow_destructive'] ?? false, false)
                ];
        }

        /**
         * @return array<int,string>
         */
        private function normalizeList(mixed $value): array {
                if ($value === null || $value === '') {
                        return [];
                }

                if (!is_array($value)) {
                        $value = explode(',', (string)$value);
                }

                $out = [];
                foreach ($value as $item) {
                        $item = trim(mb_strtolower((string)$item));
                        if ($item !== '') {
                                $out[] = $item;
                        }
                }

                return array_values(array_unique($out));
        }

        private function toBool(mixed $value, bool $default): bool {
                if (is_bool($value)) {
                        return $value;
                }

                if (is_int($value) || is_float($value)) {
                        return ((int)$value) !== 0;
                }

                if (is_string($value)) {
                        $value = trim(mb_strtolower($value));
                        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                                return true;
                        }
                        if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) {
                                return false;
                        }
                }

                return $default;
        }
}
