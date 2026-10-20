<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Privates Familienbrief-Layout eines Nextcloud-Benutzers.
 *
 * @method int getId()
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getDesign()
 * @method void setDesign(string $design)
 * @method string getSenderName()
 * @method void setSenderName(string $senderName)
 * @method string getTagline()
 * @method void setTagline(string $tagline)
 * @method string getAddressExtra()
 * @method void setAddressExtra(string $addressExtra)
 * @method string getStreet()
 * @method void setStreet(string $street)
 * @method string getZip()
 * @method void setZip(string $zip)
 * @method string getCity()
 * @method void setCity(string $city)
 * @method string getPhone()
 * @method void setPhone(string $phone)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method string getWebsite()
 * @method void setWebsite(string $website)
 * @method string|null getLogoPath()
 * @method void setLogoPath(?string $logoPath)
 * @method string getColorPrimary()
 * @method void setColorPrimary(string $colorPrimary)
 * @method string getColorGray()
 * @method void setColorGray(string $colorGray)
 * @method string getColorAccent()
 * @method void setColorAccent(string $colorAccent)
 * @method int getIsDefault()
 * @method void setIsDefault(int $isDefault)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Layout extends Entity {
	protected string $userId = '';
	protected string $name = '';
	protected string $design = 'ft';
	protected string $senderName = '';
	protected string $tagline = '';
	protected string $addressExtra = '';
	protected string $street = '';
	protected string $zip = '';
	protected string $city = '';
	protected string $phone = '';
	protected string $email = '';
	protected string $website = '';
	protected ?string $logoPath = null;
	protected string $colorPrimary = '';
	protected string $colorGray = '';
	protected string $colorAccent = '';
	protected int $isDefault = 0;
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('isDefault', 'integer');
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'name' => $this->name,
			'design' => $this->design,
			'sender_name' => $this->senderName,
			'tagline' => $this->tagline,
			'address_extra' => $this->addressExtra,
			'street' => $this->street,
			'zip' => $this->zip,
			'city' => $this->city,
			'phone' => $this->phone,
			'email' => $this->email,
			'website' => $this->website,
			'logo_path' => $this->logoPath,
			'color_primary' => $this->colorPrimary,
			'color_gray' => $this->colorGray,
			'color_accent' => $this->colorAccent,
			'is_default' => (bool)$this->isDefault,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}

	/**
	 * Org-ähnliche Struktur für PdfService.
	 *
	 * @return array<string, mixed>
	 */
	public function asOrgArray(): array {
		return [
			'id' => 'layout:' . (string)$this->id,
			'design' => $this->design,
			'name' => $this->senderName !== '' ? $this->senderName : $this->name,
			'tagline' => $this->tagline,
			'address_extra' => $this->addressExtra,
			'street' => $this->street,
			'zip' => $this->zip,
			'city' => $this->city,
			'phone' => $this->phone,
			'fax' => '',
			'email' => $this->email,
			'website' => $this->website,
			'slogan' => '',
			'iban' => '',
			'bic' => '',
			'bank_name' => '',
			'register_court' => '',
			'vr_number' => '',
			'tax_office' => '',
			'tax_id' => '',
			'board' => [],
			'seat_city' => '',
			'seat_region' => '',
			'logo_path' => (string)($this->logoPath ?? ''),
			'ref_prefix' => '',
			'ref_vorab' => '',
			'ref_pattern' => '{Y}{m}{d}-{n}',
			'title_pad' => 5,
			'ref_pad' => 3,
			'ref_reset' => 'daily',
			'howto' => '',
			'_layout_colors' => [
				'primary' => $this->colorPrimary,
				'gray' => $this->colorGray,
				'accent' => $this->colorAccent,
			],
		];
	}
}
