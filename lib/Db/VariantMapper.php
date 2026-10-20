<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Variant> */
class VariantMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bm_variants', Variant::class);
	}

	/** @return Variant[] */
	public function findAll(bool $activeOnly = false): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		if ($activeOnly) {
			$qb->where($qb->expr()->eq('active', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		}
		$qb->orderBy('sort_order', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function find(int $id): Variant {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'c'))->from($this->getTableName());
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}
}
