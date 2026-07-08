<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Service;

/**
 * Builds stable serializable result structures for MissionBay tools,
 * resources, and prompts.
 */
class MemoraAgentResultBuilder {

        /**
         * @param array<string,mixed> $data
         * @param array<int,array<string,mixed>> $items
         * @param array<string,mixed> $paging
         * @param array<int,array<string,string>> $links
         * @param array<int,string> $warnings
         * @return array<string,mixed>
         */
        public function success(
                string $tool,
                string $message,
                array $data = [],
                array $items = [],
                array $paging = [],
                array $links = [],
                array $warnings = []
        ): array {
                $result = [
                        'ok' => true,
                        'status' => 'success',
                        'tool' => $tool,
                        'message' => $message,
                        'data' => $data,
                        'items' => $items,
                        'links' => $links,
                        'warnings' => $warnings
                ];

                if ($paging !== []) {
                        $result['paging'] = $paging;
                }

                return $result;
        }

        /**
         * Builds a non-mutating result for tools that require user review before execution.
         *
         * The model should present this result to the user, wait for explicit approval,
         * and then call the same tool again with confirm=true and the same payload.
         *
         * @param array<string,mixed> $plan
         * @param array<string,mixed> $arguments
         * @param array<int,string> $warnings
         * @return array<string,mixed>
         */
        public function confirmationRequired(
                string $tool,
                string $message,
                array $plan,
                array $arguments,
                array $warnings = []
        ): array {
                return [
                        'ok' => true,
                        'status' => 'confirmation_required',
                        'tool' => $tool,
                        'message' => $message,
                        'confirmation_required' => true,
                        'confirm_argument' => 'confirm',
                        'instructions' => 'Show this proposed change to the user. Execute only after explicit user confirmation by calling the same tool again with confirm=true.',
                        'data' => [
                                'plan' => $plan,
                                'arguments' => $arguments
                        ],
                        'items' => [],
                        'links' => [],
                        'warnings' => $warnings
                ];
        }

        /**
         * @param array<int,string> $suggestions
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public function error(
                string $tool,
                string $code,
                string $message,
                array $suggestions = [],
                array $data = []
        ): array {
                return [
                        'ok' => false,
                        'status' => 'error',
                        'tool' => $tool,
                        'code' => $code,
                        'message' => $message,
                        'suggestions' => $suggestions,
                        'data' => $data
                ];
        }

        /**
         * @param array<string,mixed> $data
         * @return array<string,mixed>
         */
        public function resource(string $uri, array $data): array {
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                return [
                        'contents' => [[
                                'uri' => $uri,
                                'mimeType' => 'application/json',
                                'text' => is_string($json) ? $json : '{}'
                        ]]
                ];
        }

        /**
         * @return array<string,mixed>
         */
        public function prompt(string $description, string $text): array {
                return [
                        'description' => $description,
                        'messages' => [[
                                'role' => 'user',
                                'content' => [
                                        'type' => 'text',
                                        'text' => $text
                                ]
                        ]]
                ];
        }

        /**
         * @return array<string,mixed>
         */
        public function paging(int $offset, int $limit, int $total, int $returned, bool $truncated = false): array {
                $paging = [
                        'offset' => $offset,
                        'limit' => $limit,
                        'total' => $total,
                        'returned' => $returned
                ];

                if ($truncated) {
                        $paging['truncated'] = true;
                }

                return $paging;
        }
}
