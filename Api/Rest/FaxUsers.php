<?php
namespace FreePBX\modules\Fax\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class FaxUsers extends Base {
	protected $module = 'fax';
	public static function getScopes() {
		return [
			'read:users' => [
				'description' => _('Read fax user settings'),
			],
			'write:users' => [
				'description' => _('Write fax user settings'),
			]
		];
	}
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - a list of users' fax settings
		 * @uri /fax/users
		 */
		$app->get('/users', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			$users = array();
			foreach (fax_get_user() as $user) {
				$users[$user['user']] = $user;
				unset($users[$user['user']]['user']);
			}

			$users = $users ? $users : false;
			return $response->withJson($users);
		})->add($this->checkReadScopeMiddleware('users'));

		/**
		 * @verb GET
		 * @returns - a list of users' fax settings
		 * @uri /fax/users/:id
		 */
		$app->get('/users/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			$users = fax_get_user($args['id']);
			if (isset($users['user'])) {
				unset($users['user']);
			}
			$users = $users ? $users : false;
			return $response->withJson($users);
		})->add($this->checkReadScopeMiddleware('users'));

		/**
		 * @verb PUT
		 * @uri /fax/users/:id
		 */
		$app->post('/users/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('fax');
			$params = $request->getParsedBody();

			$params['faxemail'] = isset($params['faxemail'])
			? $params['faxemail']
			: '';

			if (isset($args['id'], $params['faxenabled'])) {
				return $response->withJson(fax_save_user(
					$args['id'],
					$params['faxenabled'],
					$params['faxemail']));
			} else {
				return $response->withJson(false);
			}

		})->add($this->checkWriteScopeMiddleware('users'));
	}
}
