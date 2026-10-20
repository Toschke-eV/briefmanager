<?php

declare(strict_types=1);

namespace OCA\BriefManager\Settings;

use OCA\BriefManager\AppInfo\Application;
use OCA\BriefManager\Design\DesignRegistry;
use OCA\BriefManager\Service\CounterService;
use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\LogoService;
use OCA\BriefManager\Service\OrgAssignmentService;
use OCA\BriefManager\Service\OrganisationService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
	public function __construct(
		private OrganisationService $organisation,
		private DesignRegistry $designs,
		private CounterService $counters,
		private LetterService $letters,
		private LogoService $logos,
		private OrgAssignmentService $assignments,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'briefmanager-admin');
		Util::addStyle(Application::APP_ID, 'briefmanager-main');
		$org = $this->organisation->get();
		return new TemplateResponse(Application::APP_ID, 'admin', [
			'organisation' => $org,
			'organisations' => $this->organisation->listAll(),
			'active_id' => $this->organisation->getActiveId(),
			'designs' => $this->designs->labels(),
			'design_details' => $this->designs->listDetailed(),
			'counters' => $this->counters->status(),
			'letters' => $this->letters->listForCurrentUser(),
			'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			'users' => $this->assignments->usersWithAssignments(),
		], TemplateResponse::RENDER_AS_BLANK);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
