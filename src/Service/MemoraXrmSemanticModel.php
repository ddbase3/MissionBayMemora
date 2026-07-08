<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 **********************************************************************/

namespace MissionBayMemora\Service;

use ResourceFoundation\Api\IEntityStructureService;
use ResourceFoundation\Api\IEntityTagService;

/**
 * Builds agent-facing semantic XRM descriptions from Memora itself.
 *
 * The source of truth is the ResourceFoundation/Memora structure layer:
 * types, modules, scopes, scope-module assignments, tags and module-tag
 * assignments. This class intentionally does not keep duplicate domain
 * presets in PHP code or in the SettingsStore.
 */
class MemoraXrmSemanticModel {

	private const DEFAULT_LIMIT = 50;
	private const MAX_LIMIT = 200;

	public function __construct(
		private readonly IEntityStructureService $structureService,
		private readonly IEntityTagService $tagService,
		private readonly MemoraAgentInputNormalizer $normalizer
	) {}

	/**
	 * Compatibility alias for older tool code. Returns Memora-derived domain
	 * profiles keyed by module name, not external presets.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function getPresets(): array {
		return $this->getDomainProfiles();
	}

	/**
	 * Compatibility alias for older tool code. Reads one Memora-derived domain
	 * profile by module/name.
	 *
	 * @return array<string,mixed>|null
	 */
	public function getPreset(string $name): ?array {
		return $this->getDomainProfile($name);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function getDomainProfiles(): array {
		$types = $this->indexTypes($this->structureService->getTypes());
		$scopeIndex = $this->indexAssignments($this->structureService->getModuleScopes(), 'module', 'scope');
		$tagIndex = $this->indexAssignments($this->tagService->getModuleTags(), 'module', 'tag');

		$profiles = [];
		foreach ($this->structureService->getModules() as $row) {
			if (!is_array($row)) {
				continue;
			}

			$module = $this->normalizer->normalizeToken((string)($row['module'] ?? ''));
			if ($module === '') {
				continue;
			}

			$typeId = (string)($row['type_id'] ?? '');
			$type = $types['by_id'][$typeId] ?? null;
			$scopes = $scopeIndex[$module] ?? [];
			$tags = $tagIndex[$module] ?? [];

			$profiles[$module] = $this->removeEmpty([
				'name' => $module,
				'label' => $this->humanize($module),
				'kind' => 'memora_module_profile',
				'source' => 'memora',
				'module' => $module,
				'type' => $type['alias'] ?? null,
				'type_id' => $row['type_id'] ?? null,
				'type_row' => $type,
				'scopes' => $scopes,
				'scope' => $scopes[0] ?? null,
				'required_tags' => $tags,
				'suggested_tags' => $tags,
				'module_tags' => $tags,
				'description' => $row['description'] ?? '',
				'single' => !empty($row['single']),
				'expected_relations' => [],
				'agent_guidance' => $this->profileGuidance($module, $type['alias'] ?? null, $scopes, $tags, (string)($row['description'] ?? ''))
			]);
		}

		ksort($profiles);
		return $profiles;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function getDomainProfile(string $name): ?array {
		$name = $this->normalizer->normalizeToken($name);
		if ($name === '') {
			return null;
		}

		$profiles = $this->getDomainProfiles();
		if (isset($profiles[$name])) {
			return $profiles[$name];
		}

		return $this->resolveProfile('', $name, '', '', '', []);
	}

	/**
	 * Relation expectations are not stored as separate presets here. The tool
	 * communicates that relations are Memora allocs and should be inspected or
	 * changed through the graph tools. Concrete relation meaning comes from the
	 * linked entries and user-provided intent.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getRelationPatterns(?string $profileName = null): array {
		$profile = $profileName !== null && $profileName !== '' ? $this->getDomainProfile($profileName) : null;
		if ($profile === null) {
			return [];
		}

		return [[
			'source_kind' => $profile['name'] ?? $profileName,
			'source_module' => $profile['module'] ?? null,
			'source_type' => $profile['type'] ?? null,
			'source' => 'memora_structure',
			'relation' => [
				'role' => 'related_entry',
				'required' => false,
				'description' => 'Use alloc relations to connect this entry to its concrete XRM context. Inspect existing entries and graph links before deciding which relations are required.'
			]
		]];
	}

	/**
	 * @param array<int,string> $include
	 * @return array<string,mixed>
	 */
	public function getModel(?string $scope = null, ?string $module = null, array $include = [], string $query = '', int $limit = self::DEFAULT_LIMIT): array {
		$scope = $scope !== null ? $this->normalizer->normalizeToken($scope) : '';
		$module = $module !== null ? $this->normalizer->normalizeToken($module) : '';
		$query = trim(mb_strtolower($query));
		$limit = max(1, min(self::MAX_LIMIT, $limit));

		$types = $this->indexTypes($this->structureService->getTypes());
		$scopes = $this->filterRows($this->structureService->getScopes(), $query, $limit);
		$modules = $this->filterDomainProfiles($scope, $module, $query, $limit);
		$tags = $this->filterRows($this->tagService->getTags($scope !== '' ? $scope : null), $query, $limit);

		$data = [
			'concepts' => $this->concepts(),
			'source_of_truth' => 'memora',
			'source_services' => [
				\ResourceFoundation\Api\IEntityStructureService::class,
				\ResourceFoundation\Api\IEntityTagService::class,
				\ResourceFoundation\Api\IEntityRelationService::class
			],
			'scope' => $scope !== '' ? $scope : null,
			'module' => $module !== '' ? $module : null
		];

		if ($this->include($include, 'types')) {
			$data['types'] = array_slice(array_values($types['by_id']), 0, $limit);
		}
		if ($this->include($include, 'scopes')) {
			$data['scopes'] = $scopes;
		}
		if ($this->include($include, 'modules')) {
			$data['modules'] = array_values($modules);
		}
		if ($this->include($include, 'tags')) {
			$data['tags'] = $tags;
		}
		if ($this->include($include, 'domain_profiles') || $this->include($include, 'presets')) {
			$data['domain_profiles'] = $modules;
		}
		if ($this->include($include, 'module_tags')) {
			$data['module_tags'] = $this->filterRows($this->tagService->getModuleTags($module !== '' ? $module : null), $query, $limit);
		}
		if ($this->include($include, 'scope_modules')) {
			$data['scope_modules'] = $this->filterRows($this->structureService->getScopeModules($scope !== '' ? $scope : null), $query, $limit);
		}
		if ($this->include($include, 'relations')) {
			$data['relation_guidance'] = $this->relationGuidance();
		}

		return $data;
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function planEntry(array $arguments): array {
		$intent = $this->normalizer->normalizeString($arguments['intent'] ?? '');
		$kind = $this->normalizer->normalizeToken((string)($arguments['kind'] ?? ''));
		$scope = $this->normalizer->normalizeToken((string)($arguments['scope'] ?? ''));
		$module = $this->normalizer->normalizeToken((string)($arguments['module'] ?? ''));
		$type = $this->normalizer->normalizeToken((string)($arguments['type'] ?? ''));
		$title = $this->normalizer->normalizeString($arguments['name'] ?? ($arguments['title'] ?? ''));
		$data = $this->normalizer->normalizeArray($arguments['data'] ?? []);
		$metadata = $this->normalizer->normalizeArray($arguments['metadata'] ?? []);
		$relations = $this->normalizer->normalizeIdList($arguments['relations'] ?? null);
		$explicitTags = $this->normalizer->normalizeStringList($arguments['tags'] ?? null);

		$profile = $this->resolveProfile($kind, $intent, $scope, $module, $type, $explicitTags);
		$profileName = $profile['name'] ?? null;

		if ($profile !== null) {
			$scope = $scope !== '' ? $scope : (string)($profile['scope'] ?? '');
			$module = $module !== '' ? $module : (string)($profile['module'] ?? '');
			$type = $type !== '' ? $type : (string)($profile['type'] ?? '');
		}

		$tags = array_values(array_unique(array_merge(
			$profile['required_tags'] ?? [],
			$explicitTags
		)));

		$entry = $this->removeEmpty([
			'type' => $type,
			'module' => $module,
			'name' => $title,
			'tags' => $tags,
			'data' => $data,
			'metadata' => $metadata,
			'allocs' => $relations
		]);

		return [
			'intent' => $intent !== '' ? $intent : null,
			'source_of_truth' => 'memora',
			'matched_domain_profile' => $profileName,
			'matched_preset' => $profileName,
			'semantic_kind' => $profileName ?? $kind ?: null,
			'scope' => $scope !== '' ? $scope : null,
			'entry' => $entry,
			'required_tags' => $profile['required_tags'] ?? [],
			'suggested_tags' => $profile['suggested_tags'] ?? [],
			'expected_relations' => $this->relationGuidance(),
			'domain_profile' => $profile,
			'agent_guidance' => $this->guidance($profileName, $profile, $entry, $relations),
			'warnings' => $this->planWarnings($entry, $profile)
		];
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function explain(array $arguments): array {
		$input = $this->normalizer->normalizeString($arguments['input'] ?? ($arguments['intent'] ?? ''));
		$scope = $this->normalizer->normalizeToken((string)($arguments['scope'] ?? ''));
		$module = $this->normalizer->normalizeToken((string)($arguments['module'] ?? ''));
		$type = $this->normalizer->normalizeToken((string)($arguments['type'] ?? ''));
		$tags = $this->normalizer->normalizeStringList($arguments['tags'] ?? null);
		$profile = $this->resolveProfile('', $input, $scope, $module, $type, $tags);
		$profileName = $profile['name'] ?? null;

		return [
			'input' => $input,
			'source_of_truth' => 'memora',
			'scope' => $scope !== '' ? $scope : null,
			'module' => $module !== '' ? $module : null,
			'type' => $type !== '' ? $type : null,
			'tags' => $tags,
			'matched_domain_profile' => $profileName,
			'matched_preset' => $profileName,
			'domain_profile' => $profile,
			'preset' => $profile,
			'concepts' => $this->concepts(),
			'interpretation' => $this->interpretationText($profile, $scope, $module, $type, $tags)
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function concepts(): array {
		return [
			'entry' => 'The central Memora/XRM object. Every record is an entry.',
			'type' => 'The technical data shape, for example contact, product, project, task, address, date, file, link or note.',
			'module' => 'The domain-specific meaning and specialization stored in Memora sysmodule.',
			'scope' => 'A domain area such as crm, dancephotography, inventory or movies, stored in Memora sysscope.',
			'tag' => 'A semantic classification, state marker, workflow marker or cross-entity grouping stored in Memora tag tables.',
			'module_tag' => 'A Memora assignment that says which tags normally belong to a module.',
			'scope_module' => 'A Memora assignment that says which modules belong to a scope.',
			'domain_profile' => 'A derived, read-only agent view of one Memora module with its type, scopes, module tags and description.',
			'relation_alloc' => 'A Memora relation/alloc connecting entries into the XRM knowledge graph. Relations are data, not static presets.'
		];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function relationGuidance(): array {
		return [[
			'role' => 'related_entry',
			'required' => false,
			'source' => 'memora_allocs',
			'description' => 'Relations are not duplicated as presets. Use existing Memora allocs and the concrete user request to decide which entries must be connected.'
		]];
	}

	/**
	 * @return array{by_id:array<string,array<string,mixed>>,by_alias:array<string,array<string,mixed>>}
	 */
	private function indexTypes(array $rows): array {
		$byId = [];
		$byAlias = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$id = (string)($row['id'] ?? '');
			$alias = $this->normalizer->normalizeToken((string)($row['alias'] ?? ''));
			if ($id !== '') {
				$byId[$id] = $row;
			}
			if ($alias !== '') {
				$byAlias[$alias] = $row;
			}
		}

		return ['by_id' => $byId, 'by_alias' => $byAlias];
	}

	/**
	 * @return array<string,array<int,string>>
	 */
	private function indexAssignments(array $rows, string $ownerField, string $valueField): array {
		$index = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$owner = $this->normalizer->normalizeToken((string)($row[$ownerField] ?? ''));
			$value = $this->normalizer->normalizeToken((string)($row[$valueField] ?? ''));
			if ($owner === '' || $value === '') {
				continue;
			}

			$index[$owner][] = $value;
		}

		foreach ($index as $owner => $values) {
			$values = array_values(array_unique($values));
			sort($values);
			$index[$owner] = $values;
		}

		return $index;
	}

	/**
	 * @param array<int,string> $scopes
	 * @param array<int,string> $tags
	 * @return array<int,string>
	 */
	private function profileGuidance(string $module, ?string $type, array $scopes, array $tags, string $description): array {
		$lines = [
			'This domain profile is derived from Memora structure data, not from hard-coded PHP or SettingsStore presets.',
			'Use the module as the domain meaning and the type as the technical data shape.'
		];

		if ($description !== '') {
			$lines[] = 'Module description: ' . $description;
		}
		if ($type !== null && $type !== '') {
			$lines[] = 'Technical type: ' . $type . '.';
		}
		if ($scopes !== []) {
			$lines[] = 'Scope membership: ' . implode(', ', $scopes) . '.';
		}
		if ($tags !== []) {
			$lines[] = 'Normally apply module tags: ' . implode(', ', $tags) . '.';
		}

		$lines[] = 'Use alloc relations to connect the entry to its concrete context.';

		return $lines;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function filterDomainProfiles(string $scope, string $module, string $query, int $limit): array {
		$out = [];
		foreach ($this->getDomainProfiles() as $name => $profile) {
			if ($scope !== '' && !in_array($scope, $profile['scopes'] ?? [], true)) {
				continue;
			}
			if ($module !== '' && $name !== $module) {
				continue;
			}
			if ($query !== '' && !$this->profileMatches($profile, $query)) {
				continue;
			}

			$out[$name] = $profile;
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	private function profileMatches(array $profile, string $query): bool {
		$haystack = mb_strtolower(json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
		return str_contains($haystack, $query);
	}

	/**
	 * @param array<int,string> $tags
	 * @return array<string,mixed>|null
	 */
	private function resolveProfile(string $kind, string $intent, string $scope, string $module, string $type, array $tags): ?array {
		$profiles = $this->getDomainProfiles();

		if ($module !== '' && isset($profiles[$module])) {
			return $profiles[$module];
		}

		if ($kind !== '' && isset($profiles[$kind])) {
			return $profiles[$kind];
		}

		$haystack = mb_strtolower(trim($kind . ' ' . $intent . ' ' . $scope . ' ' . $module . ' ' . $type . ' ' . implode(' ', $tags)));
		if ($haystack === '') {
			return null;
		}

		$ranked = [];
		foreach ($profiles as $name => $profile) {
			$score = 0;
			if (str_contains($haystack, $name)) {
				$score += 100;
			}
			foreach ($profile['module_tags'] ?? [] as $tag) {
				if (in_array($tag, $tags, true) || str_contains($haystack, $tag)) {
					$score += 25;
				}
			}
			foreach ($profile['scopes'] ?? [] as $profileScope) {
				if ($scope === $profileScope || str_contains($haystack, $profileScope)) {
					$score += 15;
				}
			}
			if ($type !== '' && ($profile['type'] ?? '') === $type) {
				$score += 10;
			}
			if (str_contains(mb_strtolower((string)($profile['description'] ?? '')), $haystack)) {
				$score += 5;
			}

			if ($score > 0) {
				$ranked[] = ['score' => $score, 'profile' => $profile, 'name' => $name];
			}
		}

		if ($ranked === []) {
			return null;
		}

		usort($ranked, static function(array $a, array $b): int {
			if ($a['score'] !== $b['score']) {
				return $b['score'] <=> $a['score'];
			}
			return strcmp((string)$a['name'], (string)$b['name']);
		});

		return $ranked[0]['profile'];
	}

	/**
	 * @param array<int|string,mixed> $rows
	 * @return array<int,array<string,mixed>>
	 */
	private function filterRows(array $rows, string $query, int $limit): array {
		$out = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			if ($query !== '') {
				$encoded = mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
				if (!str_contains($encoded, $query)) {
					continue;
				}
			}

			$out[] = $row;
			if (count($out) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @param array<int,string> $include
	 */
	private function include(array $include, string $section): bool {
		return $include === [] || in_array('all', $include, true) || in_array($section, $include, true);
	}

	/**
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
	private function removeEmpty(array $entry): array {
		foreach ($entry as $key => $value) {
			if ($value === '' || $value === [] || $value === null) {
				unset($entry[$key]);
			}
		}
		return $entry;
	}

	/**
	 * @param array<string,mixed>|null $profile
	 * @param array<string,mixed> $entry
	 * @return array<int,string>
	 */
	private function guidance(?string $profileName, ?array $profile, array $entry, array $relations): array {
		$lines = [
			'Use Memora/XRM data as source of truth: type defines the technical shape, module defines the domain meaning, tags define classification/state, and alloc relations define knowledge connections.'
		];

		if ($profileName !== null && $profile !== null) {
			$lines[] = 'Matched Memora domain profile: ' . $profileName . ' (' . (string)($profile['label'] ?? $profileName) . ').';
			foreach (($profile['agent_guidance'] ?? []) as $line) {
				$line = trim((string)$line);
				if ($line !== '') {
					$lines[] = $line;
				}
			}
		}

		if (($entry['type'] ?? '') === '' || ($entry['module'] ?? '') === '') {
			$lines[] = 'The plan is incomplete until type and module are clear. Use memora_get_xrm_semantic_model or memora_explain_xrm_semantics first.';
		}

		if ($relations === []) {
			$lines[] = 'No relations were provided. For knowledge work, connect the new entry to existing context entries when possible.';
		}

		return $lines;
	}

	/**
	 * @param array<string,mixed> $entry
	 * @param array<string,mixed>|null $profile
	 * @return array<int,string>
	 */
	private function planWarnings(array $entry, ?array $profile): array {
		$warnings = [];
		if (($entry['type'] ?? '') === '') {
			$warnings[] = 'Missing type.';
		}
		if (($entry['module'] ?? '') === '') {
			$warnings[] = 'Missing module.';
		}
		if (($entry['tags'] ?? []) === []) {
			$warnings[] = 'No tags selected.';
		}
		if ($profile !== null && empty($entry['allocs'])) {
			$warnings[] = 'This is only a local entry plan. Add alloc relations when the entry belongs to existing XRM context.';
		}

		return $warnings;
	}

	/**
	 * @param array<int,string> $tags
	 */
	private function interpretationText(?array $profile, string $scope, string $module, string $type, array $tags): string {
		if ($profile !== null) {
			return sprintf(
				'This matches Memora module "%s". Use type "%s", module "%s", and normally include module tags: %s.',
				(string)($profile['name'] ?? ''),
				(string)($profile['type'] ?? ''),
				(string)($profile['module'] ?? ''),
				implode(', ', $profile['module_tags'] ?? [])
			);
		}

		return 'No Memora module profile matched. Use discovered types, modules, scopes, tags and alloc relations from Memora before changing data.';
	}

	private function humanize(string $value): string {
		$value = trim(preg_replace('/[_-]+/u', ' ', $value) ?? $value);
		return $value !== '' ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8') : '';
	}
}
