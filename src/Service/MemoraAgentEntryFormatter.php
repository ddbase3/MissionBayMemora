<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Service;

/**
 * Converts ResourceFoundation entity arrays into compact agent-friendly
 * response objects.
 */
class MemoraAgentEntryFormatter {

        private const MAX_STRING_LENGTH = 2000;

        public function __construct(
                private readonly MemoraAgentInputNormalizer $normalizer
        ) {}

        /**
         * @param array<string,mixed> $entry
         * @param array<int,string> $include
         * @return array<string,mixed>
         */
        public function formatEntry(array $entry, array $include = []): array {
                $out = [
                        'id' => $entry['id'] ?? null,
                        'uuid' => $entry['uuid'] ?? null,
                        'name' => $entry['name'] ?? ($entry['title'] ?? null),
                        'type' => $entry['type'] ?? ($entry['type_alias'] ?? null),
                        'archive' => $entry['archive'] ?? null,
                        'dellock' => $entry['dellock'] ?? null,
                        'created' => $entry['created'] ?? null,
                        'changed' => $entry['changed'] ?? null
                ];

                if (array_key_exists('access', $entry)) {
                        $out['access'] = $entry['access'];
                }

                if ($this->shouldInclude($include, 'data') && array_key_exists('data', $entry)) {
                        $out['data'] = $this->sanitizeValue($entry['data']);
                }

                if ($this->shouldInclude($include, 'metadata') && array_key_exists('metadata', $entry)) {
                        $out['metadata'] = $this->sanitizeValue($entry['metadata']);
                }

                if ($this->shouldInclude($include, 'tags') && array_key_exists('tags', $entry)) {
                        $out['tags'] = $this->sanitizeValue($entry['tags']);
                }

                if ($this->shouldInclude($include, 'relations') && array_key_exists('allocs', $entry)) {
                        $out['relations'] = $this->sanitizeValue($entry['allocs']);
                }

                if ($this->shouldInclude($include, 'relations') && array_key_exists('allocuuids', $entry)) {
                        $out['relation_uuids'] = $this->sanitizeValue($entry['allocuuids']);
                }

                return $this->removeEmptyNulls($out);
        }

        /**
         * @param array<string,mixed> $entry
         * @return array<string,mixed>
         */
        public function formatSummary(array $entry): array {
                $out = [
                        'id' => $entry['id'] ?? null,
                        'uuid' => $entry['uuid'] ?? null,
                        'name' => $entry['name'] ?? ($entry['title'] ?? null),
                        'type' => $entry['type'] ?? ($entry['type_alias'] ?? null),
                        'access' => $entry['access'] ?? null,
                        'changed' => $entry['changed'] ?? null
                ];

                if (isset($entry['tags'])) {
                        $out['tags'] = $this->sanitizeValue($entry['tags']);
                }

                return $this->removeEmptyNulls($out);
        }

        /**
         * @param array<int,array<string,mixed>> $entries
         * @return array<int,array<string,mixed>>
         */
        public function formatSummaries(array $entries): array {
                $out = [];
                foreach ($entries as $entry) {
                        if (is_array($entry)) {
                                $out[] = $this->formatSummary($entry);
                        }
                }

                return $out;
        }

        public function matchesQuery(array $entry, string $query): bool {
                $query = trim(mb_strtolower($query));
                if ($query === '') {
                        return true;
                }

                $haystack = mb_strtolower(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                return str_contains($haystack, $query);
        }

        /**
         * @return array<string,mixed>
         */
        private function removeEmptyNulls(array $data): array {
                foreach ($data as $key => $value) {
                        if ($value === null || $value === '') {
                                unset($data[$key]);
                        }
                }

                return $data;
        }

        /**
         * @param array<int,string> $include
         */
        private function shouldInclude(array $include, string $name): bool {
                return $include === [] || in_array($name, $include, true) || in_array('all', $include, true);
        }

        private function sanitizeValue(mixed $value): mixed {
                if (is_array($value)) {
                        $out = [];
                        foreach ($value as $key => $child) {
                                $out[$key] = $this->sanitizeValue($child);
                        }
                        return $out;
                }

                if (is_object($value)) {
                        return $this->sanitizeValue((array)$value);
                }

                if (is_string($value) && mb_strlen($value) > self::MAX_STRING_LENGTH) {
                        return mb_substr($value, 0, self::MAX_STRING_LENGTH) . '…';
                }

                return $value;
        }
}
