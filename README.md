CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Installation
 * Available drush commands

INTRODUCTION
------------

The Queue Watcher module lets you define automatic checks
for specific queues regarding their overall size.

A queue might get bloated due to missing or too slow worker jobs.
With this module, you can define size limits a queue shouldn't exceed.
During each cron run, the Queue Watcher checks the sizes of the queues
and sends reports to certain E-Mail addresses
and the logging system in case of exceeded limits.

The module also adds some Drush helper commands, e.g.
<code>$ drush queue-watcher-size-get</code>
.. to get a list of currently existent queues with their sizes.

Furthermore you can see the size status of your queues in the status report.

INSTALLATION
------------

Make sure your cron is running properly.

Install the module itself as usual, see
https://www.drupal.org/docs/8/extending-drupal-8/installing-contributed-modules-find-import-enable-configure-drupal-8.

Configure your queue sizes and target report addresses on
admin/config/queue-watcher

AVAILABLE DRUSH COMMANDS
------------------------

$ drush queue-watcher-size-get
.. returns a list of a specific or all queues with their current sizes.
See drush help queue-watcher-size-get for additional usage information.
