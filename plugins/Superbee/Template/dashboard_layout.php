<?php
/* Overrides app/Template/dashboard/layout.php.

   Kanboard's dashboard sub-navigation (Overview / My projects / My tasks /
   My subtasks) repeats the SUPERBEE rail almost item for item - Overview is
   Home, My projects is Projects, My tasks is My Tasks - so it is not rendered
   at all.

   Only "My subtasks" had no rail equivalent and is no longer reachable; it is
   a minor Kanboard view and was not worth a rail entry of its own.

   The New project / New personal project / My activity stream row has moved to
   the Projects grid: creating a project had nothing to do with My Tasks. */
?>
<section id="main">
    <section id="dashboard" class="sb-dashboard">
        <div class="sb-dashbody">
            <?= $content_for_sublayout ?>
        </div>
    </section>
</section>
