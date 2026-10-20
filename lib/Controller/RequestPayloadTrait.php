<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCP\IRequest;

trait RequestPayloadTrait {
	/** @return array<string, mixed> */
	protected function payload(IRequest $request): array {
		$params = $request->getParams();
		$contentType = (string)$request->getHeader('Content-Type');
		if (!str_contains(strtolower($contentType), 'application/json')) {
			return $params;
		}
		$raw = '';
		if (method_exists($request, 'getContent')) {
			try {
				$raw = (string)$request->getContent();
			} catch (\Throwable) {
				$raw = '';
			}
		}
		if ($raw === '') {
			$raw = (string)file_get_contents('php://input');
		}
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				return array_merge($params, $decoded);
			}
		}
		return $params;
	}
}
