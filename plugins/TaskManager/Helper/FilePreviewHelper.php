<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;

/**
 * Which FileViewerController action can actually show a given file.
 *
 * Three templates linked every attachment to FileViewerController::show with
 * only a project_id, and both halves of that were wrong.
 *
 * The project_id half is why nothing opened at all. BaseController::getFile()
 * picks the table from the URL - a task_id means task_has_files, and its
 * absence means project_has_files - so a task attachment addressed with only
 * a project_id was looked for among the project documents, where it is not,
 * and the empty result is thrown as PageNotFoundException. That is the
 * "Sorry, I didn't find this information in my database!" page: a successful
 * query that returned no rows, not a database fault.
 *
 * The show half is why some of them would still have looked broken once the
 * id was right. The show action renders app/Template/file_viewer/show.php,
 * which can draw an image, markdown or plain text and nothing else. A PDF or
 * a .docx would have produced a header and an empty box. Core never links to
 * it blindly either; app/Template/task_file/files.php asks the same questions
 * this does before choosing.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class FilePreviewHelper extends Base
{
    /**
     * @param  string  $fileName  Empty when the file row no longer exists.
     * @param  mixed   $isImage   The is_image flag off task_has_files.
     * @return string  'show' | 'browser' | 'download' | '' (nothing to link to)
     */
    public function action($fileName, $isImage = false)
    {
        $fileName = (string) $fileName;

        /* No name means the attachment has been deleted while the submission
           row survives. The caller must render text, not a link: a link here
           is precisely the 404 this class exists to stop. */
        if ($fileName === '') {
            return '';
        }

        /* file_viewer/show.php reads is_image itself and emits an <img>, so
           an image is previewable whatever its extension says. */
        if (! empty($isImage)) {
            return 'show';
        }

        if ($this->helper->file->getPreviewType($fileName) !== null) {
            return 'show';
        }

        /* PDFs, audio and video stream inline, but in a tab of their own -
           the viewer template has no markup for them. */
        if ($this->helper->file->getBrowserViewType($fileName) !== null) {
            return 'browser';
        }

        /* A .docx, .xlsx or .zip cannot be shown in a browser at all, so the
           honest offer is the file itself. */
        return 'download';
    }

    /**
     * Whether that action opens in the in-page modal.
     *
     * @param  string $action
     * @return boolean
     */
    public function isModal($action)
    {
        return $action === 'show';
    }

    /**
     * Whether that action should open in a new tab.
     *
     * @param  string $action
     * @return boolean
     */
    public function isNewTab($action)
    {
        return $action === 'browser';
    }
}
