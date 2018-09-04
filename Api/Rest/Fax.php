<?php
namespace FreePBX\modules\Fax\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Fax extends Base {
	protected $module = 'fax';
	public static function getScopes() {
		return [
			'read:settings' => [
				'description' => _('Read fax settings'),
			],
			'write:settings' => [
				'description' => _('Write fax settings'),
			]
		];
	}
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - list of fax settings
		 * @uri /fax
		 */
		$app->get('/', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			return $response->withJson(fax_get_settings());
		})->add($this->checkReadScopeMiddleware('settings'));

		/**
		* @verb GET
		 * @returns - list of fax related modules that may be installed
		 * @uri /fax/detect
		 */
		$app->get('/detect', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			return $response->withJson(fax_detect());
		})->add($this->checkReadScopeMiddleware('settings'));

		/**
		 * Updates the fax settings
		 * @verb POST
		 * @returns - the freshly created settings
		 * @uri /fax
		 */
		$app->post('/', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			$params = $request->getParsedBody();
			return $response->withJson(fax_save_settings($params));
		})->add($this->checkWriteScopeMiddleware('settings'));
	}
}
