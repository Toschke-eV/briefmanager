<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Internetmarke-Auftrag mit Idempotenzschlüssel.
 *
 * @method int getId()
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getLetterId()
 * @method void setLetterId(int $letterId)
 * @method string getIdempotencyKey()
 * @method void setIdempotencyKey(string $idempotencyKey)
 * @method int getProductCode()
 * @method void setProductCode(int $productCode)
 * @method string getProductLabel()
 * @method void setProductLabel(string $productLabel)
 * @method int getPriceCents()
 * @method void setPriceCents(int $priceCents)
 * @method string getVoucherLayout()
 * @method void setVoucherLayout(string $voucherLayout)
 * @method string|null getShopOrderId()
 * @method void setShopOrderId(?string $shopOrderId)
 * @method string|null getVoucherId()
 * @method void setVoucherId(?string $voucherId)
 * @method string|null getTrackingNumber()
 * @method void setTrackingNumber(?string $trackingNumber)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getMode()
 * @method void setMode(string $mode)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 * @method string|null getStampPath()
 * @method void setStampPath(?string $stampPath)
 * @method string|null getResponseJson()
 * @method void setResponseJson(?string $responseJson)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class PostageOrder extends Entity {
	protected string $userId = '';
	protected int $letterId = 0;
	protected string $idempotencyKey = '';
	protected int $productCode = 0;
	protected string $productLabel = '';
	protected int $priceCents = 0;
	protected string $voucherLayout = 'FRANKING_ZONE';
	protected ?string $shopOrderId = null;
	protected ?string $voucherId = null;
	protected ?string $trackingNumber = null;
	protected string $status = 'pending';
	protected string $mode = 'preview';
	protected ?string $errorMessage = null;
	protected ?string $stampPath = null;
	protected ?string $responseJson = null;
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('letterId', 'integer');
		$this->addType('productCode', 'integer');
		$this->addType('priceCents', 'integer');
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		$track = trim((string)($this->trackingNumber ?? ''));
		$publicTrack = null;
		if ($track !== '') {
			$publicTrack = 'https://www.deutschepost.de/de/s/sendungsverfolgung.html?piececode=' . rawurlencode($track);
		}
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'letter_id' => $this->letterId,
			'idempotency_key' => $this->idempotencyKey,
			'product_code' => $this->productCode,
			'product_label' => $this->productLabel,
			'price_cents' => $this->priceCents,
			'price_eur' => number_format($this->priceCents / 100, 2, ',', '.') . ' €',
			'voucher_layout' => $this->voucherLayout,
			'shop_order_id' => $this->shopOrderId,
			'voucher_id' => $this->voucherId,
			'tracking_number' => $this->trackingNumber,
			'tracking_public_url' => $publicTrack,
			'status' => $this->status,
			'mode' => $this->mode,
			'error_message' => $this->errorMessage,
			'stamp_path' => $this->stampPath,
			'has_stamp' => trim((string)($this->stampPath ?? '')) !== '',
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
