<?php

namespace Kanboard\Core\Controller;

use Kanboard\Controller\AppController;
use Kanboard\Core\Base;
use Kanboard\Middleware\ApplicationAuthorizationMiddleware;
use Kanboard\Middleware\AuthenticationMiddleware;
use Kanboard\Middleware\BootstrapMiddleware;
use Kanboard\Middleware\PostAuthenticationMiddleware;
use Kanboard\Plugin\TaskManager\Middleware\PasswordChangeMiddleware;
use Kanboard\Middleware\ProjectAuthorizationMiddleware;
use RuntimeException;

/**
 * Class Runner
 *
 * @package Kanboard\Core\Controller
 * @author  Frederic Guillot
 */
class Runner extends Base
{
    /**
     * Execute middleware and controller
     */
    public function execute()
    {
        try {
            $this->executeMiddleware();

            if (!$this->response->isResponseAlreadySent()) {
                $this->executeController();
            }
        } catch (PageNotFoundException $e) {
            $controllerObject = new AppController($this->container);
            $controllerObject->notFound($e->hasLayout());
        } catch (AccessForbiddenException $e) {
            $controllerObject = new AppController($this->container);
            $controllerObject->accessForbidden($e->hasLayout(), $e->getMessage());
        }
    }

    /**
     * Execute all middleware
     */
    protected function executeMiddleware()
    {
        if (DEBUG) {
            $this->logger->debug(__METHOD__);
        }

        $bootstrapMiddleware = new BootstrapMiddleware($this->container);
        $authenticationMiddleware = new AuthenticationMiddleware($this->container);
        $postAuthenticationMiddleware = new PostAuthenticationMiddleware($this->container);
        $appAuthorizationMiddleware = new ApplicationAuthorizationMiddleware($this->container);
        $projectAuthorizationMiddleware = new ProjectAuthorizationMiddleware($this->container);

        $bootstrapMiddleware->setNextMiddleware($authenticationMiddleware);
        $authenticationMiddleware->setNextMiddleware($postAuthenticationMiddleware);
        /* SUPERBEE: accounts are onboarded with a shared default password.
           This holds the user on the change-password screen until they have
           set one of their own - after two-factor, so the gate cannot be used
           to skip it, and before authorization, so no other page loads first.
           Kanboard builds this chain in code with no hook to extend it, which
           is why the line lives here rather than in the plugin. */
        $passwordChangeMiddleware = new PasswordChangeMiddleware($this->container);

        $postAuthenticationMiddleware->setNextMiddleware($passwordChangeMiddleware);
        $passwordChangeMiddleware->setNextMiddleware($appAuthorizationMiddleware);
        $appAuthorizationMiddleware->setNextMiddleware($projectAuthorizationMiddleware);

        $bootstrapMiddleware->execute();
    }

    /**
     * Execute the controller
     */
    protected function executeController()
    {
        $className = $this->getControllerClassName();

        if (DEBUG) {
            $this->logger->debug(__METHOD__.' => '.$className.'::'.$this->router->getAction());
        }

        $controllerObject = new $className($this->container);
        $controllerObject->{$this->router->getAction()}();
    }

    /**
     * Get controller class name
     *
     * @access protected
     * @return string
     * @throws RuntimeException
     */
    protected function getControllerClassName()
    {
        if ($this->router->getPlugin() !== '') {
            $className = '\Kanboard\Plugin\\'.$this->router->getPlugin().'\Controller\\'.$this->router->getController();
        } else {
            $className = '\Kanboard\Controller\\'.$this->router->getController();
        }

        if (! class_exists($className)) {
            throw new RuntimeException('Controller not found');
        }

        if (! method_exists($className, $this->router->getAction())) {
            throw new RuntimeException('Action not implemented');
        }

        return $className;
    }
}
