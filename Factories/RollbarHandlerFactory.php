<?php

namespace Rollbar\Symfony\RollbarBundle\Factories;

use Psr\Log\LogLevel;
use Monolog\Handler\RollbarHandler;
use Rollbar\Rollbar;
use Rollbar\Symfony\RollbarBundle\DependencyInjection\RollbarExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class RollbarHandlerFactory
{
    public function __construct(ContainerInterface $container)
    {
        $config = $container->getParameter(RollbarExtension::ALIAS . '.config');

        if (isset($_ENV['ROLLBAR_TEST_TOKEN']) && $_ENV['ROLLBAR_TEST_TOKEN']) {
            $config['access_token'] = $_ENV['ROLLBAR_TEST_TOKEN'];
        }

        if (!empty($config['person_fn']) && is_callable($config['person_fn'])) {
            $config['person'] = null;
        } elseif (empty($config['person'])) {
            $config['person_fn'] = static function () use ($container) {
                try {
                    $token = $container->get('security.token_storage')->getToken();

                    if ($token) {
                        $user = $token->getUser();
                        $serializer = $container->get('serializer');
                        return \json_decode($serializer->serialize($user, 'json'), true, 512, JSON_THROW_ON_ERROR);
                    }
                } catch (\Throwable $exception) {
                    // Ignore
                }
            };
        }

        if (!empty($config['check_ignore'])) {
            if (is_callable($config['check_ignore'])) {
                $config['check_ignore'] = static function(...$args) use ($config) {
                    return call_user_func($config['check_ignore'], ...$args);
                };
            } elseif (preg_match('/\@[a-z0-9_\/\.]+/i', $config['check_ignore'])) {
                if (is_callable($service = $container->get(substr($config['check_ignore'], 1)))) {
                    $config['check_ignore'] = $service;
                }
            } else {
                $config['check_ignore'] = null;
            }
        }

        $config['framework'] = 'symfony ' . Kernel::VERSION;

        Rollbar::init($config, false, false, false);
    }

    public function createRollbarHandler(): RollbarHandler
    {
        return new RollbarHandler(Rollbar::logger(), LogLevel::ERROR);
    }
}
