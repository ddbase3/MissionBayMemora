<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBayMemora for BASE3 Framework.
 *
 * MissionBayMemora connects MissionBay agent tooling with the
 * ResourceFoundation entity service layer. It exposes Memora/XRM data as
 * safe, reusable agent tools, resources, and prompts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbaymemora
 **********************************************************************/

namespace Base3\MissionBayMemora;

use Base3\CoreFoundation\Api\ICheck;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\MissionBayMemora\Service\MemoraAgentAccessPolicy;
use Base3\MissionBayMemora\Service\MemoraAgentEntryFormatter;
use Base3\MissionBayMemora\Service\MemoraAgentInputNormalizer;
use Base3\MissionBayMemora\Service\MemoraAgentResultBuilder;
use Base3\MissionBayMemora\Service\MemoraXrmSemanticModel;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationCatalogService;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationAdministrationService;
use Base3\ResourceFoundation\Api\IEntityActivityService;
use Base3\ResourceFoundation\Api\IEntityDataService;
use Base3\ResourceFoundation\Api\IEntityFileService;
use Base3\ResourceFoundation\Api\IEntityMetadataService;
use Base3\ResourceFoundation\Api\IEntityProfileService;
use Base3\ResourceFoundation\Api\IEntityRelationService;
use Base3\ResourceFoundation\Api\IEntityStructureService;
use Base3\ResourceFoundation\Api\IEntityTagService;
use Base3\ResourceFoundation\Api\IEntityUserDataService;

/**
 * MissionBayMemoraPlugin
 *
 * Registers shared helper services used by the discoverable MissionBay
 * Memora agent tools. The tools themselves are discovered through the BASE3
 * class map because they implement MissionBay agent resource interfaces.
 */
class MissionBayMemoraPlugin implements IPlugin, ICheck {

	public function __construct(
		private readonly IContainer $container
	) {}

	public static function getName(): string {
		return 'missionbaymemora';
	}

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)
			->set(MemoraAgentInputNormalizer::class, fn() => new MemoraAgentInputNormalizer(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MemoraAgentResultBuilder::class, fn() => new MemoraAgentResultBuilder(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MemoraAgentAccessPolicy::class, fn() => new MemoraAgentAccessPolicy(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MemoraAgentEntryFormatter::class, fn($c) => new MemoraAgentEntryFormatter(
				$c->get(MemoraAgentInputNormalizer::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MemoraXrmSemanticModel::class, fn($c) => new MemoraXrmSemanticModel(
				$c->get(IEntityStructureService::class),
				$c->get(IEntityTagService::class),
				$c->get(MemoraAgentInputNormalizer::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	public function checkDependencies(): array {
		$services = [
			IEntityDataService::class,
			IEntityFileService::class,
			IEntityRelationService::class,
			IEntityMetadataService::class,
			IEntityTagService::class,
			IEntityStructureService::class,
			IEntityActivityService::class,
			IEntityUserDataService::class,
			IAuthorizationCatalogService::class,
			IAuthorizationAdministrationService::class,
			IEntityProfileService::class
		];

		$result = [];
		foreach ($services as $service) {
			$result[$service] = $this->container->has($service) ? 'Ok' : 'missing';
		}

		return $result;
	}
}
