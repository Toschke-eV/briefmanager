<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\InternetmarkePurchaseService;
use OCA\BriefManager\Service\LayoutService;
use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\OrgAssignmentService;
use OCA\BriefManager\Service\OrganisationService;
use OCA\BriefManager\Service\StorageService;
use OCA\BriefManager\Service\VariantService;
use OCA\BriefManager\Design\DesignRegistry;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
		private VariantService $variants,
		private OrganisationService $organisation,
		private OrgAssignmentService $assignments,
		private DesignRegistry $designs,
		private StorageService $storage,
		private LayoutService $layouts,
		private InternetmarkePurchaseService $internetmarke,
		private IURLGenerator $url,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript('briefmanager', 'briefmanager-main');
		Util::addStyle('briefmanager', 'briefmanager-main');

		$this->variants->ensureSeed();
		try {
			$this->storage->ensureRootFolder();
		} catch (\Throwable) {
			// Ordner wird beim ersten PDF-Speichern angelegt
		}
		$org = $this->assignments->activeOrgForCurrentUser();
		$userOrgs = $this->assignments->availableOrgsForCurrentUser();
		$letters = $this->letters->listForCurrentUser();
		$variants = $this->variants->list(true);
		$userLayouts = $this->layouts->listMine();
		$imStatus = $this->internetmarke->status();

		$selectedId = (int)$this->request->getParam('id', 0);
		$selected = null;
		foreach ($letters as $row) {
			if ((int)$row['id'] === $selectedId) {
				$selected = $row;
				break;
			}
		}
		if ($selected === null && $letters !== []) {
			$selected = $letters[0];
			$selectedId = (int)$selected['id'];
		}

		$msg = (string)$this->request->getParam('msg', '');
		$err = (string)$this->request->getParam('err', '0') === '1';

		$letterLinks = [];
		foreach ($letters as $row) {
			$letterLinks[(int)$row['id']] = $this->route('briefmanager.page.index', ['id' => (int)$row['id']], '/apps/briefmanager/?id=' . (int)$row['id']);
		}

		$pdfUrl = $selectedId > 0
			? $this->route('briefmanager.pdf.download', ['id' => $selectedId], '/apps/briefmanager/api/letters/' . $selectedId . '/pdf')
			: '';
		$pdfInlineUrl = $selectedId > 0
			? $this->route('briefmanager.pdf.inline', ['id' => $selectedId], '/apps/briefmanager/api/letters/' . $selectedId . '/pdf-inline')
			: '';

		$fileMeta = null;
		if ($selected !== null) {
			$entity = $this->letters->get($selectedId);
			if ($entity !== null) {
				$fileMeta = $this->storage->resolve($entity);
			}
		}

		return new TemplateResponse('briefmanager', 'index', [
			'title' => 'Briefe',
			'org_name' => (string)($org['name'] ?: 'Briefmanager'),
			'primary' => $this->designs->primaryHex((string)$org['design']),
			'is_admin' => $this->letters->isAdmin(),
			'user_organisations' => $userOrgs,
			'active_organisation_id' => (string)($org['id'] ?? ''),
			'letters' => $letters,
			'variants' => $variants,
			'layouts' => $userLayouts,
			'internetmarke_status' => $imStatus,
			'selected' => $selected,
			'selected_id' => $selectedId,
			'message' => $msg,
			'message_error' => $err,
			'letter_links' => $letterLinks,
			'folder_url' => $this->storage->folderUrl(),
			'file_meta' => $fileMeta,
			'urls' => [
				'create' => $this->route('briefmanager.form.create', [], '/apps/briefmanager/form/create'),
				'update' => $selectedId > 0
					? $this->route('briefmanager.form.update', ['id' => $selectedId], '/apps/briefmanager/form/update/' . $selectedId)
					: '',
				'delete' => $selectedId > 0
					? $this->route('briefmanager.form.destroy', ['id' => $selectedId], '/apps/briefmanager/form/delete/' . $selectedId)
					: '',
				'select_org' => $this->route('briefmanager.form.selectOrg', [], '/apps/briefmanager/form/select-org'),
				'pdf' => $pdfUrl,
				'pdf_inline' => $pdfInlineUrl,
				'pdf_save' => $selectedId > 0
					? $this->route('briefmanager.pdf.save', ['id' => $selectedId], '/apps/briefmanager/form/pdf/' . $selectedId)
					: '',
				'tracking_refresh' => $selectedId > 0
					? $this->route('briefmanager.tracking.refresh', ['id' => $selectedId], '/apps/briefmanager/form/tracking/' . $selectedId . '/refresh')
					: '',
				'tracking_receipt' => $selectedId > 0
					? $this->route('briefmanager.tracking.receipt', ['id' => $selectedId], '/apps/briefmanager/form/tracking/' . $selectedId . '/receipt')
					: '',
				'tracking_receipt_dl' => $selectedId > 0 && !empty($selected['tracking_receipt'])
					? $this->route('briefmanager.tracking.receiptDownload', ['id' => $selectedId], '/apps/briefmanager/api/letters/' . $selectedId . '/receipt')
					: '',
				'viewer' => is_array($fileMeta) ? (string)$fileMeta['viewer_url'] : '',
				'office' => is_array($fileMeta) ? (string)$fileMeta['office_url'] : '',
				'page' => $this->route('briefmanager.page.index', [], '/apps/briefmanager/'),
			],
			'initial' => [
				'letters' => $letters,
				'variants' => $variants,
				'layouts' => $userLayouts,
				'organisation' => $org,
				'organisations' => $userOrgs,
				'designs' => $this->designs->labels(),
				'internetmarke' => $imStatus,
				'selected_id' => $selectedId,
			],
		]);
	}

	/** @param array<string, scalar> $params */
	private function route(string $name, array $params, string $fallback): string {
		try {
			$url = $this->url->linkToRoute($name, $params);
			if (is_string($url) && $url !== '') {
				return $url;
			}
		} catch (\Throwable) {
			// Fallback für CLI / Sonderfälle
		}
		return $fallback;
	}
}
