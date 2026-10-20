<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\LetterMapper;
use OCA\BriefManager\Db\Variant;
use OCA\BriefManager\Db\VariantMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class VariantService {
	public function __construct(
		private VariantMapper $mapper,
		private LetterMapper $letters,
	) {
	}

	/** @return array<int, array<string, mixed>> */
	public function list(bool $activeOnly = false): array {
		$this->ensureSeed();
		return array_map(static fn (Variant $v) => $v->jsonSerialize(), $this->mapper->findAll($activeOnly));
	}

	public function get(int $id): ?Variant {
		try {
			return $this->mapper->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @param array<string, mixed> $data */
	public function create(array $data): Variant {
		$this->ensureSeed();
		$v = new Variant();
		$v->setSlug($this->slugify((string)($data['slug'] ?? $data['name'] ?? 'variante')));
		$v->setName(mb_substr(trim((string)($data['name'] ?? 'Variante')), 0, 190));
		$v->setDescription(isset($data['description']) ? (string)$data['description'] : null);
		$v->setAbteilung(mb_substr(trim((string)($data['abteilung'] ?? '')), 0, 190));
		$v->setInfotitel(mb_substr(trim((string)($data['infotitel'] ?? '')), 0, 190));
		$v->setActive(!empty($data['active']) || !isset($data['active']) ? 1 : 0);
		$v->setSortOrder((int)($data['sort_order'] ?? 100));
		return $this->mapper->insert($v);
	}

	/** @param array<string, mixed> $data */
	public function update(int $id, array $data): ?Variant {
		$v = $this->get($id);
		if ($v === null) {
			return null;
		}
		if (isset($data['name'])) {
			$v->setName(mb_substr(trim((string)$data['name']), 0, 190));
		}
		if (array_key_exists('description', $data)) {
			$v->setDescription($data['description'] !== null ? (string)$data['description'] : null);
		}
		if (isset($data['abteilung'])) {
			$v->setAbteilung(mb_substr(trim((string)$data['abteilung']), 0, 190));
		}
		if (isset($data['infotitel'])) {
			$v->setInfotitel(mb_substr(trim((string)$data['infotitel']), 0, 190));
		}
		if (isset($data['active'])) {
			$v->setActive(!empty($data['active']) ? 1 : 0);
		}
		if (isset($data['sort_order'])) {
			$v->setSortOrder((int)$data['sort_order']);
		}
		return $this->mapper->update($v);
	}

	public function delete(int $id): bool {
		$v = $this->get($id);
		if ($v === null || $v->getSlug() === 'default') {
			return false;
		}
		if ($this->letters->countByVariant($id) > 0) {
			return false;
		}
		$this->mapper->delete($v);
		return true;
	}

	public function ensureSeed(): void {
		if ($this->mapper->countAll() > 0) {
			return;
		}
		$seed = [
			['default', 'Standard (ohne Abteilung)', '', '', 10],
			['geschaeftsfuehrung', 'Geschäftsführung', '', 'Geschäftsführung', 20],
		];
		foreach ($seed as $row) {
			$v = new Variant();
			$v->setSlug($row[0]);
			$v->setName($row[1]);
			$v->setDescription(null);
			$v->setAbteilung($row[2]);
			$v->setInfotitel($row[3]);
			$v->setActive(1);
			$v->setSortOrder($row[4]);
			$this->mapper->insert($v);
		}
	}

	private function slugify(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'variante';
		$value = trim($value, '-');
		return $value !== '' ? mb_substr($value, 0, 64) : 'variante';
	}
}
