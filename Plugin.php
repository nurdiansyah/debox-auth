<?php namespace debox\auth\jwt;

use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Console\JWTGenerateSecretCommand;
use Tymon\JWTAuth\Contracts\Providers\Auth;
use Tymon\JWTAuth\Contracts\Providers\Storage;
use Tymon\JWTAuth\Factory;
use Tymon\JWTAuth\Http\Middleware\Authenticate;
use Tymon\JWTAuth\Http\Middleware\AuthenticateAndRenew;
use Tymon\JWTAuth\Http\Middleware\Check;
use System\Classes\PluginBase;
use Tymon\JWTAuth\Http\Middleware\RefreshToken;
use Tymon\JWTAuth\Http\Parser\AuthHeaders;
use Tymon\JWTAuth\Http\Parser\Cookies;
use Tymon\JWTAuth\Http\Parser\InputSource;
use Tymon\JWTAuth\Http\Parser\Parser;
use Tymon\JWTAuth\Http\Parser\QueryString;
use Tymon\JWTAuth\Http\Parser\RouteParams;
use Tymon\JWTAuth\JWT;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\JWTGuard;
use Tymon\JWTAuth\Contracts\Providers\JWT as JWTContract;
use Tymon\JWTAuth\Claims\Factory as ClaimFactory;
use Tymon\JWTAuth\Manager;
use Tymon\JWTAuth\Providers\JWT\Namshi;
use Tymon\JWTAuth\Validators\PayloadValidator;


/**
 * Graphql Plugin Information File
 */
class Plugin extends PluginBase {
    /**
     * @var \Illuminate\Auth\AuthManager
     */
    private $auth;

    /**
     * The middleware aliases.
     *
     * @var array
     */
    protected $middlewareAliases = [
        'jwt.auth' => Authenticate::class,
        'jwt.check' => Check::class,
        'jwt.refresh' => RefreshToken::class,
        'jwt.renew' => AuthenticateAndRenew::class,
    ];

