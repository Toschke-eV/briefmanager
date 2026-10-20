<?php

declare(strict_types=1);

namespace OCA\BriefManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20261002170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('bm_letters')) {
			return null;
		}
		$table = $schema->getTable('bm_letters');
		if (!$table->hasColumn('internetmarke_path')) {
			$table->addColumn('internetmarke_path', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}
		if (!$table->hasColumn('sendungsnummer')) {
			$table->addColumn('sendungsnummer', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => '',
			]);
		}
		return $schema;
	}
}
