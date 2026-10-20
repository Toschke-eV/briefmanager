<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Letter;
use OCA\BriefManager\Design\DesignRegistry;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use TCPDF;

/**
 * Briefpapier-PDF nahe am WordPress-TEBS_PDF (DIN 5008 Fenster, Infoblock, Fußzeile).
 */
class PdfService {
	public function __construct(
		private OrgAssignmentService $assignments,
		private VariantService $variants,
		private DesignRegistry $designs,
		private IAppData $appData,
		private LogoService $logos,
		private StampService $stamps,
		private LayoutService $layouts,
	) {
	}

	public function render(Letter $letter): string {
		$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
		if (is_readable($autoload)) {
			require_once $autoload;
		}
		if (!class_exists(TCPDF::class)) {
			throw new \RuntimeException('TCPDF fehlt. Bitte composer install im App-Ordner ausführen.');
		}

		$org = $this->resolveOrgContext($letter);
		$designSlug = (string)$org['design'];
		$design = $this->designs->get($designSlug !== '' ? $designSlug : 'muster');
		$design = $this->applyLayoutColors($design, $org);
		$variant = $this->variants->get($letter->getVariantId());
		$blue = $design['blue'];
		$gray = $design['gray'];
		$compact = in_array($designSlug, ['aab', 'sm'], true) || $designSlug === '';
		$windowMode = $letter->getWindowMode() === 'sichtfenster';
		$hasStamp = trim((string)($letter->getInternetmarkePath() ?? '')) !== '';

		$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
		$pdf->SetCreator('Briefmanager');
		$pdf->SetAuthor((string)$org['name']);
		$pdf->SetTitle($letter->getBetreff() !== '' ? $letter->getBetreff() : $letter->getTitle());
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetMargins(20, 10, 20);
		$pdf->SetAutoPageBreak(true, 48);
		$pdf->AddPage();

		$this->foldMarks($pdf, $gray);

		$logoW = (float)($design['logo_w'] ?? 28);
		$logoY = 8.0;
		$logoPath = $this->resolveLogoPath($org, $design);
		$logoH = $logoW;
		if ($logoPath !== null) {
			$sz = @getimagesize($logoPath);
			if (is_array($sz) && ($sz[0] ?? 0) > 0) {
				$logoH = $logoW * ($sz[1] / $sz[0]);
			}
			if ($logoY + $logoH > 44.0) {
				$logoH = 44.0 - $logoY;
				if (is_array($sz) && ($sz[1] ?? 0) > 0) {
					$logoW = $logoH * ($sz[0] / $sz[1]);
				}
			}
			$logoX = 190.0 - $logoW - 4.0;
			$pdf->Image($logoPath, $logoX, $logoY, $logoW, $logoH, '', '', '', false, 300);
		} else {
			$logoX = 162.0;
		}

		$leftX = 20.0;
		$titleW = max(60.0, $logoX - $leftX - 3.0);
		$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
		$pdf->SetFont('dejavusans', 'B', (float)$design['title_size']);
		$pdf->SetXY($leftX, 12);
		$pdf->MultiCell($titleW, (float)$design['title_size'] * 0.42, (string)$org['name'], 0, 'L');
		if (!empty($org['tagline'])) {
			$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
			$pdf->SetFont('dejavusans', '', (float)$design['tagline_size']);
			$pdf->SetX($leftX);
			$pdf->MultiCell($titleW, (float)$design['tagline_size'] * 0.5, (string)$org['tagline'], 0, 'L');
		}

		// Infoblock rechts - mit Internetmarke weiter unten, Frankierzone freilassen
		$infoX = 120.0;
		$infoY = $windowMode ? 45.0 : ($logoY + $logoH + 1.5);
		if ($hasStamp && !$windowMode) {
			$infoY = max($infoY, 72.0);
		}
		$pdf->SetXY($infoX, $infoY);
		$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
		$pdf->SetFont('dejavusans', 'B', $compact ? 8 : 11);
		$pdf->Cell(70, $this->pt($compact ? 9.5 : 13.5), (string)$org['name'], 0, 2, 'L');
		$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
		$pdf->SetFont('dejavusans', '', $compact ? 7.1 : 8.2);
		$addressExtra = trim((string)($org['address_extra'] ?? ''));
		$addressBlock = ($addressExtra !== '' ? $addressExtra . "\n" : '')
			. (string)$org['street'] . "\n"
			. trim(($org['zip'] ?? '') . ' ' . ($org['city'] ?? ''));
		if ($compact) {
			$addressBlock .= "\n" . (string)$org['email'] . "\n" . (string)$org['website'];
		} else {
			$addressBlock .= "\n\nTelefon: " . (string)$org['phone']
				. "\nTelefax: " . (string)$org['fax']
				. "\n\n" . (string)$org['email']
				. "\n" . (string)$org['website'];
		}
		$pdf->MultiCell(70, $this->pt($compact ? 8.5 : 9.6), $addressBlock, 0, 'L', false, 1);

		$infoTitle = trim((string)($variant?->getInfotitel() ?: ($variant?->getAbteilung() ?: '')));
		if ($infoTitle !== '') {
			$pdf->Ln(2);
			$pdf->SetX($infoX);
			$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
			$pdf->SetFont('dejavusans', 'B', $compact ? 7.1 : 9);
			$pdf->Cell(70, $this->pt($compact ? 8.5 : 10.5), $infoTitle, 0, 2, 'L');
		}

		$labelSize = $compact ? 7.1 : 6.4;
		$valueSize = $compact ? 7.1 : 8.4;
		foreach ([
			['Sachbearbeiter:', $letter->getSachbearbeiter()],
			['Telefon:', $letter->getTelefon()],
			['Emailadresse:', $letter->getEmail()],
		] as [$label, $value]) {
			if (trim($value) === '') {
				continue;
			}
			$pdf->Ln(1);
			$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
			$pdf->SetFont('dejavusans', '', $labelSize);
			$pdf->SetX($infoX);
			$pdf->Cell(70, $this->pt($compact ? 8.5 : 7.2), $label, 0, 2, 'L');
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('dejavusans', '', $valueSize);
			$pdf->SetX($infoX);
			$pdf->Cell(70, $this->pt($compact ? 8.5 : 9.8), $value, 0, 2, 'L');
		}
		$infoBottomY = $pdf->GetY();

		if ($windowMode) {
			$pdf->SetXY(20, 45.0);
			$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
			$pdf->SetFont('dejavusans', '', 6);
			$pdf->Cell(85, 4, trim(implode(' · ', array_filter([
				(string)$org['name'],
				(string)$org['street'],
				trim(($org['zip'] ?? '') . ' ' . ($org['city'] ?? '')),
			]))), 0, 2, 'L');
			$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
			$pdf->Line(20, 50.0, 105, 50.0);
			$pdf->SetXY(20, 62.7);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('dejavusans', '', 10);
			$pdf->MultiCell(85, 4.5, trim($letter->getEmpfaenger()), 0, 'L');
		}

		$refY = $infoBottomY + 6;
		if ($windowMode) {
			$refY = max($refY, 92.0);
		}
		if ($hasStamp) {
			$refY = max($refY, 108.0);
		}
		$pdf->SetY($refY);
		$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
		$pdf->SetFont('dejavusans', '', 7.3);
		$pdf->Cell(45, $this->pt(8.5), 'Unser Zeichen', 0, 0, 'L');
		$pdf->Cell(45, $this->pt(8.5), 'Ihr Schreiben vom', 0, 0, 'L');
		$pdf->Cell(45, $this->pt(8.5), 'Ihr Zeichen', 0, 0, 'L');
		$pdf->Cell(30, $this->pt(8.5), 'Datum', 0, 1, 'L');
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('dejavusans', '', 9.5);
		$pdf->Cell(45, $this->pt(11), $letter->getUnserZeichen(), 0, 0, 'L');
		$pdf->Cell(45, $this->pt(11), $letter->getIhrSchreibenVom(), 0, 0, 'L');
		$pdf->Cell(45, $this->pt(11), $letter->getIhrZeichen(), 0, 0, 'L');
		$pdf->Cell(30, $this->pt(11), $letter->getDatum(), 0, 1, 'L');

		$pdf->Ln(2);
		$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
		$pdf->SetFont('dejavusans', 'B', 10.5);
		$pdf->MultiCell(0, $this->pt(12.5), $letter->getBetreff(), 0, 'L');
		$pdf->Ln(1);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('dejavusans', '', 10.5);
		$pdf->setCellHeightRatio(12.5 / 10.5);
		if ($letter->getAnrede() !== '') {
			$pdf->MultiCell(0, $this->pt(12.5), $letter->getAnrede(), 0, 'L');
			$pdf->Ln(1);
		}
		$body = $letter->getBrieftext();
		if ($letter->getContentFormat() === 'html' && str_contains($body, '<')) {
			$pdf->writeHTMLCell(0, 0, '', '', $this->sanitizeHtml($body), 0, 1, false, true, 'L', true);
		} else {
			$pdf->MultiCell(0, $this->pt(12.5), $body, 0, 'L');
		}
		$pdf->Ln(2);
		$pdf->MultiCell(0, $this->pt(12.5), $letter->getSchluss(), 0, 'L');

		$signers = json_decode($letter->getSignaturen() ?: '[]', true);
		if (is_array($signers) && $signers !== []) {
			$pdf->Ln(8);
			$colW = min(55, 160 / count($signers));
			$x = 20.0;
			$y = $pdf->GetY();
			foreach ($signers as $s) {
				if (!is_array($s)) {
					continue;
				}
				$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
				$pdf->Line($x, $y + 10, $x + $colW - 5, $y + 10);
				$pdf->SetXY($x, $y + 12);
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('dejavusans', 'B', 8);
				$pdf->Cell($colW - 5, 4, (string)($s['name'] ?? ''), 0, 2, 'C');
				$pdf->SetFont('dejavusans', '', 7);
				$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
				$pdf->Cell($colW - 5, 3.5, (string)($s['amt'] ?? ''), 0, 2, 'C');
				$x += $colW;
			}
		}

		$pdf->SetAutoPageBreak(false, 0);
		$this->drawFooter($pdf, $org, $design, $addressExtra, $designSlug);

		$binary = $pdf->Output('', 'S');
		if ($hasStamp) {
			$binary = $this->applyInternetmarke($binary, (string)$letter->getInternetmarkePath());
		}
		return $binary;
	}

