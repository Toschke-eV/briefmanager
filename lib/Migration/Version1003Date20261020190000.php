<?php

declare(strict_types=1);

namespace OCA\BriefManager\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Private Familienlayouts pro Benutzer + Idempotenz für Internetmarke-Käufe.
 */
class Version1003Date20261020190000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bm_layouts')) {
			$table = $schema->createTable('bm_layouts');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 190]);
			$table->addColumn('design', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'ft']);
			$table->addColumn('sender_name', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('tagline', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('address_extra', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('street', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('zip', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => '']);
			$table->addColumn('city', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('phone', Types::STRING, ['notnull' => true, 'length' => 120, 'default' => '']);
			$table->addColumn('email', Types::STRING, ['notnull' => true, 'length' => 190, 'default' => '']);
			$table->addColumn('website', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('logo_path', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('color_primary', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => '']);
			$table->addColumn('color_gray', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => '']);
			$table->addColumn('color_accent', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => '']);
			$table->addColumn('is_default', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'bm_layouts_user');
		}

		if (!$schema->hasTable('bm_postage_orders')) {
			$table = $schema->createTable('bm_postage_orders');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('letter_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$table->addColumn('idempotency_key', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('product_code', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('product_label', Types::STRING, ['notnull' => true, 'length' => 255, 'default' => '']);
			$table->addColumn('price_cents', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('voucher_layout', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'FRANKING_ZONE']);
			$table->addColumn('shop_order_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('voucher_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('tracking_number', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 40, 'default' => 'pending']);
			$table->addColumn('mode', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'preview']);
			$table->addColumn('error_message', Types::TEXT, ['notnull' => false]);
			$table->addColumn('stamp_path', Types::STRING, ['notnull' => false, 'length' => 255]);
			$table->addColumn('response_json', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['idempotency_key'], 'bm_postage_idem');
			$table->addIndex(['user_id'], 'bm_postage_user');
			$table->addIndex(['letter_id'], 'bm_postage_letter');
		}

		if ($schema->hasTable('bm_letters')) {
			$table = $schema->getTable('bm_letters');
			if (!$table->hasColumn('layout_id')) {
				$table->addColumn('layout_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
			}
		}

		return $schema;
	}
}
