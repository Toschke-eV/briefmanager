<?php

declare(strict_types=1);

namespace OCA\BriefManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Letter> */
class LetterMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bm_letters', Letter::class);
	}

	public function find(int $id): Letter {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Letter[] */
	public function findForUser(string $userId, bool $includeTeam = true): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		if ($includeTeam) {
			$qb->where($qb->expr()->orX(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('visibility', $qb->createNamedParameter('team'))
			));
		} else {
			$qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		}
		$qb->orderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	/** @return Letter[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->orderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function countByVariant(int $variantId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'c'))->from($this->getTableName())
			->where($qb->expr()->eq('variant_id', $qb->createNamedParameter($variantId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function countTodayTitles(string $prefix): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'c'))->from($this->getTableName())
			->where($qb->expr()->like('title', $qb->createNamedParameter($prefix . '%')));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** @return Letter[] */
	public function findWithTracking(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->neq('sendungsnummer', $qb->createNamedParameter('')))
			->orderBy('updated_at', 'DESC');
		return $this->findEntities($qb);
	}

	public function tryFind(int $id): ?Letter {
		try {
			return $this->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