	/**
	 * Internetmarke (DIN-A4-PDF) als Hintergrund unter den Brief legen.
	 */
	private function applyInternetmarke(string $documentBinary, string $stampKey): string {
		$stampPath = $this->stamps->resolveToLocalPath($stampKey);
		if ($stampPath === null) {
			throw new \RuntimeException('Hinterlegte Internetmarke wurde nicht gefunden.');
		}
		if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
			throw new \RuntimeException('FPDI fehlt für die Internetmarke. Bitte composer install ausführen.');
		}
		$source = tempnam(sys_get_temp_dir(), 'bmletter_') . '.pdf';
		file_put_contents($source, $documentBinary);
		try {
			$pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->setSourceFile($stampPath);
			$stampTpl = $pdf->importPage(1);
			$pageCount = $pdf->setSourceFile($source);
			for ($page = 1; $page <= $pageCount; $page++) {
				$pdf->AddPage();
				if ($page === 1) {
					$pdf->useTemplate($stampTpl, 0, 0, 210, 297);
				}
				$tpl = $pdf->importPage($page);
				$pdf->useTemplate($tpl, 0, 0, 210, 297);
			}
			return $pdf->Output('', 'S');
		} finally {
			@unlink($source);
			if (str_contains($stampPath, sys_get_temp_dir())) {
				@unlink($stampPath);
			}
		}
	}

	public function store(Letter $letter, string $binary): string {
		try {
			$folder = $this->appData->getFolder('pdf');
		} catch (NotFoundException) {
			$folder = $this->appData->newFolder('pdf');
		}
		$fileName = 'letter-' . $letter->getId() . '.pdf';
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($binary);
		return $fileName;
	}

	/** @param array<string, mixed> $org @param array<string, mixed> $design */
	private function resolveLogoPath(array $org, array $design): ?string {
		$custom = trim((string)($org['logo_path'] ?? ''));
		if ($custom !== '') {
			$fromApp = $this->logos->resolveToLocalPath($custom);
			if ($fromApp !== null) {
				return $fromApp;
			}
			if (is_readable($custom)) {
				return $custom;
			}
		}
		// Bundled Preset-Logo nur bei gewähltem Design, nicht bei leerer Vorauswahl
		if (trim((string)($org['design'] ?? '')) === '') {
			return null;
		}
		$bundled = dirname(__DIR__, 2) . '/img/designs/' . ($design['logo'] ?? '');
		if (is_readable($bundled)) {
			return $bundled;
		}
		return null;
	}

	private function pt(float $pt): float {
		return $pt * 25.4 / 72;
	}

	/** @param int[] $gray */
	private function foldMarks(TCPDF $pdf, array $gray): void {
		$pdf->SetDrawColor($gray[0], $gray[1], $gray[2]);
		$pdf->SetLineWidth(0.2);
		$pdf->Line(0, 105, 5, 105);
		$pdf->Line(0, 210, 5, 210);
	}

	private function sanitizeHtml(string $html): string {
		$allowed = '<p><br><b><strong><i><em><u><ul><ol><li>';
		return strip_tags($html, $allowed);
	}

	/**
	 * @param array<string, mixed> $org
	 * @param array<string, mixed> $design
	 */
	private function drawFooter(TCPDF $pdf, array $org, array $design, string $addressExtra, string $slug): void {
		$blue = $design['blue'];
		$gray = $design['gray'];
		$accent = $design['accent'];
		$lineColor = $design['line'] ?? [155, 184, 216];
		$compact = in_array($slug, ['aab', 'sm'], true) || $slug === '';
		$x0 = 20.0;
		$width = 170.0;
		$lh = $this->pt(7.5);
		$bannerH = 3.6;
		$bannerY = 297.0 - 7.0 - $bannerH;

		$col1 = array_values(array_filter([
			(string)($org['name'] ?? ''),
			$addressExtra,
			(string)($org['street'] ?? ''),
			trim(($org['zip'] ?? '') . ' ' . ($org['city'] ?? '')),
			$compact ? '' : ((($org['phone'] ?? '') !== '') ? 'Tel.: ' . $org['phone'] : ''),
			(string)($org['website'] ?? ''),
			(string)($org['email'] ?? ''),
		], static fn ($line) => trim((string)$line) !== ''));

		$board = ['Vorstand:'];
		$members = is_array($org['board'] ?? null) ? $org['board'] : [];
		foreach (array_slice($members, 0, 5) as $member) {
			if (!is_array($member)) {
				continue;
			}
			$nameLine = trim((string)($member['name'] ?? ''));
			if ($nameLine === '') {
				continue;
			}
			$role = trim((string)($member['role'] ?? ''));
			$board[] = $role !== '' ? $nameLine . ' (' . $role . ')' : $nameLine;
		}
		$board[] = 'Vereinssitz:';
		$seat = trim((string)($org['seat_city'] ?? ''));
		$region = trim((string)($org['seat_region'] ?? ''));
		$board[] = $region !== '' ? ($seat !== '' ? $seat . ' (' . $region . ')' : $region) : $seat;

		$iban = trim(str_replace(' ', '', (string)($org['iban'] ?? '')));
		$col3 = array_values(array_filter([
			(($org['register_court'] ?? '') !== '') ? 'Registergericht: ' . $org['register_court'] : '',
			(($org['vr_number'] ?? '') !== '') ? (string)$org['vr_number'] : '',
			(($org['bank_name'] ?? '') !== '') ? 'Bank: ' . $org['bank_name'] : '',
			$iban !== '' ? 'IBAN: ' . $iban : '',
			(($org['bic'] ?? '') !== '') ? 'BIC: ' . $org['bic'] : '',
		], static fn ($line) => trim((string)$line) !== ''));

		$columns = [
			$col1,
			array_values(array_filter($board, static fn ($line) => trim((string)$line) !== '')),
			$col3,
		];
		$maxLines = max(1, ...array_map('count', $columns));
		$blockH = $maxLines * $lh;
		$topY = $bannerY - 2.5 - $blockH;

		$pdf->SetDrawColor($lineColor[0], $lineColor[1], $lineColor[2]);
		$pdf->SetLineWidth(0.35);
		$pdf->Line($x0, $topY - 2, $x0 + $width, $topY - 2);

		$colW = $width / 3;
		$pdf->SetFont('dejavusans', '', 6.2);
		$pdf->SetTextColor($gray[0], $gray[1], $gray[2]);
		foreach ($columns as $i => $lines) {
			$pdf->SetXY($x0 + $i * $colW, $topY);
			$pdf->MultiCell($colW - 2, $lh, implode("\n", $lines), 0, 'L');
		}

		$pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
		$pdf->Rect($x0, $bannerY, $width * 0.72, $bannerH, 'F');
		$pdf->SetFillColor($accent[0], $accent[1], $accent[2]);
		$pdf->Rect($x0 + $width * 0.72, $bannerY, $width * 0.28, $bannerH, 'F');
	}

	/**
	 * Familienlayout des Brief-Eigentümers hat Vorrang vor Vereinsorganisation.
	 *
	 * @return array<string, mixed>
	 */
	private function resolveOrgContext(Letter $letter): array {
		$layoutId = (int)($letter->getLayoutId() ?? 0);
		if ($layoutId > 0) {
			$layout = $this->layouts->getOwned($layoutId);
			if ($layout === null) {
				// Admin darf fremden Brief rendern: Layout über Mapper indirekt via canUse-Fallback
				try {
					$all = $this->layouts->listMine();
					foreach ($all as $row) {
						if ((int)($row['id'] ?? 0) === $layoutId) {
							break;
						}
					}
				} catch (\Throwable) {
				}
			}
			if ($layout !== null && $this->layouts->canUse($layout)) {
				return $layout->asOrgArray();
			}
		}
		$fallback = $this->layouts->defaultForCurrentUser();
		if ($fallback !== null && ($letter->getLayoutId() === null || (int)$letter->getLayoutId() === 0)) {
			// Nur wenn Brief kein Layout gesetzt hat und Benutzer ein Default-Familienlayout hat:
			// Vereinsorg bleibt Standard für Vereinsbriefe; Familienbriefe setzen layout_id explizit.
		}
		return $this->assignments->activeOrgForCurrentUser();
	}

	/**
	 * @param array<string, mixed> $design
	 * @param array<string, mixed> $org
	 * @return array<string, mixed>
	 */
	private function applyLayoutColors(array $design, array $org): array {
		$colors = is_array($org['_layout_colors'] ?? null) ? $org['_layout_colors'] : [];
		foreach (['primary' => 'blue', 'gray' => 'gray', 'accent' => 'accent'] as $src => $dst) {
			$hex = trim((string)($colors[$src] ?? ''));
			$rgb = $this->hexToRgb($hex);
			if ($rgb !== null) {
				$design[$dst] = $rgb;
			}
		}
		return $design;
	}

	/** @return int[]|null */
	private function hexToRgb(string $hex): ?array {
		if (preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m) !== 1) {
			return null;
		}
		$h = $m[1];
		return [
			hexdec(substr($h, 0, 2)),
			hexdec(substr($h, 2, 2)),
			hexdec(substr($h, 4, 2)),
		];
	}
}
