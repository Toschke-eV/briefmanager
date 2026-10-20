<?php

declare(strict_types=1);

namespace OCA\BriefManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1002Date20261002180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('bm_letters')) {
			return null;
		}
		$table = $schema->getTable('bm_letters');
		// Nextcloud erlaubt keinen leeren Default bei notnull=true.
		$columns = [
			'empfaenger_plz' => ['type' => Types::STRING, 'length' => 16],
			'tracking_status' => ['type' => Types::STRING, 'length' => 255],
			'tracking_delivered' => ['type' => Types::STRING, 'length' => 40],
			'tracking_json' => ['type' => Types::TEXT],
			'tracking_checked' => ['type' => Types::STRING, 'length' => 40],
			'tracking_receipt' => ['type' => Types::STRING, 'length' => 255],
		];
		foreach ($columns as $name => $opts) {
			if ($table->hasColumn($name)) {
				continue;
			}
			$params = ['notnull' => false];
			if (isset($opts['length'])) {
				$params['length'] = $opts['length'];
			}
			$table->addColumn($name, $opts['type'], $params);
		}
		return $schema;
	}
}
