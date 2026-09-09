<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Security\Role;

/**
 * Inline editing of project details.
 *
 * Click the project name, type a new one, press Enter - the same gesture as
 * renaming a file. There is no form page and no round trip to a settings
 * screen for what is a one-word change.
 *
 * Every save still goes through projectModel->update(), so the backdating
 * rule and every other model-level guarantee apply exactly as they do to the
 * full edit form. This controller adds a faster way in, not a way round.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class ProjectInlineController extends BaseController
{
    /**
     * Fields that may be changed this way, and how each is validated.
     *
     * Deliberately narrow. Ownership, permissions and privacy are not here:
     * those deserve the deliberation of a real form, not an inline edit.
     */
    protected function editableFields()
    {
        return array('name', 'description', 'start_date', 'end_date');
    }

    /**
     * Renaming, or changing a detail, is a project-manager action.
     *
     * @param  array $project
     * @return boolean
     */
    protected function canEdit(array $project)
    {
        if ($this->userSession->isAdmin()) {
            return true;
        }

        return $this->helper->projectRole->getProjectUserRole($project['id']) === Role::PROJECT_MANAGER;
    }

    /**
     * Save one field. Responds with JSON so the page can update in place.
     *
     * @access public
     */
    public function save()
    {
        $project = $this->getProject();

        /* A reusable token, not a single-use one: the page is not reloaded
           between edits, so a consumed token would make the second rename
           fail. This is the same token the board's drag-and-drop uses. */
        $this->checkReusableGETCSRFParam();

        if (! $this->canEdit($project)) {
            $this->response->json(array(
                'ok'      => false,
                'message' => t('Only an administrator or the project manager can edit this project.'),
            ), 403);
            return;
        }

        /* getRawValue reads $_POST directly. getValues() is not usable here:
           it insists on a CSRF token inside the body, and ours travels in the
           URL so the same reusable token can serve several inline edits. */
        $field = (string) $this->request->getRawValue('field');
        $value = $this->request->getRawValue('value');

        if ($value === null) {
            $value = '';
        }

        if (! in_array($field, $this->editableFields(), true)) {
            $this->response->json(array(
                'ok'      => false,
                'message' => t('That field cannot be edited here.'),
            ), 400);
            return;
        }

        $value = trim((string) $value);

        if ($field === 'name') {
            if ($value === '') {
                $this->response->json(array(
                    'ok'      => false,
                    'message' => t('The project name cannot be empty.'),
                ), 400);
                return;
            }

            if (mb_strlen($value) > 191) {
                $this->response->json(array(
                    'ok'      => false,
                    'message' => t('The maximum length is %d characters', 191),
                ), 400);
                return;
            }

            // Unchanged is a no-op, not an error: pressing Enter without
            // typing anything should simply close the editor.
            if ($value === $project['name']) {
                $this->response->json(array('ok' => true, 'value' => $value, 'unchanged' => true));
                return;
            }
        }

        $values = array('id' => $project['id'], $field => $value);

        if ($this->projectModel->update($values)) {
            $this->response->json(array(
                'ok'    => true,
                'field' => $field,
                'value' => $value,
            ));
            return;
        }

        /* projectModel->update() returns false when the backdating rule
           refuses a date. It records why, so say that rather than a generic
           failure. */
        $reason = method_exists($this->projectModel, 'getBackdatingRefusal')
            ? $this->projectModel->getBackdatingRefusal()
            : '';

        $this->response->json(array(
            'ok'      => false,
            'message' => $reason !== '' ? $reason : t('Unable to update this project.'),
        ), 400);
    }
}
