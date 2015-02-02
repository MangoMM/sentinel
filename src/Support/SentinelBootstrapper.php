<?php namespace Cartalyst\Sentinel\Support;
/**
 * Part of the Sentinel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Sentinel
 * @version    2.0.0
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

use InvalidArgumentException;
use Cartalyst\Sentinel\Sentinel;
use Cartalyst\Sentinel\Cookies\NativeCookie;
use Cartalyst\Sentinel\Hashing\NativeHasher;
use Cartalyst\Sentinel\Roles\RoleRepository;
use Cartalyst\Sentinel\Users\UserRepository;
use Cartalyst\Sentinel\Sessions\NativeSession;
use Cartalyst\Sentinel\Reminders\ReminderRepository;
use Cartalyst\Sentinel\Throttling\ThrottleRepository;
use Cartalyst\Sentinel\Checkpoints\ThrottleCheckpoint;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Cartalyst\Sentinel\Checkpoints\ActivationCheckpoint;
use Cartalyst\Sentinel\Activations\ActivationRepository;
use Cartalyst\Sentinel\Persistences\PersistenceRepository;

class SentinelBootstrapper {

	/**
	 * Configuration.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The event dispatcher.
	 *
	 * @var \Symfony\Component\EventDispatcher\EventDispatcher
	 */
	protected $dispatcher;

	/**
	 * Constructor.
	 *
	 * @param  arry  $config
	 * @return void
	 */
	public function __construct($config = null)
	{
		if (is_string($config))
		{
			$this->config = new ConfigRepository($config);
		}
		else
		{
			$this->config = $config ?: new ConfigRepository();
		}
	}

	/**
	 * Creates a sentinel instance.
	 *
	 * @return \Cartalyst\Sentinel\Sentinel
	 */
	public function createSentinel()
	{
		$persistence = $this->createPersistence();
		$users       = $this->createUsers();
		$roles       = $this->createRoles();
		$activations = $this->createActivations();
		$dispatcher  = $this->getEventDispatcher();

		$sentinel = new Sentinel(
			$persistence,
			$users,
			$roles,
			$activations,
			$dispatcher
		);

		$ipAddress = $this->guessIpAddress();

		$checkpoints = $this->createCheckpoints($activations, $ipAddress);

		foreach ($checkpoints as $key => $checkpoint)
		{
			$sentinel->addCheckpoint($key, $checkpoint);
		}

		$reminders = $this->createReminders($users);

		$sentinel->setActivationRepository($activations);

		$sentinel->setReminderRepository($reminders);

		return $sentinel;
	}

	/**
	 * Creates a persistences repository.
	 *
	 * @return \Cartalyst\Sentinel\Persistences\PersistenceRepository
	 */
	protected function createPersistence()
	{
		$session = $this->createSession();

		$cookie = $this->createCookie();

		return new PersistenceRepository($session, $cookie);
	}

	/**
	 * Creates a session.
	 *
	 * @return \Cartalyst\Sentinel\Sessions\NativeSession
	 */
	protected function createSession()
	{
		return new NativeSession($this->config['session']);
	}

	/**
	 * Creates a cookie.
	 *
	 * @return \Cartalyst\Sentinel\Cookies\NativeCookie
	 */
	protected function createCookie()
	{
		return new NativeCookie($this->config['cookie']);
	}

	/**
	 * Creates a user repository.
	 *
	 * @return \Cartalyst\Sentinel\Users\UserRepository
	 */
	protected function createUsers()
	{
		$hasher = $this->createHasher();

		$model = $this->config['users']['model'];

		$roles = $this->config['roles']['model'];

		$persistences = $this->config['persistences']['model'];

		if (class_exists($roles) && method_exists($roles, 'setUsersModel'))
		{
			forward_static_call_array([$roles, 'setUsersModel'], [$model]);
		}

		if (class_exists($persistences) && method_exists($persistences, 'setUsersModel'))
		{
			forward_static_call_array([$persistences, 'setUsersModel'], [$model]);
		}

		return new UserRepository($hasher, $this->getEventDispatcher(), $model);
	}

	/**
	 * Creates a hasher.
	 *
	 * @return \Cartalyst\Sentinel\Hashing\NativeHasher
	 */
	protected function createHasher()
	{
		return new NativeHasher();
	}

	/**
	 * Creates a role repository.
	 *
	 * @return \Cartalyst\Sentinel\Roles\RoleRepository
	 */
	protected function createRoles()
	{
		$model = $this->config['roles']['model'];

		$users = $this->config['users']['model'];

		if (class_exists($users) && method_exists($users, 'setRolesModel'))
		{
			forward_static_call_array([$users, 'setRolesModel'], [$model]);
		}

		return new RoleRepository($model);
	}

	/**
	 * Creates an activation repository.
	 *
	 * @return \Cartalyst\Sentinel\Activations\ActivationRepository
	 */
	protected function createActivations()
	{
		$model = $this->config['activations']['model'];

		$expires = $this->config['activations']['expires'];

		return new ActivationRepository($model, $expires);
	}

	/**
	 * Guesses the client's ip address.
	 *
	 * @return string
	 */
	protected function guessIpAddress()
	{
		foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'] as $key)
		{
			if (array_key_exists($key, $_SERVER) === true)
			{
				foreach (explode(',', $_SERVER[$key]) as $ipAddress)
				{
					$ipAddress = trim($ipAddress);

					if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false)
					{
						return $ipAddress;
					}
				}
			}
		}
	}

	/**
	 * Create an activation checkpoint.
	 *
	 * @param  \Cartalyst\Sentinel\Activations\ActivationRepository  $activations
	 * @return \Cartalyst\Sentinel\Checkpoints\ActivationCheckpoint
	 */
	protected function createActivationCheckpoint(ActivationRepository $activations)
	{
		return new ActivationCheckpoint($activations);
	}

	/**
	 * Create activation and throttling checkpoints.
	 *
	 * @param  \Cartalyst\Sentinel\Activations\ActivationRepository  $activations
	 * @param  string  $ipAddress
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function createCheckpoints(ActivationRepository $activations, $ipAddress)
	{
		$activeCheckpoints = $this->config['checkpoints'];

		$activation = $this->createActivationCheckpoint($activations);

		$throttle = $this->createThrottleCheckpoint($ipAddress);

		$checkpoints = [];

		foreach ($activeCheckpoints as $checkpoint)
		{
			if ( ! isset($$checkpoint))
			{
				throw new InvalidArgumentException("Invalid checkpoint [{$checkpoint}] given.");
			}

			$checkpoints[$checkpoint] = $$checkpoint;
		}

		return $checkpoints;
	}

	/**
	 * Create a throttle checkpoint.
	 *
	 * @param  string  $ipAddress
	 * @return \Cartalyst\Sentinel\Checkpoints\ThrottleCheckpoint
	 */
	protected function createThrottleCheckpoint($ipAddress)
	{
		$throttling = $this->createThrottling();

		return new ThrottleCheckpoint($throttling, $ipAddress);
	}

	/**
	 * Create a throttling repository.
	 *
	 * @return \Cartalyst\Sentinel\Throttling\ThrottleRepository
	 */
	protected function createThrottling()
	{
		$model = $this->config['throttling']['model'];

		foreach (['global', 'ip', 'user'] as $type)
		{
			${"{$type}Interval"} = $this->config['throttling'][$type]['interval'];

			${"{$type}Thresholds"} = $this->config['throttling'][$type]['thresholds'];
		}

		return new ThrottleRepository(
			$model,
			$globalInterval,
			$globalThresholds,
			$ipInterval,
			$ipThresholds,
			$userInterval,
			$userThresholds
		);
	}

	/**
	 * Returns the event dispatcher.
	 *
	 * @return \Symfony\Component\EventDispatcher\EventDispatcher
	 */
	protected function getEventDispatcher()
	{
		if ( ! $this->dispatcher)
		{
			$this->dispatcher = new EventDispatcher();
		}

		return $this->dispatcher;
	}

	/**
	 * Create a reminder repository.
	 *
	 * @param  \Cartalyst\Sentinel\Users\UserRepository  $users
	 * @return \Cartalyst\Sentinel\Reminders\ReminderRepository
	 */
	protected function createReminders(UserRepository $users)
	{
		$model = $this->config['reminders']['model'];

		$expires = $this->config['reminders']['expires'];

		return new ReminderRepository($users, $model, $expires);
	}

}
