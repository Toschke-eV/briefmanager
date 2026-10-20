<?php

declare(strict_types=1);

namespace OCA\BriefManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261002160000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bm_variants')) {
			$table = $schema->createTable('bm_variants');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('slug', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('abteilung', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('infotitel', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('active', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 100]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['slug'], 'bm_variants_slug');
		}

		if (!$schema->hasTable('bm_letters')) {
			$table = $schema->createTable('bm_letters');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('variant_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('empfaenger', Types::TEXT, ['notnull' => true]);
			$table->addColumn('betreff', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('anrede', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('brieftext', Types::TEXT, ['notnull' => true]);
			$table->addColumn('content_format', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'html']);
			$table->addColumn('schluss', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('unser_zeichen', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('ihr_schreiben_vom', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('ihr_zeichen', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '-']);
			$table->addColumn('datum', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('sachbearbeiter', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('telefon', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('email', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('signaturen', Types::TEXT, ['notnull' => false]);
			$table->addColumn('window_mode', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'sichtfenster']);
			$table->addColumn('visibility', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'private']);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 40, 'default' => 'entwurf']);
			$table->addColumn('pdf_file', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'bm_letters_user');
			$table->addIndex(['variant_id'], 'bm_letters_variant');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Seed happens in VariantService on first use / via InstallRepair
	}
}
