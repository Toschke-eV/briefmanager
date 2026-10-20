<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'form#create', 'url' => '/form/create', 'verb' => 'POST'],
		['name' => 'form#update', 'url' => '/form/update/{id}', 'verb' => 'POST'],
		['name' => 'form#destroy', 'url' => '/form/delete/{id}', 'verb' => 'POST'],
		['name' => 'form#selectOrg', 'url' => '/form/select-org', 'verb' => 'POST'],

		['name' => 'letter#index', 'url' => '/api/letters', 'verb' => 'GET'],
		['name' => 'letter#show', 'url' => '/api/letters/{id}', 'verb' => 'GET'],
		['name' => 'letter#create', 'url' => '/api/letters', 'verb' => 'POST'],
		['name' => 'letter#update', 'url' => '/api/letters/{id}', 'verb' => 'PUT'],
		['name' => 'letter#destroy', 'url' => '/api/letters/{id}', 'verb' => 'DELETE'],

		['name' => 'variant#index', 'url' => '/api/variants', 'verb' => 'GET'],
		['name' => 'variant#create', 'url' => '/api/variants', 'verb' => 'POST'],
		['name' => 'variant#update', 'url' => '/api/variants/{id}', 'verb' => 'PUT'],
		['name' => 'variant#destroy', 'url' => '/api/variants/{id}', 'verb' => 'DELETE'],

		['name' => 'organisation#show', 'url' => '/api/organisation', 'verb' => 'GET'],
		['name' => 'organisation#detail', 'url' => '/api/organisation/{id}', 'verb' => 'GET'],
		['name' => 'organisation#create', 'url' => '/api/organisation', 'verb' => 'POST'],
		['name' => 'organisation#update', 'url' => '/api/organisation', 'verb' => 'PUT'],
		['name' => 'organisation#destroy', 'url' => '/api/organisation/{id}', 'verb' => 'DELETE'],
		['name' => 'organisation#setActive', 'url' => '/api/organisation/{id}/active', 'verb' => 'PUT'],
		['name' => 'organisation#selectMine', 'url' => '/api/organisation/{id}/mine', 'verb' => 'PUT'],
		['name' => 'organisation#uploadLogo', 'url' => '/api/organisation/{id}/logo', 'verb' => 'POST'],

		['name' => 'admin#counters', 'url' => '/api/admin/counters', 'verb' => 'GET'],
		['name' => 'admin#resetCounters', 'url' => '/api/admin/counters/reset', 'verb' => 'POST'],
		['name' => 'admin#letters', 'url' => '/api/admin/letters', 'verb' => 'GET'],
		['name' => 'admin#deleteLetter', 'url' => '/api/admin/letters/{id}', 'verb' => 'DELETE'],
		['name' => 'admin#deleteAllLetters', 'url' => '/api/admin/letters', 'verb' => 'DELETE'],
		['name' => 'admin#assignments', 'url' => '/api/admin/assignments', 'verb' => 'GET'],
		['name' => 'admin#saveAssignment', 'url' => '/api/admin/assignments', 'verb' => 'PUT'],

		['name' => 'pdf#download', 'url' => '/api/letters/{id}/pdf', 'verb' => 'GET'],
		['name' => 'pdf#inline', 'url' => '/api/letters/{id}/pdf-inline', 'verb' => 'GET'],
		['name' => 'pdf#save', 'url' => '/form/pdf/{id}', 'verb' => 'POST'],

		['name' => 'tracking#refresh', 'url' => '/form/tracking/{id}/refresh', 'verb' => 'POST'],
		['name' => 'tracking#receipt', 'url' => '/form/tracking/{id}/receipt', 'verb' => 'POST'],
		['name' => 'tracking#receiptDownload', 'url' => '/api/letters/{id}/receipt', 'verb' => 'GET'],

		['name' => 'design#index', 'url' => '/api/designs', 'verb' => 'GET'],
		['name' => 'design#create', 'url' => '/api/designs', 'verb' => 'POST'],
		['name' => 'design#update', 'url' => '/api/designs/{slug}', 'verb' => 'PUT'],
		['name' => 'design#destroy', 'url' => '/api/designs/{slug}', 'verb' => 'DELETE'],

		['name' => 'layout#index', 'url' => '/api/layouts', 'verb' => 'GET'],
		['name' => 'layout#show', 'url' => '/api/layouts/{id}', 'verb' => 'GET'],
		['name' => 'layout#create', 'url' => '/api/layouts', 'verb' => 'POST'],
		['name' => 'layout#update', 'url' => '/api/layouts/{id}', 'verb' => 'PUT'],
		['name' => 'layout#destroy', 'url' => '/api/layouts/{id}', 'verb' => 'DELETE'],
		['name' => 'layout#uploadLogo', 'url' => '/api/layouts/{id}/logo', 'verb' => 'POST'],

		['name' => 'internetmarke#status', 'url' => '/api/internetmarke/status', 'verb' => 'GET'],
		['name' => 'internetmarke#catalog', 'url' => '/api/internetmarke/catalog', 'verb' => 'GET'],
		['name' => 'internetmarke#savePortokasse', 'url' => '/api/internetmarke/portokasse', 'verb' => 'PUT'],
		['name' => 'internetmarke#clearPortokasse', 'url' => '/api/internetmarke/portokasse', 'verb' => 'DELETE'],
		['name' => 'internetmarke#saveAppCredentials', 'url' => '/api/internetmarke/app-credentials', 'verb' => 'PUT'],
		['name' => 'internetmarke#preview', 'url' => '/api/letters/{letterId}/internetmarke/preview', 'verb' => 'POST'],
		['name' => 'internetmarke#purchase', 'url' => '/api/letters/{letterId}/internetmarke/purchase', 'verb' => 'POST'],
		['name' => 'internetmarke#orders', 'url' => '/api/letters/{letterId}/internetmarke/orders', 'verb' => 'GET'],
	],
];