    /**
     * Plugin constructor.
     */
    public function __construct($application) {
        parent::__construct($application);
        $this->auth = $this->app['auth'];
    }


    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails() {
        return [
            'name' => 'Auth-JWT',
            'description' => 'Authentication JWT(Json Web Token)',
            'author' => 'Debox',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register() {
        $this->registerAliases();

        $this->registerJWTProvider();
        $this->registerAuthProvider();
        $this->registerStorageProvider();
        $this->registerJWTBlacklist();

        $this->registerManager();
        $this->registerTokenParser();

        $this->registerJWT();
        $this->registerJWTAuth();
        $this->registerPayloadValidator();
        $this->registerClaimFactory();
        $this->registerPayloadFactory();
        $this->registerJWTCommand();
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot() {
        $path = realpath(__DIR__ . '/../config/config.php');

        $this->publishes([$path => config_path('jwt.php')], 'config');
        $this->mergeConfigFrom($path, 'jwt');

        $this->aliasMiddleware();

        $this->extendAuthGuard();
    }

    /**
     * Alias the middleware.
     *
     * @return void
     */
    private function aliasMiddleware() {
        $router = $this->app['router'];

        $method = method_exists($router, 'aliasMiddleware') ? 'aliasMiddleware' : 'middleware';

        foreach ($this->middlewareAliases as $alias => $middleware) {
            $router->$method($alias, $middleware);
        }
    }

    private function extendAuthGuard() {
        $this->auth->extend('jwt', function ($app, $name, array $config) {
            $guard = new JWTGuard($app['jwt'],
                $app['auth']->createUserProvider($config['provider']),
                $app['request']
            );

            $app->refresh('request', $guard, 'setRequest');
            return $guard;
        });
    }

    private function registerAliases() {
        $this->app->alias('jwt', JWT::class);
        $this->app->alias('jwt.auth', JWTAuth::class);
        $this->app->alias('jwt.provider.jwt', JWTContract::class);
        $this->app->alias('jwt.provider.jwt.namshi', Namshi::class);
//        $this->app->alias('jwt.provider.jwt.lcobucci', Lcobucci::class);
        $this->app->alias('jwt.provider.auth', Auth::class);
        $this->app->alias('jwt.provider.storage', Storage::class);
        $this->app->alias('jwt.manager', Manager::class);
        $this->app->alias('jwt.blacklist', Blacklist::class);
        $this->app->alias('jwt.payload.factory', Factory::class);
        $this->app->alias('jwt.validators.payload', PayloadValidator::class);
    }

    private function registerJWTProvider() {
        $this->app->singleton('jwt.provider.jwt', function () {
            $provider = $this->config('providers.jwt');
            return new $provider(
                $this->config('jwt.secret'),
                $this->config('jwt.algo'),
                $this->config('jwt.keys')
            );
        });
    }

    /**
     * Register the bindings for the Auth provider.
     *
     * @return void
     */
    private function registerAuthProvider() {
        $this->app->singleton('jwt.provider.auth', function () {
            return $this->getConfigInstance('providers.auth');
        });
    }

    /**
     * Register the bindings for the Storage provider.
     *
     * @return void
     */
    private function registerStorageProvider() {
        $this->app->singleton('jwt.provider.storage', function () {
            return $this->getConfigInstance('providers.storage');
        });
    }

    /**
     * Register the bindings for the JWT Manager.
     *
     * @return void
     */
    private function registerManager() {
        $this->app->singleton('tymon.jwt.manager', function ($app) {
            $instance = new Manager(
                $app['jwt.provider.jwt'],
                $app['jwt.blacklist'],
                $app['jwt.payload.factory']
            );

            return $instance->setBlacklistEnabled((bool)$this->config('blacklist_enabled'))
                ->setPersistentClaims($this->config('persistent_claims'));
        });
    }

    /**
     * Register the bindings for the Token Parser.
     *
     * @return void
     */
    private function registerTokenParser() {
        $this->app->singleton('jwt.parser', function ($app) {
            $parser = new Parser(
                $app['request'],
                [
                    new AuthHeaders(),
                    new QueryString(),
                    new InputSource(),
                    new RouteParams(),
                    new Cookies(),
                ]
            );

            $app->refresh('request', $parser, 'setRequest');

            return $parser;
        });
    }

    /**
     * Register the bindings for the main JWT class.
     *
     * @return void
     */
    protected function registerJWT() {
        $this->app->singleton('jwt', function ($app) {
            return new JWT(
                $app['jwt.manager'],
                $app['jwt.parser']
            );
        });
    }

    /**
     * Register the bindings for the main JWTAuth class.
     *
     * @return void
     */
    private function registerJWTAuth() {
        $this->app->singleton('jwt.auth', function ($app) {
            return new JWTAuth(
                $app['jwt.manager'],
                $app['jwt.provider.auth'],
                $app['jwt.parser']
            );
        });
    }

    /**
     * Register the bindings for the Blacklist.
     *
     * @return void
     */
    private function registerJWTBlacklist() {
        $this->app->singleton('jwt.blacklist', function ($app) {
            $instance = new Blacklist($app['jwt.provider.storage']);

            return $instance->setGracePeriod($this->config('blacklist_grace_period'))
                ->setRefreshTTL($this->config('refresh_ttl'));
        });
    }

    /**
     * Register the bindings for the payload validator.
     *
     * @return void
     */
    private function registerPayloadValidator() {
        $this->app->singleton('jwt.validators.payload', function () {
            return (new PayloadValidator())
                ->setRefreshTTL($this->config('refresh_ttl'))
                ->setRequiredClaims($this->config('required_claims'));
        });
    }

    /**
     * Register the bindings for the Claim Factory.
     *
     * @return void
     */
    private function registerClaimFactory() {
        $this->app->singleton('jwt.claim.factory', function ($app) {
            $factory = new ClaimFactory($app['request']);
            $app->refresh('request', $factory, 'setRequest');

            return $factory->setTTL($this->config('ttl'));
        });
    }

    /**
     * Register the bindings for the Payload Factory.
     *
     * @return void
     */
    private function registerPayloadFactory() {
        $this->app->singleton('jwt.payload.factory', function ($app) {
            return new Factory(
                $app['jwt.claim.factory'],
                $app['jwt.validators.payload']
            );
        });
    }

    /**
     * Register the Artisan command.
     *
     * @return void
     */
    private function registerJWTCommand() {
        $this->registerConsoleCommand('jwt.secret', JWTGenerateSecretCommand::class);
    }

    /**
     * Helper to get the config values.
     *
     * @param  string $key
     * @param  string $default
     *
     * @return mixed
     */
    private function config($key, $default = null) {
        return config("jwt.$key", $default);
    }

    /**
     * Get an instantiable configuration instance.
     *
     * @param  string $key
     *
     * @return mixed
     */
    private function getConfigInstance($key) {
        $instance = $this->config($key);

        if (is_string($instance)) {
            return $this->app->make($instance);
        }

        return $instance;
    }
}
