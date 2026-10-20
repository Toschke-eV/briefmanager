<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getSlug()
 * @method void setSlug(string $slug)
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getDescription()
 * @method void setDescription(?string $description)
 * @method string getAbteilung()
 * @method void setAbteilung(string $abteilung)
 * @method string getInfotitel()
 * @method void setInfotitel(string $infotitel)
 * @method int getActive()
 * @method void setActive(int $active)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Variant extends Entity {
	protected string $slug = '';
	protected string $name = '';
	protected ?string $description = null;
	protected string $abteilung = '';
	protected string $infotitel = '';
	protected int $active = 1;
	protected int $sortOrder = 100;

	public function __construct() {
		$this->addType('active', 'integer');
		$this->addType('sortOrder', 'integer');
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'slug' => $this->slug,
			'name' => $this->name,
			'description' => $this->description,
			'abteilung' => $this->abteilung,
			'infotitel' => $this->infotitel,
			'active' => (bool)$this->active,
			'sort_order' => $this->sortOrder,
		];
	}
}
