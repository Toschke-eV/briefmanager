<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getVariantId()
 * @method void setVariantId(int $variantId)
 * @method int|null getLayoutId()
 * @method void setLayoutId(?int $layoutId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getEmpfaenger()
 * @method void setEmpfaenger(string $empfaenger)
 * @method string getBetreff()
 * @method void setBetreff(string $betreff)
 * @method string getAnrede()
 * @method void setAnrede(string $anrede)
 * @method string getBrieftext()
 * @method void setBrieftext(string $brieftext)
 * @method string getContentFormat()
 * @method void setContentFormat(string $contentFormat)
 * @method string getSchluss()
 * @method void setSchluss(string $schluss)
 * @method string getUnserZeichen()
 * @method void setUnserZeichen(string $unserZeichen)
 * @method string getIhrSchreibenVom()
 * @method void setIhrSchreibenVom(string $ihrSchreibenVom)
 * @method string getIhrZeichen()
 * @method void setIhrZeichen(string $ihrZeichen)
 * @method string getDatum()
 * @method void setDatum(string $datum)
 * @method string getSachbearbeiter()
 * @method void setSachbearbeiter(string $sachbearbeiter)
 * @method string getTelefon()
 * @method void setTelefon(string $telefon)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string|null getSignaturen()
 * @method void setSignaturen(?string $signaturen)
 * @method string getWindowMode()
 * @method void setWindowMode(string $windowMode)
 * @method string getVisibility()
 * @method void setVisibility(string $visibility)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getPdfFile()
 * @method void setPdfFile(?string $pdfFile)
 * @method string|null getInternetmarkePath()
 * @method void setInternetmarkePath(?string $internetmarkePath)
 * @method string|null getTrackingJson()
 * @method void setTrackingJson(?string $trackingJson)
 * @method string|null getTrackingReceipt()
 * @method void setTrackingReceipt(?string $trackingReceipt)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Letter extends Entity {
	protected string $userId = '';
	protected int $variantId = 0;
	protected ?int $layoutId = null;
	protected string $title = '';
	protected string $empfaenger = '';
	protected string $betreff = '';
	protected string $anrede = '';
	protected string $brieftext = '';
	protected string $contentFormat = 'html';
	protected string $schluss = '';
	protected string $unserZeichen = '';
	protected string $ihrSchreibenVom = '';
	protected string $ihrZeichen = '-';
	protected string $datum = '';
	protected string $sachbearbeiter = '';
	protected string $telefon = '';
	protected string $email = '';
	protected ?string $signaturen = '[]';
	protected string $windowMode = 'sichtfenster';
	protected string $visibility = 'private';
	protected string $status = 'entwurf';
	protected ?string $pdfFile = null;
	protected ?string $internetmarkePath = null;
	/** @nullable DB-Spalten (Migration 1001/1002) */
	protected ?string $sendungsnummer = null;
	protected ?string $empfaengerPlz = null;
	protected ?string $trackingStatus = null;
	protected ?string $trackingDelivered = null;
	protected ?string $trackingJson = null;
	protected ?string $trackingChecked = null;
	protected ?string $trackingReceipt = null;
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('variantId', 'integer');
		$this->addType('layoutId', 'integer');
	}

	public function getSendungsnummer(): string {
		return $this->sendungsnummer ?? '';
	}

	public function setSendungsnummer(?string $sendungsnummer): void {
		$this->sendungsnummer = $sendungsnummer ?? '';
		$this->markFieldUpdated('sendungsnummer');
	}

	public function getEmpfaengerPlz(): string {
		return $this->empfaengerPlz ?? '';
	}

	public function setEmpfaengerPlz(?string $empfaengerPlz): void {
		$this->empfaengerPlz = $empfaengerPlz ?? '';
		$this->markFieldUpdated('empfaengerPlz');
	}

	public function getTrackingStatus(): string {
		return $this->trackingStatus ?? '';
	}

	public function setTrackingStatus(?string $trackingStatus): void {
		$this->trackingStatus = $trackingStatus ?? '';
		$this->markFieldUpdated('trackingStatus');
	}

	public function getTrackingDelivered(): string {
		return $this->trackingDelivered ?? '';
	}

	public function setTrackingDelivered(?string $trackingDelivered): void {
		$this->trackingDelivered = $trackingDelivered ?? '';
		$this->markFieldUpdated('trackingDelivered');
	}

	public function getTrackingChecked(): string {
		return $this->trackingChecked ?? '';
	}

	public function setTrackingChecked(?string $trackingChecked): void {
		$this->trackingChecked = $trackingChecked ?? '';
		$this->markFieldUpdated('trackingChecked');
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(?string $variantName = null): array {
		$tracking = json_decode($this->trackingJson ?: '{}', true);
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'variant_id' => $this->variantId,
			'layout_id' => $this->layoutId,
			'variant_name' => $variantName,
			'title' => $this->title,
			'empfaenger' => $this->empfaenger,
			'betreff' => $this->betreff,
			'anrede' => $this->anrede,
			'brieftext' => $this->brieftext,
			'content_format' => $this->contentFormat,
			'schluss' => $this->schluss,
			'unser_zeichen' => $this->unserZeichen,
			'ihr_schreiben_vom' => $this->ihrSchreibenVom,
			'ihr_zeichen' => $this->ihrZeichen,
			'datum' => $this->datum,
			'sachbearbeiter' => $this->sachbearbeiter,
			'telefon' => $this->telefon,
			'email' => $this->email,
			'signaturen' => json_decode($this->signaturen ?: '[]', true) ?: [],
			'window_mode' => $this->windowMode,
			'visibility' => $this->visibility,
			'status' => $this->status,
			'pdf_file' => $this->pdfFile,
			'internetmarke_path' => $this->internetmarkePath,
			'sendungsnummer' => $this->getSendungsnummer(),
			'empfaenger_plz' => $this->getEmpfaengerPlz(),
			'tracking_status' => $this->getTrackingStatus(),
			'tracking_delivered' => $this->getTrackingDelivered(),
			'tracking_checked' => $this->getTrackingChecked(),
			'tracking_receipt' => $this->trackingReceipt,
			'tracking_events' => is_array($tracking) ? ($tracking['events'] ?? []) : [],
			'tracking_produkt' => is_array($tracking) ? (string)($tracking['produkt'] ?? '') : '',
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
