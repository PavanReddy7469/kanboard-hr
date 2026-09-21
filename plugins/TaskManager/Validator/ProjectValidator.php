<?php

namespace Kanboard\Plugin\TaskManager\Validator;

use SimpleValidator\Validator;
use SimpleValidator\Validators;

/**
 * Adds the airframe-type rules to project creation.
 *
 * Core already refuses a code that another project holds - it has a Unique
 * rule on the column - so that half is inherited rather than rewritten. What
 * is added here is the shape: a code has to be the selected type's prefix
 * followed by two digits, so MC01 cannot be filed under FixedWing and a stray
 * "X9" cannot become a project code at all.
 *
 * Only creation is tightened. Projects made before the types existed carry a
 * random four-character code and no type, and editing one of those must not
 * start failing because of a rule that did not exist when it was made.
 *
 * @package Kanboard\Plugin\TaskManager\Validator
 */
class ProjectValidator extends \Kanboard\Validator\ProjectValidator
{
    public function validateCreation(array $values)
    {
        if (! empty($values['identifier'])) {
            $values['identifier'] = strtoupper(preg_replace('/\s+/', '', $values['identifier']));
        }

        list($valid, $errors) = parent::validateCreation($values);

        $types = $this->projectModel->getProjectTypes();
        $type  = isset($values['project_type']) ? strtoupper(trim($values['project_type'])) : '';

        if (! array_key_exists($type, $types)) {
            $errors['project_type'] = array(t('Choose the aircraft type - the project code is built from it.'));
            return array(false, $errors);
        }

        if (! empty($values['identifier']) && ! $this->projectModel->isIdentifierForType($values['identifier'], $type)) {
            $errors['identifier'] = array(t('A %s code looks like %s01 - the type letters followed by two digits. Leave this empty and the next free one is used.', $types[$type], $type));
            return array(false, $errors);
        }

        return array($valid && empty($errors), $errors);
    }
}
