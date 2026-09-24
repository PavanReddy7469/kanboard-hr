<?php

namespace Kanboard\Plugin\TaskManager\Middleware;

use Kanboard\Core\Controller\BaseMiddleware;
use Kanboard\Model\UserModel;

/**
 * Holds a signed-in user on the change-password screen until they have set a
 * password of their own.
 *
 * Kanboard has no such gate, so an onboarding default stays live on an account
 * for as long as its owner never gets round to changing it - which for most
 * people is forever. Without this, one leaked default is every account.
 *
 * Modelled on core's PostAuthenticationMiddleware, which does the same job for
 * two-factor codes.
 *
 * @package Kanboard\Plugin\TaskManager\Middleware
 */
class PasswordChangeMiddleware extends BaseMiddleware
{
    public function execute()
    {
        if ($this->shouldGate()) {
            $this->nextMiddleware = null;

            if ($this->request->isAjax()) {
                /* A background request must not be answered with the change
                   form; 403 lets the page's own handler surface it. */
                $this->response->text('Password change required', 403);
            } else {
                $this->response->redirect($this->helper->url->to(
                    'UserCredentialController',
                    'changePassword',
                    array('user_id' => $this->userSession->getId())
                ));
            }

            return;
        }

        $this->next();
    }

    /**
     * @return boolean
     */
    protected function shouldGate()
    {
        if (! $this->userSession->isLogged()) {
            return false;
        }

        $controller = strtolower($this->router->getController());
        $action     = strtolower($this->router->getAction());

        /* The pages needed to comply, plus the way out. Excluding these is
           what stops the redirect becoming a loop. */
        $allowed = array(
            'usercredentialcontroller' => array('changepassword', 'savepassword'),
            'authcontroller'           => array('logout'),
            'twofactorcontroller'      => array('code', 'check'),
        );

        if (isset($allowed[$controller]) && in_array($action, $allowed[$controller], true)) {
            return false;
        }

        return $this->mustChangePassword($this->userSession->getId());
    }

    /**
     * Read straight from the table rather than the session copy: an
     * administrator resetting somebody's password has to take effect on that
     * person's next request, not whenever their session happens to refresh.
     *
     * @param  integer $userId
     * @return boolean
     */
    protected function mustChangePassword($userId)
    {
        $value = $this->db
            ->table(UserModel::TABLE)
            ->eq('id', (int) $userId)
            ->findOneColumn('must_change_password');

        return ! empty($value);
    }
}
