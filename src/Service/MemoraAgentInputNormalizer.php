<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Service;

/**
 * Normalizes user- and model-provided tool arguments before they reach the
 * ResourceFoundation service layer.
 */
class MemoraAgentInputNormalizer {

        /**
         * @param array<int,string> $allowed
         */
        public function normalizeToken(string $value, array $allowed = [], string $default = ''): string {
                $value = trim(mb_strtolower($value));
                $value = preg_replace('/\s+/u', '_', $value) ?? $value;

                if ($allowed !== [] && !in_array($value, $allowed, true)) {
                        return $default;
                }

                return $value;
        }

        public function normalizeString(mixed $value, string $default = ''): string {
                if (is_scalar($value) || $value === null) {
                        $value = trim((string)$value);
                        return $value !== '' ? $value : $default;
                }

                return $default;
        }

        public function normalizeBool(mixed $value, bool $default = false): bool {
                if (is_bool($value)) {
                        return $value;
                }

                if (is_int($value) || is_float($value)) {
                        return ((int)$value) !== 0;
                }

                if (is_string($value)) {
                        $value = mb_strtolower(trim($value));
                        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                                return true;
                        }
                        if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) {
                                return false;
                        }
                }

                return $default;
        }

        public function normalizeLimit(mixed $value, int $default = 10, int $max = 50): int {
                $limit = (int)$value;
                if ($limit <= 0) {
                        $limit = $default;
                }

                return max(1, min($max, $limit));
        }

        public function normalizeOffset(mixed $value): int {
                return max(0, (int)$value);
        }

        /**
         * @param array<int,string> $allowed
         * @return array<int,string>
         */
        public function normalizeStringList(mixed $value, array $allowed = []): array {
                if ($value === null || $value === '') {
                        return [];
                }

                if (!is_array($value)) {
                        $value = explode(',', (string)$value);
                }

                $out = [];
                foreach ($value as $item) {
                        if (is_array($item)) {
                                foreach ($this->normalizeStringList($item, $allowed) as $child) {
                                        $out[] = $child;
                                }
                                continue;
                        }

                        $item = trim((string)$item);
                        if ($item === '') {
                                continue;
                        }

                        if ($allowed !== [] && !in_array($item, $allowed, true)) {
                                continue;
                        }

                        $out[] = $item;
                }

                return array_values(array_unique($out));
        }

        /**
         * @return array<int,int|string>
         */
        public function normalizeIdList(mixed $value): array {
                if ($value === null || $value === '') {
                        return [];
                }

                if (!is_array($value)) {
                        $value = explode(',', (string)$value);
                }

                $out = [];
                foreach ($value as $item) {
                        if (is_array($item)) {
                                foreach ($this->normalizeIdList($item) as $child) {
                                        $out[] = $child;
                                }
                                continue;
                        }

                        if (is_int($item)) {
                                $out[] = $item;
                                continue;
                        }

                        $item = trim((string)$item);
                        if ($item === '') {
                                continue;
                        }

                        $out[] = ctype_digit($item) ? (int)$item : $item;
                }

                return array_values(array_unique($out, SORT_REGULAR));
        }

        /**
         * @param array<int,string> $default
         * @param array<int,string> $allowed
         * @return array<int,string>
         */
        public function normalizeIncludes(mixed $value, array $default, array $allowed): array {
                $includes = $this->normalizeStringList($value, $allowed);
                if ($includes === []) {
                        return $default;
                }

                return $includes;
        }

        /**
         * @return array<string,mixed>
         */
        public function normalizeArray(mixed $value): array {
                return is_array($value) ? $value : [];
        }
}
