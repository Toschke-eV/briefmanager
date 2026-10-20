<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Layout>
 */
class LayoutMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bm_layouts', Layout::class);
	}

	/** @return Layout[] */
	public function findForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->orderBy('is_default', 'DESC')
			->addOrderBy('name', 'ASC');
		return $this->findEntities($qb);
	}

	public function findOwned(int $id, string $userId): Layout {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	public function findDefaultForUser(string $userId): ?Layout {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('is_default', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function clearDefaultForUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_default', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->executeStatement();
	}
}
