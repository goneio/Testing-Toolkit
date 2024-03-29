<?php

namespace Gone\Testing;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Faker\Provider;
use Gone\AppCore\App;
use Gone\AppCore\Router\Router;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @see https://github.com/fzaninotto/Faker
     *
     * @var Generator $faker
     */
    protected static $faker;

    private $defaultEnvironment = [];
    private $defaultHeaders     = [];
    private $startTime;

    /** @var Logger */
    protected $logger;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass(); // TODO: Change the autogenerated stub
        self::$faker = FakerFactory::create();
        self::$faker->addProvider(new Provider\Base(self::$faker));
        self::$faker->addProvider(new Provider\DateTime(self::$faker));
        self::$faker->addProvider(new Provider\Lorem(self::$faker));
        self::$faker->addProvider(new Provider\Internet(self::$faker));
        self::$faker->addProvider(new Provider\Payment(self::$faker));
        self::$faker->addProvider(new Provider\en_US\Person(self::$faker));
        self::$faker->addProvider(new Provider\en_US\Address(self::$faker));
        self::$faker->addProvider(new Provider\en_US\PhoneNumber(self::$faker));
        self::$faker->addProvider(new Provider\en_US\Company(self::$faker));
    }

    public function setUp()
    {
        $this->defaultEnvironment = [
            'SCRIPT_NAME' => '/index.php',
            'RAND'        => rand(0, 100000000),
        ];
        $this->defaultHeaders = [];
        $this->startTime = microtime(true);
        if (class_exists('Kint')) {
            \Kint::$max_depth = 0;
        }
        $this->logger = App::Container()->get(Logger::class);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $post
     * @param bool   $isJsonRequest
     * @param array  $extraHeaders
     *
     * @return ResponseInterface
     */
    public function request(
        string $method,
        string $path,
        $post = null,
        $isJsonRequest = true,
        $extraHeaders = []
    ) {
        /**
         * @var \Slim\App           $app
         * @var \Gone\AppCore\App $applicationInstance
         */
        $applicationInstance = $this->getApp();
        $calledClass = get_called_class();

        $app = $applicationInstance->getApp();

        if (defined("$calledClass")) {
            $modelName = $calledClass::MODEL_NAME;
            if (file_exists(APP_ROOT . "/src/Routes/{$modelName}Route.php")) {
                require(APP_ROOT . "/src/Routes/{$modelName}Route.php");
            }
        } else {
            if (file_exists(APP_ROOT . "/src/Routes.php")) {
                require(APP_ROOT . "/src/Routes.php");
            }
        }
        if (file_exists(APP_ROOT . "/src/RoutesExtra.php")) {
            require(APP_ROOT . "/src/RoutesExtra.php");
        }
        Router::Instance()->populateRoutes($app);
        $headers = array_merge($this->defaultHeaders, $extraHeaders);

        $envArray = array_merge($this->defaultEnvironment, $headers);
        $envArray = array_merge($envArray, [
            'REQUEST_URI'    => $path,
            'REQUEST_METHOD' => $method,
        ]);

        $env     = Environment::mock($envArray);
        $uri     = Uri::createFromEnvironment($env);
        $headers = Headers::createFromEnvironment($env);

        $cookies      = [];
        $serverParams = $env->all();
        $body         = new RequestBody();
        if (!is_array($post) && $post != null) {
            $body->write($post);
            $body->rewind();
        } elseif (is_array($post) && count($post) > 0) {
            $body->write(json_encode($post));
            $body->rewind();
        }

        $request = new Request($method, $uri, $headers, $cookies, $serverParams, $body);
        if ($isJsonRequest) {
            foreach ($extraHeaders as $k => $v) {
                $request = $request->withHeader($k, $v);
            }
            $request = $request->withHeader("Content-type", "application/json");
            $request = $request->withHeader("Accept", "application/json");
        }
        $response = new Response();

        // Invoke app
        $response = $applicationInstance
            ->makeClean()
            ->getApp()
            ->process($request, $response);
        $response->getBody()->rewind();

        return $response;
    }

    protected function waypoint(string $label) : void
    {
        printf(
            "[%s] %s\n",
            number_format(microtime(true) - $this->startTime),
            $label
        );
    }

    protected function setEnvironmentVariable($key, $value)
    {
        $this->defaultEnvironment[$key] = $value;
        return $this;
    }

    protected function setRequestHeader($header, $value)
    {
        $this->defaultHeaders[$header] = $value;
        return $this;
    }

    private function getApp()
    {
        return App::Instance();
    }
}
