<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<PostageOrder>
 */
class PostageOrderMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bm_postage_orders', PostageOrder::class);
	}

	public function findByIdempotencyKey(string $key): ?PostageOrder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('idempotency_key', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return PostageOrder[] */
	public function findForLetter(int $letterId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('letter_id', $qb->createNamedParameter($letterId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	public function findOwned(int $id, string $userId): PostageOrder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}
}
