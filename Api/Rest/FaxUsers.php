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
			$users = [];
			foreach (\FreePBX::Fax()->listUsers() as $user)
			{
				$users[$user['user']] = $user;
				unset($users[$user['user']]['user']);
			}

			$users = $users ?: false;
			$response->getBody()->write(json_encode($users));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('users'));

		/**
		 * @verb GET
		 * @returns - a list of users' fax settings
		 * @uri /fax/users/:id
		 */
		$app->get('/users/{id}', function ($request, $response, $args) {
			$users = \FreePBX::Fax()->getUser($args['id']);
			if (isset($users['user']))
			{
				unset($users['user']);
			}
			$users = $users ?: false;
			$response->getBody()->write(json_encode($users));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkReadScopeMiddleware('users'));

		/**
		 * @verb PUT
		 * @uri /fax/users/:id
		 */
		$app->post('/users/{id}', function ($request, $response, $args)
		{
			$params = $request->getParsedBody();
			$params['faxemail'] ??= '';

			if (isset($args['id'], $params['faxenabled']))
			{
				$response->getBody()->write(json_encode(\FreePBX::Fax()->saveUser($args['id'] ?? '',$params['faxenabled'] ?? '',$params['faxemail'] ?? '')));
				return $response->withHeader('Content-Type', 'application/json');
			} else {
				$response->getBody()->write(json_encode(false));
				return $response->withHeader('Content-Type', 'application/json');
			}

		})->add($this->checkWriteScopeMiddleware('users'));
	}
}
