<?php namespace Debox\Auth;

use Debox\Auth\Console\JWTGenerateSecretCommand;
use Debox\Auth\Providers\Jwt\Lcobucci;
use Illuminate\Foundation\AliasLoader;
use Lcobucci\JWT\Builder as JWTBuilder;
use Lcobucci\JWT\Parser as JWTParser;
use RainLab\User\Models\User;
use Tymon\JWTAuth\Blacklist;
use Tymon\JWTAuth\Contracts\Providers\Auth as AuthProvider;
use Tymon\JWTAuth\Contracts\Providers\Storage;
use Tymon\JWTAuth\Factory;
use System\Classes\PluginBase;
use Tymon\JWTAuth\Http\Parser\AuthHeaders;
use Tymon\JWTAuth\Http\Parser\Cookies;
use Tymon\JWTAuth\Http\Parser\InputSource;
use Tymon\JWTAuth\Http\Parser\Parser;
use Tymon\JWTAuth\Http\Parser\QueryString;
use Tymon\JWTAuth\Http\Parser\RouteParams;
use Tymon\JWTAuth\JWT;
use Tymon\JWTAuth\JWTAuth;
use Tymon\JWTAuth\Contracts\Providers\JWT as JWTContract;
use Tymon\JWTAuth\Claims\Factory as ClaimFactory;
use Tymon\JWTAuth\Manager;
use Tymon\JWTAuth\Validators\PayloadValidator;


/**
 * Graphql Plugin Information File
 */
class Plugin extends PluginBase {
    public $require = ['RainLab.User', 'Debox.Graphql'];
    /**
     * @var \Illuminate\Auth\AuthManager
     */

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails() {
        return [
            'name' => 'Auth',
            'description' => 'Authentication JWT(Json Web Token)',
            'author' => 'Debox',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return void
     */
    public function boot() {
        $path = realpath(__DIR__ . '/config/config.php');
        $this->publishes([$path => config_path('jwt.php')], 'config');
        $this->mergeConfigFrom($path, 'jwt');
        $this->app->bind(\Illuminate\Auth\AuthManager::class, function ($app) {
            return new \Illuminate\Auth\AuthManager($app);
        });

        $facade = AliasLoader::getInstance();
        $facade->alias('JWTAuth', '\Tymon\JWTAuth\Facades\JWTAuth');
        $facade->alias('JWTFactory', '\Tymon\JWTAuth\Facades\JWTFactory');
        $this->app->singleton('auth', function ($app) {
            return new \Illuminate\Auth\AuthManager($app);
        });
        $this->app['router']->middleware('jwt.auth', '\Tymon\JWTAuth\Middleware\GetUserFromToken');
        $this->app['router']->middleware('jwt.refresh', '\Tymon\JWTAuth\Middleware\RefreshToken');
        User::extend(function ($model) {
            $model->addDynamicMethod('getAuthApiAttributes', function () {
                return [];
            });
        });
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

    private function registerAliases() {
        $this->app->alias('jwt', JWT::class);
        $this->app->alias('jwt.auth', JWTAuth::class);
        $this->app->alias('jwt.provider.jwt', JWTContract::class);
        $this->app->alias('jwt.provider.jwt.lcobucci', Lcobucci::class);
        $this->app->alias('jwt.provider.auth', AuthProvider::class);
        $this->app->alias('jwt.provider.storage', Storage::class);
        $this->app->alias('jwt.manager', Manager::class);
        $this->app->alias('jwt.blacklist', Blacklist::class);
        $this->app->alias('jwt.payload.factory', Factory::class);
        $this->app->alias('jwt.validators.payload', PayloadValidator::class);
    }

    private function registerJWTProvider() {
        $this->registerLcobucciProvider();
        $this->app->singleton('jwt.provider.jwt', function () {
            return $this->getConfigInstance('providers.jwt');
        });
    }

    /**
     * Register the bindings for the Lcobucci JWT provider.
     *
     * @return void
     */
    private function registerLcobucciProvider() {
        $this->app->singleton('jwt.provider.jwt.lcobucci', function ($app) {
            return new Lcobucci(
                new JWTBuilder(),
                new JWTParser(),
                $this->config('secret'),
                $this->config('algo'),
                $this->config('keys')
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
        $this->app->singleton('jwt.manager', function ($app) {
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
